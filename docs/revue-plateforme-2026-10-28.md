# Revue de la plateforme — 28 octobre 2026

## Périmètre et méthode

Inventaire des sources applicatives : 235 fichiers PHP backend, 130 fichiers Vue/TypeScript dans app et 227 fichiers Dart mobiles. Recherches globales des imports absents, routes, contrôles d'accès, dépendances retirées, journaux de connexion, réponses API et faux succès. Lecture approfondie des parcours sensibles ; exécution des suites disponibles. Un inventaire automatique et des tests ne constituent pas une garantie d'absence de défaut dans chaque ligne.

## Tâches

- [x] Backend : corriger la synchronisation et son isolation, vérifier le périmètre des requêtes rejouées.
- [x] Backend : interdire l'accès des comptes désactivés et encadrer les tentatives d'authentification.
- [x] Backend / mobile : rendre la récupération de mot de passe cohérente et retirer les succès simulés.
- [x] Backend : limiter les paramètres publics et les fichiers téléversés aux types attendus.
- [x] Web : retirer les journaux contenant mots de passe, jetons et réponses d'authentification.
- [x] Mobile : rendre les échecs de synchronisation visibles et corriger les diagnostics de l'analyse globale.
- [x] Exécuter les contrôles et documenter les propositions restantes et les limites.

## Anomalies corrigées

| Priorité | Parcours | Constat et correction |
|---|---|---|
| Haute | Comptes et sessions | Les comptes désactivés pouvaient se connecter ou utiliser des jetons existants. Contrôle ajouté à la connexion, à l’OTP et aux routes authentifiées. Tentatives de connexion / OTP / récupération limitées. |
| Haute | Mot de passe | La notification de changement était appelée avec un argument manquant et pouvait provoquer une erreur 500 après le changement effectif. Correction du contrat et retrait du mot de passe du courriel de confirmation. Les anciens jetons sont révoqués après réinitialisation ou changement administratif. |
| Haute | Récupération mobile | L’interface avançait vers la réussite en cas d’échec réseau ; elle demandait un code numérique alors que le serveur envoyait un jeton alphanumérique. Les erreurs arrêtent maintenant le parcours ; vérification du jeton ajoutée au backend ; champ mobile cohérent. |
| Haute | Synchronisation backend | Ancien modèle Leave inexistant, chemins d’API préfixés incorrectement, destinations arbitraires et récursives possibles, perte des en-têtes pays lors du rejeu. Correction du modèle, validation de la file, liste de destinations autorisées, conservation du contexte et confidentialité des brouillons. |
| Haute | Synchronisation mobile | Les refus serveur pouvaient entraîner la suppression des opérations et des saisies provisoires. Elles sont désormais conservées. Les erreurs de rechargement exposées par les contrôleurs empêchent un résultat global de réussite et la mise à jour de la date de synchronisation. Cette date est isolée par utilisateur et pays. |
| Haute | Pharmacovigilance | Le formulaire pouvait remplacer une sélection non résolue par le premier produit / prescripteur, inventer un âge de 45 ans et utiliser un cache non isolé. Sélections liées aux identifiants réels, absence de présélection arbitraire, âge facultatif et validé, cache par utilisateur/pays, pagination et remplacement du cache même avec une réponse vide. |
| Haute | Formations | La progression était commune à tous les utilisateurs. Nouvelle table de progression personnelle ; correction des filtres sur des colonnes absentes ; retrait des faux quiz et badges. Les interfaces ouvrent un contenu réel lorsqu’il est renseigné. Une déclaration de fin de formation ne constitue plus une certification. |
| Haute | Confidentialité web | Retrait des traces de connexion contenant fragments de mots de passe, réponses avec jetons ou configuration Axios. Suppression de la page de diagnostic publique. |
| Haute | Fichiers | Les médias acceptaient du contenu exécutable ; les archives HTML pouvaient contenir des scripts serveur et fichiers de configuration. Types de médias encadrés, extensions déterminées côté serveur, exclusion des fichiers exécutables et chemins spéciaux, limites d’extraction. Les ressources JavaScript nécessaires aux présentations HTML restent prises en charge. |
| Moyenne | Paramètres publics | L’API publique renvoyait toutes les clés. Elle ne publie plus que les clés nécessaires à l’apparence et aux objectifs généraux. |
| Moyenne | Objectifs et référentiels | Retrait du chargement d’équipes obsolètes qui pouvait interrompre l’initialisation des objectifs ; suppression de stores inutilisés et appels mobile aux anciens secteurs / filiales. |
| Moyenne | Conformité | Retrait des scores fixes 98,2 %, du cas GPS inventé, de la version de moteur IA prétendument active et du seuil de distance non calculé. L’absence du service est explicitement signalée et les KPI ne se présentent pas comme des résultats disponibles. |

