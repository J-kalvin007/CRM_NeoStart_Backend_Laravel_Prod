<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use Illuminate\Validation\ValidationException;

class DuplicateDetectionService
{
    /**
     * Vérifie si un Contact en doublon existe déjà dans le même pays.
     * Critères : (Nom ET Prénom) OU (Email) OU (Téléphone)
     *
     * @param string $countryId L'ID du pays du compte associé
     * @param array $data Les données du contact
     * @param string|null $excludeId ID du contact à ignorer (pour les updates)
     * @return Contact|null Le contact en doublon ou null
     */
    public function findDuplicateContact(?string $countryId, array $data, ?string $excludeId = null): ?Contact
    {
        $query = Contact::query();

        if ($countryId) {
            $query->whereHas('account.organizationalUnit', function($q) use ($countryId) {
                // TODO: country_id might not exist on organizational_units.
                $q->where('country_id', $countryId);
            });
        }

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $email = $data['email'] ?? null;
        $telephone = $data['telephone'] ?? null;
        $nom = $data['nom'] ?? null;
        $prenom = $data['prenom'] ?? null;
        $meta = $data['meta'] ?? []; // On ne cherche pas encore par meta spécifique pour simplifier, mais c'est prévu.

        if (!$email && !$telephone && !($nom && $prenom)) {
            return null;
        }

        return $query->where(function($q) use ($email, $telephone, $nom, $prenom) {
            if ($email) $q->orWhere('email', $email);
            if ($telephone) $q->orWhere('telephone', $telephone);
            if ($nom && $prenom) {
                $q->orWhere(function($subQ) use ($nom, $prenom) {
                    $subQ->where('nom', $nom)->where('prenom', $prenom);
                });
            }
        })->first();
    }

    /**
     * Vérifie si un Compte en doublon existe déjà dans la même filiale.
     * Critères : (Nom ET Ville) OU (Email) OU (Téléphone)
     */
    public function findDuplicateAccount(?string $organizationalUnitId, array $data, ?string $excludeId = null): ?Account
    {
        $query = Account::query();

        // Si une unité org est définie on restreint la recherche à ce périmètre,
        // sinon on cherche globalement (évite de rater un doublon sans unité).
        if ($organizationalUnitId) {
            $query->where('organizational_unit_id', $organizationalUnitId);
        }

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $email     = $data['email']     ?? null;
        $telephone = $data['telephone'] ?? null;
        $nom       = $data['nom']       ?? null;
        $ville     = $data['ville']     ?? null;

        if (!$email && !$telephone && !($nom && $ville)) {
            return null; // Pas assez de critères
        }

        return $query->where(function($q) use ($email, $telephone, $nom, $ville) {
            if ($email)             $q->orWhere('email', $email);
            if ($telephone)         $q->orWhere('telephone', $telephone);
            if ($nom && $ville) {
                $q->orWhere(function($subQ) use ($nom, $ville) {
                    $subQ->where('nom', $nom)->where('ville', $ville);
                });
            }
        })->first();
    }

    /**
     * Lance une exception 409 Conflict si un doublon de contact est détecté.
     */
    public function abortIfDuplicateContact(?string $countryId, array $data, ?string $excludeId = null): void
    {
        $duplicate = $this->findDuplicateContact($countryId, $data, $excludeId);
        if ($duplicate) {
            abort(response()->json([
                'message'        => 'Un contact similaire existe déjà.',
                'duplicate_id'   => $duplicate->id,
                'duplicate_name' => trim(($duplicate->nom ?? '') . ' ' . ($duplicate->prenom ?? '')),
            ], 409));
        }
    }

    /**
     * Lance une exception 409 Conflict si un doublon de compte est détecté.
     */
    public function abortIfDuplicateAccount(?string $organizationalUnitId, array $data, ?string $excludeId = null): void
    {
        $duplicate = $this->findDuplicateAccount($organizationalUnitId, $data, $excludeId);
        if ($duplicate) {
            abort(response()->json([
                'message'        => 'Un compte similaire existe déjà dans cette filiale.',
                'duplicate_id'   => $duplicate->id,
                'duplicate_name' => $duplicate->nom ?? 'Inconnu',
            ], 409));
        }
    }
}
