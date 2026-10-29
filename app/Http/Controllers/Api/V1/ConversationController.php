<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Scopes\DynamicPerimeterScope;
use App\Models\User;
use App\Support\CountryFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    private function visibleRecipients(Request $request)
    {
        $ids = User::query()->pluck('id')->all();
        if ($request->user()->manager_id) {
            $ids[] = $request->user()->manager_id;
        }

        return User::withoutGlobalScope(DynamicPerimeterScope::class)->whereIn('id', $ids)->where('id', '!=', $request->user()->id);
    }

    public function recipients(Request $request)
    {
        return response()->json(['data' => $this->visibleRecipients($request)->with('roles')->orderBy('name')->get()
            ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'country_id' => $user->country_id, 'roles' => $user->roles->pluck('name')])]);
    }

    private function scoped(Request $request)
    {
        $query = Conversation::whereHas('users', fn ($q) => $q->withoutGlobalScope(DynamicPerimeterScope::class)->where('users.id', $request->user()->id));
        $selected = CountryFilter::selectedValue();
        if ($selected && ! in_array($selected, ['group', 'default'], true)) {
            $country = CountryFilter::resolveCountryId();
            if (! $country) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(fn ($q) => $q->where('conversations.country_id', $country)->orWhere(fn ($legacy) => $legacy->whereNull('conversations.country_id')->whereHas('users', fn ($users) => $users->withoutGlobalScope(DynamicPerimeterScope::class)->where('users.country_id', $country))));
            }
        }

        return $query;
    }

    public function index(Request $request)
    {
        $data = $this->scoped($request)->with(['messages' => fn ($q) => $q->latest()->limit(1),
            'users' => fn ($q) => $q->withoutGlobalScope(DynamicPerimeterScope::class)->select('users.id', 'users.name')])->get()->map(function ($conversation) use ($request) {
                $last = $conversation->messages->first();

                return ['id' => $conversation->id, 'name' => $conversation->title ?: $conversation->users->where('id', '!=', $request->user()->id)->pluck('name')->join(', '),
                    'title' => $conversation->title, 'type' => $conversation->type, 'created_by' => $conversation->created_by,
                    'participants' => $conversation->users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
                    'can_post' => $this->canPost($request, $conversation),
                    'can_manage' => $conversation->created_by === $request->user()->id || $request->user()->isAdminOrDirection(),
                    'last_message' => $last?->content ?? '', 'last_message_at' => ($last?->created_at ?? $conversation->updated_at)->toISOString(),
                    'unread_count' => $this->unreadMessages($conversation, $request->user()->id)->count()];
            })->sortByDesc('last_message_at')->values();

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        $values = $request->validate(['recipient_id' => 'required_without:recipient_ids|nullable|uuid',
            'recipient_ids' => 'required_without:recipient_id|array|min:1|max:99', 'recipient_ids.*' => 'uuid|distinct',
            'title' => 'nullable|string|max:255', 'type' => 'nullable|string|max:100', 'message' => 'nullable|string|max:20000']);
        $ids = array_values(array_unique($values['recipient_ids'] ?? [$values['recipient_id']]));
        $this->validateRecipients($request, $ids);
        $raw = strtolower(Str::ascii($values['type'] ?? 'direct'));
        $type = match ($raw) {
            'group', 'groupe', 'team' => 'group', 'announcement', 'annonce' => 'announcement', default => 'direct'
        };
        if (count($ids) > 1 && $type === 'direct') {
            $type = 'group';
        }
        abort_if($type === 'announcement' && ! $request->user()->isAdminOrDirection() && ! $request->user()->hasAnyRole(['superviseur', 'supervisor', 'manager', 'sales_manager', 'sales-manager']), 403);
        abort_if($type !== 'direct' && empty(trim($values['title'] ?? '')), 422, 'Le titre du groupe ou de l’annonce est requis.');
        $this->validateFiles($request);
        $conversation = DB::transaction(function () use ($request, $values, $ids, $type) {
            $conversation = Conversation::create(['title' => $values['title'] ?? null, 'type' => $type,
                'created_by' => $request->user()->id, 'country_id' => CountryFilter::resolveCountryId()]);
            $conversation->users()->attach(array_merge([$request->user()->id], $ids));
            if (! empty($values['message']) || $request->hasFile('attachments')) {
                $this->writeMessage($request, $conversation, $values['message'] ?? '');
            }

            return $conversation;
        });

        return response()->json(['data' => ['id' => $conversation->id]], 201);
    }

    public function updateMembers(Request $request, string $id)
    {
        $conversation = $this->participantConversation($id, $request);
        abort_unless($conversation->created_by === $request->user()->id || $request->user()->isAdminOrDirection(), 403);
        abort_if($conversation->type === 'direct', 422, 'Cette conversation est directe.');
        $values = $request->validate(['recipient_ids' => 'required|array|min:1|max:99', 'recipient_ids.*' => 'uuid|distinct']);
        $ids = array_values(array_diff($values['recipient_ids'], [$request->user()->id]));
        $this->validateRecipients($request, $ids);
        $conversation->users()->sync(array_unique(array_merge([$conversation->created_by ?: $request->user()->id, $request->user()->id], $ids)));

        return response()->json(['message' => 'Participants mis à jour.']);
    }

    public function messages(string $id, Request $request)
    {
        $conversation = $this->participantConversation($id, $request);
        $messages = $conversation->messages()->with(['sender' => fn ($q) => $q->withoutGlobalScopes(), 'attachments'])->orderBy('created_at')->get();
        $received = $messages->where('sender_id', '!=', $request->user()->id);
        if (in_array($conversation->type, ['group', 'announcement'], true)) {
            foreach ($received->chunk(500) as $chunk) {
                DB::table('message_reads')->insertOrIgnore($chunk->map(fn ($message) => ['message_id' => $message->id, 'user_id' => $request->user()->id])->values()->all());
            }
        } else {
            Message::whereIn('id', $received->pluck('id'))->update(['is_read' => true]);
        }

        return response()->json(['data' => $messages->map(fn ($m) => $this->formatMessage($m))]);
    }

    public function sendMessage(string $id, Request $request)
    {
        $conversation = $this->participantConversation($id, $request);
        abort_unless($this->canPost($request, $conversation), 403, 'Cette annonce est en lecture seule.');
        $values = $request->validate(['content' => 'required_without:attachments|nullable|string|max:20000']);
        $this->validateFiles($request);
        $message = DB::transaction(fn () => $this->writeMessage($request, $conversation, $values['content'] ?? ''));

        return response()->json(['data' => $this->formatMessage($message->load(['sender' => fn ($q) => $q->withoutGlobalScopes(), 'attachments']))], 201);
    }

    private function writeMessage(Request $request, Conversation $conversation, string $content): Message
    {
        $paths = [];
        try {
            $message = Message::create(['conversation_id' => $conversation->id, 'sender_id' => $request->user()->id,
                'content' => $content ?: 'Pièce jointe', 'is_read' => false]);
            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store('messages/'.$message->id, 'local');
                if (! $path) {
                    throw new \RuntimeException('Enregistrement de la pièce jointe impossible.');
                }
                $paths[] = $path;
                $name = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName())));
                $message->attachments()->create(['path' => $path, 'name' => mb_substr($name, 0, 200), 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
            }
            $conversation->touch();
            try {
                foreach ($conversation->users()->withoutGlobalScopes()->where('users.id', '!=', $request->user()->id)->get() as $recipient) {
                    NotificationController::sendNotification($recipient, 'Nouveau message', $request->user()->name.' : '.mb_strimwidth($message->content, 0, 120, '…'), 'message', 'all', ['id' => $conversation->id, 'type' => 'conversation']);
                }
            } catch (\Throwable $error) {
                report($error);
            }

            return $message;
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }
    }

    private function validateFiles(Request $request): void
    {
        $request->validate(['attachments' => 'sometimes|array|min:1|max:5',
            'attachments.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,webp,txt,doc,docx,xls,xlsx,ppt,pptx']);
    }

    public function attachment(Request $request, string $id, string $message, string $attachment)
    {
        $this->participantConversation($id, $request);
        $item = MessageAttachment::where('message_id', $message)->whereHas('message', fn ($q) => $q->where('conversation_id', $id))->findOrFail($attachment);
        abort_unless(Storage::disk('local')->exists($item->path), 404);

        return Storage::disk('local')->download($item->path, $item->name, ['X-Content-Type-Options' => 'nosniff']);
    }

    private function formatMessage(Message $message): array
    {
        return ['id' => $message->id, 'conversation_id' => $message->conversation_id, 'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name ?? '', 'content' => $message->content, 'created_at' => $message->created_at->toISOString(),
            'attachments' => $message->attachments->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'mime' => $a->mime, 'size' => $a->size,
                'url' => '/api/v1/conversations/'.$message->conversation_id.'/messages/'.$message->id.'/attachments/'.$a->id])];
    }

    private function unreadMessages(Conversation $conversation, string $userId)
    {
        $query = Message::where('conversation_id', $conversation->id)->where('sender_id', '!=', $userId);

        return in_array($conversation->type, ['group', 'announcement'], true)
            ? $query->whereNotExists(fn ($reads) => $reads->selectRaw('1')->from('message_reads')->whereColumn('message_reads.message_id', 'messages.id')->where('message_reads.user_id', $userId))
            : $query->where('is_read', false);
    }

    private function canPost(Request $request, Conversation $conversation): bool
    {
        return $conversation->type !== 'announcement' || $conversation->created_by === $request->user()->id || $request->user()->isAdminOrDirection();
    }

    private function validateRecipients(Request $request, array $ids): void
    {
        $count = $this->visibleRecipients($request)->whereIn('id', $ids)->count();
        abort_unless($ids && $count === count($ids), 422, 'Destinataire absent du périmètre sélectionné.');
    }

    private function participantConversation(string $id, Request $request): Conversation
    {
        $conversation = Conversation::findOrFail($id);
        abort_unless($conversation->users()->withoutGlobalScopes()->where('users.id', $request->user()->id)->exists(), 403, 'Vous ne participez pas à cette conversation.');

        return $this->scoped($request)->findOrFail($id);
    }
}