## Couverture et validation

- Inventaire des routes : 372 routes API ; rapprochement de 142 appels littéraux web et 78 appels littéraux mobile avant corrections. Les appels dynamiques ne sont pas tous prouvés par cette vérification.
- Syntaxe PHP : 239 fichiers app/routes contrôlés sans erreur. La nouvelle migration est exécutée dans les bases de test par RefreshDatabase.
- Backend : suite complète réussie, 209 tests et 1 231 assertions, dont confidentialité des progressions, récupération de mot de passe, comptes désactivés, refus de contenu exécutable et synchronisation réelle.
- Web : suite globale et tests de régression sur les journaux de connexion et le parcours formation ; contrôle TypeScript et compilation Nuxt. Résultat final : 103 tests web réussis ; contrôle TypeScript et compilation Nuxt réussis.
- Mobile : analyse globale sans erreur ni warning ; 14 informations de style / API dépréciée. Dix contrôles Dart sur les stocks, catégories et données patient réussissent. Les tests Flutter ont été tentés : le système refuse la création de leur socket sur 127.0.0.1 (Operation not permitted). Ils ne sont donc pas validés.
- Composer valide le manifeste, avec une remarque sur la contrainte non bornée de kreait/laravel-firebase. La compilation web signale l’incompatibilité du module PrimeVue installé avec Nuxt ; elle termine néanmoins correctement.

## Propositions restant à traiter

1. **Haute — Rejeux idempotents.** Généraliser une clé d’opération unique, conservée par le mobile et vérifiée transactionnellement par le serveur, sur les créations et remises. Cela protège notamment le cas où le serveur a accepté une opération mais où l’accusé de réception s’est perdu. Conserver une file ne suffit pas à garantir l’absence de doublons.
2. **Haute — Service de conformité.** Aucun endpoint compliance/anomalies n’est implémenté dans le serveur actuel. Définir les règles, les périmètres et le stockage des décisions avant de connecter le journal. Ne pas inventer des seuils ni annoncer un moteur d’IA actif.
3. **Moyenne — Contenus et évaluations de formation.** Renseigner les ressources réelles et construire un service de quiz, scores et attestations si une certification est souhaitée. L’état terminé reste une progression personnelle déclarative.
4. **Moyenne — Configuration de livraison.** Vérifier API_BASE_URL pour chaque build mobile et l’origine du frontend / backend réellement déployés. Le défaut local du code mobile ne convient pas à une installation distante. Les migrations, dépendances d’export et mises à jour des clients doivent être coordonnées.
5. **Maintenance — Dépendances et code retiré.** Traiter l’incompatibilité locale PrimeVue/Nuxt, borner la dépendance Firebase, migrer les usages Flutter dépréciés et retirer les anciens contrôleurs/policies de hiérarchie non routés qui référencent des modèles supprimés. Un audit des vulnérabilités en ligne et un essai de bout en bout en environnement de recette restent à réaliser.

## Déploiement

Cette revue modifie uniquement les fichiers locaux et les bases temporaires des tests. Aucune base de production n’a été modifiée. Le serveur devra installer la nouvelle migration avant de servir les nouvelles API de formation :

```sh
php artisan migrate --force
```

La migration ajoute category et file_url au catalogue de formations et crée training_user_progress sans supprimer les anciennes valeurs globales. Ces anciennes valeurs ne sont pas attribuées arbitrairement à des utilisateurs. Les nouveaux clients doivent être livrés après le backend et la migration. Le push Git n’est pas un déploiement.

## Clôture locale

Les correctifs sont enregistrés dans les dépôts backend, frontend et mobile. Aucun push ni déploiement n’est annoncé par cette revue. Les tests Flutter bloqués, la migration à livrer et les propositions ci-dessus restent explicitement distincts des contrôles réussis.
