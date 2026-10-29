<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FieldPost;
use App\Support\CountryFilter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FieldPostController extends Controller
{
    private function authorizeWrite(Request $request): void
    {
        abort_unless($request->user()->can('scope:global') || $request->user()->hasAnyRole(['superadmin', 'super-admin', 'admin']), 403);
    }

    public function index()
    {
        return response()->json(['data' => FieldPost::with('country')->withCount('users')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $this->authorizeWrite($request);
        $values = $this->values($request);

        return response()->json(['data' => FieldPost::create($values)->load('country')], 201);
    }

    public function update(Request $request, string $id)
    {
        $this->authorizeWrite($request);
        $post = FieldPost::findOrFail($id);
        $values = $this->values($request, $id);
        abort_if($post->country_id !== $values['country_id'] && $post->users()->withoutGlobalScopes()->exists(), 409, 'Réaffectez les collaborateurs avant de changer le pays du poste.');
        $post->update($values);

        return response()->json(['data' => $post->fresh('country')]);
    }

    public function destroy(Request $request, string $id)
    {
        $this->authorizeWrite($request);
        $post = FieldPost::findOrFail($id);
        abort_if($post->users()->withoutGlobalScopes()->exists(), 409, 'Ce poste est affecté à des collaborateurs. Désactivez-le ou réaffectez-les.');
        $post->delete();

        return response()->noContent();
    }

    private function values(Request $request, ?string $id = null): array
    {
        $values = $request->validate(['country_id' => 'required|uuid|exists:countries,id',
            'name' => ['required', 'string', 'max:255', Rule::unique('field_posts')->where('country_id', $request->country_id)->ignore($id)],
            'city' => 'nullable|string|max:255', 'is_active' => 'sometimes|boolean']);
        $selected = CountryFilter::selectedValue();
        if ($selected && ! in_array($selected, ['group', 'default'], true)) {
            abort_unless(CountryFilter::resolveCountryId() === $values['country_id'], 422, 'Le poste doit appartenir au pays sélectionné.');
        }

        return $values;
    }
}
