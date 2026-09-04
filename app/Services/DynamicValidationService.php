<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DynamicValidationService
{
    /**
     * Valide le champ 'meta' d'une requête en fonction du Type (AccountType ou ContactType).
     *
     * @param array $metaData Les données envoyées dans le champ meta
     * @param mixed $typeModel Le modèle AccountType ou ContactType
     * @throws ValidationException
     */
    public function validateMeta(?array $metaData, $typeModel): void
    {
        // Si le type n'a pas de règles, on ne valide rien de plus
        if (!$typeModel || empty($typeModel->validation_rules)) {
            return;
        }

        $rules = $typeModel->validation_rules;
        
        // Comme les règles sont pour le champ meta, on les préfixe pour le Validator
        // Ex: "finess" devient "meta.finess"
        $prefixedRules = [];
        foreach ($rules as $key => $rule) {
            $prefixedRules["meta.{$key}"] = $rule;
        }

        // On crée un tableau avec les données meta préfixées pour le Validator
        $dataToValidate = ['meta' => $metaData ?? []];

        $validator = Validator::make($dataToValidate, $prefixedRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
