<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (is_null($this->resource) || $this->resource instanceof \Illuminate\Http\Resources\MissingValue) {
            return [];
        }
        // Résolution de la spécialité (plusieurs sources possibles)
        $specialite = $this->speciality?->name          // relation chargée  → colonne "name"
            ?? data_get($this->meta, 'specialite')      // stockée dans meta
            ?? $this->titre;                             // fallback ultime

        // Résolution du potentiel lisible
        $potentielLabel = $this->potential?->name
            ?? data_get($this->meta, 'potentiel');

        // Résolution de l'influence lisible
        $influenceLabel = $this->influence?->name       // relation Eloquent
            ?? data_get($this->meta, 'influence');

        return [
            // ── Identifiants ─────────────────────────────────────────────
            'id'           => $this->id,
            'local_uuid'   => $this->local_uuid,
            'code_externe' => $this->code_externe,

            // ── Identité ──────────────────────────────────────────────────
            'nom'       => $this->nom,
            'prenom'    => $this->prenom,
            'nom_complet' => trim("{$this->titre} {$this->prenom} {$this->nom}"),
            'titre'     => $this->titre,

            // ── Coordonnées ───────────────────────────────────────────────
            'telephone'    => $this->telephone,
            'whatsapp'     => $this->whatsapp,
            'email'        => $this->email,
            'ville'        => $this->ville,
            'zone_quartier'=> $this->zone_quartier,
            'adresse'      => $this->adresse
                              ?? $this->account?->adresse
                              ?? data_get($this->meta, 'adresse'),
            'gps_lat'      => $this->gps_lat,
            'gps_lng'      => $this->gps_lng,
            'pays'         => $this->pays ?? $this->country ?? $this->account?->pays ?? data_get($this->meta, 'pays'),
            'visits_count' => (int) ($this->activities_count ?? ($this->relationLoaded('activities') ? $this->activities->whereIn('statut', ['realisee', 'completee', 'effectuee'])->count() : \App\Models\Activity::withoutGlobalScopes()->where(function($q) { $q->where('contact_id', $this->id)->orWhereHas('contacts', fn($sub) => $sub->where('contacts.id', $this->id)); })->whereIn('statut', ['realisee', 'completee', 'effectuee'])->count())),
            'last_visit_date' => $this->last_visit_date ?? ($this->relationLoaded('activities') ? $this->activities->whereIn('statut', ['realisee', 'completee', 'effectuee'])->sortByDesc('date_activite')->first()?->date_activite : null),

            // ── Ciblage ───────────────────────────────────────────────────
            'potentiel'       => $potentielLabel,
            'influence_label' => $influenceLabel,
            'score'        => $this->score,
            'nb_patients_cible_mois'       => $this->nb_patients_cible_mois,
            'nb_visites_recommandees_mois' => $this->nb_visites_recommandees_mois,

            // ── Produits ──────────────────────────────────────────────────
            'produit_prioritaire'   => $this->produit_prioritaire,
            'produit_secondaire'    => $this->produit_secondaire,
            'autre_produit_interet' => $this->autre_produit_interet,

            // ── Relation commerciale ──────────────────────────────────────
            'canal_prefere'   => $this->canal_prefere,
            'statut_relation' => $this->statut_relation,

            // ── Statuts ───────────────────────────────────────────────────
            'statut'            => $this->statut,
            'statut_validation' => $this->statut_validation,

            // ── Spécialité & Meta ─────────────────────────────────────────
            'specialite' => $specialite,
            'meta'       => $this->meta,

            // ── FK brutes ─────────────────────────────────────────────────
            'account_id'      => $this->account_id,
            'contact_type_id' => $this->contact_type_id,
            'speciality_id'   => $this->speciality_id,
            'potential_id'    => $this->potential_id,
            'influence_id'    => $this->influence_id,
            'user_id'         => $this->user_id,

            // ── Relations chargées (whenLoaded) ───────────────────────────
            'contact_type' => $this->whenLoaded('contactType', fn() => [
                'id'      => $this->contactType->id,
                'code'    => $this->contactType->code,
                'libelle' => $this->contactType->libelle,
                'couleur' => $this->contactType->couleur ?? null,
                'icone'   => $this->contactType->icone ?? null,
            ]),

            'speciality' => $this->whenLoaded('speciality', fn() => [
                'id'   => $this->speciality->id,
                'name' => $this->speciality->name,   // ← "name", pas "nom"
                'code' => $this->speciality->code,
            ]),

            'segmentation' => $this->whenLoaded('segmentation', fn() => [
                'id'   => $this->segmentation->id,
                'name' => $this->segmentation->name, // ← "name"
            ]),

            'potential' => $this->whenLoaded('potential', fn() => [
                'id'   => $this->potential->id,
                'name' => $this->potential->name,    // ← "name"
                'code' => $this->potential->code,
            ]),

            'influence' => $this->whenLoaded('influence', fn() => [
                'id'   => $this->influence->id,
                'name' => $this->influence->name,    // ← "name"
            ]),

            'account' => (function($contact) {
                if ($contact->relationLoaded('account') && $contact->account) {
                    return new AccountResource($contact->account);
                }
                if ($contact->relationLoaded('accounts') && $contact->accounts->isNotEmpty()) {
                    return new AccountResource($contact->accounts->first());
                }
                if ($contact->account) {
                    return new AccountResource($contact->account);
                }
                return null;
            })($this),

            'accounts' => (function($contact) {
                if ($contact->relationLoaded('accounts') && $contact->accounts->isNotEmpty()) {
                    return AccountResource::collection($contact->accounts);
                }
                if ($contact->relationLoaded('account') && $contact->account) {
                    return AccountResource::collection(collect([$contact->account]));
                }
                if ($contact->account) {
                    return AccountResource::collection(collect([$contact->account]));
                }
                return [];
            })($this),

            // Établissement (nom textuel pour affichage direct dans la colonne Etablissement du web)
            'etablissement' => (function($contact) {
                if ($contact->relationLoaded('account') && $contact->account) {
                    return $contact->account->nom;
                }
                if ($contact->relationLoaded('accounts') && $contact->accounts->isNotEmpty()) {
                    return $contact->accounts->first()->nom;
                }
                if ($contact->account) {
                    return $contact->account->nom;
                }
                return data_get($contact->meta, 'etablissement')
                    ?? data_get($contact->meta, 'attached_account_names.0');
            })($this),

            // Délégué lié au prescripteur
            'delegue' => $this->whenLoaded('delegue', fn() => [
                'id'    => $this->delegue->id,
                'name'  => $this->delegue->name,
                'email' => $this->delegue->email,
            ]),

            // Champ pratique pour l'affichage liste
            'account_name' => (function($contact) {
                $acc = $contact->account ?? ($contact->relationLoaded('accounts') && $contact->accounts->isNotEmpty() ? $contact->accounts->first() : null);
                if ($acc) {
                    $typeStr = $acc->accountType?->libelle ?? $acc->accountType?->name ?? $acc->accountType?->code ?? $acc->type ?? data_get($acc->meta, 'type');
                    if (!empty($typeStr) && is_string($typeStr) && $acc->nom && !str_starts_with(mb_strtolower($acc->nom), mb_strtolower(trim($typeStr)))) {
                        return trim($typeStr) . ' ' . $acc->nom;
                    }
                    return $acc->nom;
                }
                return data_get($contact->meta, 'etablissement')
                    ?? data_get($contact->meta, 'attached_account_names.0');
            })($this),

            'attached_account_names' => (function($contact) {
                if ($contact->relationLoaded('accounts') && $contact->accounts->isNotEmpty()) {
                    return $contact->accounts->pluck('nom')->toArray();
                }
                if ($contact->relationLoaded('account') && $contact->account) {
                    return [$contact->account->nom];
                }
                if ($contact->account) {
                    return [$contact->account->nom];
                }
                $metaNames = data_get($contact->meta, 'attached_account_names');
                if (is_array($metaNames) && !empty($metaNames)) {
                    return $metaNames;
                }
                $metaEst = data_get($contact->meta, 'etablissement');
                return $metaEst ? [$metaEst] : [];
            })($this),

            // ── Dates ─────────────────────────────────────────────────────
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
