<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Contact;
use App\Models\Account;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Réparer les contacts ayant des comptes dans la table pivot account_contact mais account_id NULL
        if (Schema::hasTable('account_contact') && Schema::hasTable('contacts')) {
            $pivotContacts = DB::table('account_contact')
                ->select('contact_id', 'account_id', 'is_primary')
                ->orderBy('is_primary', 'desc')
                ->get()
                ->groupBy('contact_id');

            foreach ($pivotContacts as $contactId => $rows) {
                $primaryAccountId = $rows->first()->account_id;
                DB::table('contacts')
                    ->where('id', $contactId)
                    ->whereNull('account_id')
                    ->update(['account_id' => $primaryAccountId]);
            }

            // 2. Réparer les contacts ayant account_id renseigné mais absents de account_contact
            $contactsWithAccount = DB::table('contacts')
                ->whereNotNull('account_id')
                ->select('id', 'account_id')
                ->get();

            foreach ($contactsWithAccount as $c) {
                $exists = DB::table('account_contact')
                    ->where('contact_id', $c->id)
                    ->where('account_id', $c->account_id)
                    ->exists();

                if (!$exists) {
                    DB::table('account_contact')->insert([
                        'id' => \Illuminate\Support\Str::uuid()->toString(),
                        'contact_id' => $c->id,
                        'account_id' => $c->account_id,
                        'is_primary' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // 3. Réparer les contacts ayant un nom d'établissement dans meta
            $contactsWithMeta = DB::table('contacts')
                ->whereNull('account_id')
                ->whereNotNull('meta')
                ->select('id', 'meta')
                ->get();

            foreach ($contactsWithMeta as $c) {
                $meta = json_decode($c->meta, true);
                if (!is_array($meta)) continue;

                $estName = $meta['etablissement'] ?? null;
                if (!$estName && !empty($meta['attached_account_names'][0])) {
                    $estName = $meta['attached_account_names'][0];
                }

                if ($estName && is_string($estName) && trim($estName) !== '' && trim($estName) !== 'Non rattaché') {
                    $acc = DB::table('accounts')
                        ->where('nom', trim($estName))
                        ->orWhere('nom', 'like', '%' . trim($estName) . '%')
                        ->first();

                    if ($acc) {
                        DB::table('contacts')->where('id', $c->id)->update(['account_id' => $acc->id]);

                        $exists = DB::table('account_contact')
                            ->where('contact_id', $c->id)
                            ->where('account_id', $acc->id)
                            ->exists();

                        if (!$exists) {
                            DB::table('account_contact')->insert([
                                'id' => \Illuminate\Support\Str::uuid()->toString(),
                                'contact_id' => $c->id,
                                'account_id' => $acc->id,
                                'is_primary' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas d'action inverse requise pour une réparation de données
    }
};
