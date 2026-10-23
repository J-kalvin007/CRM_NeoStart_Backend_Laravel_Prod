# Planning : périodes et visites hors semaine

Migrations nécessaires : appliquer la migration de schéma ci-dessous puis `2026_10_22_121000_seed_calendar_activity_types.php`.

Migration de schéma : `php artisan migrate --path=database/migrations/2026_10_22_120000_add_planning_flexibility_to_activities.php`.

`POST /api/v1/activities` accepte :

- `outside_weekly_plan: true` pour une visite personnelle immédiatement exécutable, limitée à 5 par délégué et par semaine civile (lundi à dimanche, selon la date de visite).
- `activity_type: rcp|reunion|conge|permission`, `date_activite`, `date_fin`, `heure_debut` et `heure_fin` pour une activité sur une période. La fin doit suivre le début. Établissement et praticien sont facultatifs.

Les activités de calendrier restent dans le circuit de soumission hebdomadaire. Chaque jour couvert par une période compte dans la complétude du planning. Les visites hors planning en sont exclues.

Le quota est contrôlé dans une transaction avec verrou du délégué, à la création et lors des changements de semaine. Supprimer une visite ne rend pas la place disponible. Reprogrammer un brouillon ordinaire ne le valide pas.

Le web et le mobile proposent après 20 minutes de visite en cours de continuer ou de terminer. Continuer reporte le prochain rappel de 20 minutes ; terminer ouvre le parcours habituel avec confirmation/compte-rendu et contrôle GPS. Sur le web le rappel fonctionne tant que l'application est ouverte et revient au premier plan ; sur mobile il reste lié au parcours de visite.

## Configuration commune aux clients

`GET /api/v1/planning-options?date=YYYY-MM-DD` renvoie les types de calendrier actifs (UUID, code et libellé de la base), le quota utilisé/restant du délégué connecté pour cette semaine, et le délai de rappel. Les quotas ne sont jamais pris dans un cache hors ligne. Le catalogue et le délai peuvent être conservés sur mobile pour la consultation hors ligne.

Les libellés et l’activation des types se gèrent dans le référentiel existant `activity-types`. Les codes métier restent `rcp`, `reunion`, `conge`, `permission`. Les API d’activité renvoient `planning_kind` pour conserver la classification lorsque les libellés changent. Les types désactivés ne peuvent plus être utilisés pour une nouvelle activité.

Les paramètres serveur `PLANNING_VISIT_REMINDER_MINUTES` et `PLANNING_OUTSIDE_WEEKLY_LIMIT` alimentent `config/planning.php` (valeurs par défaut : 20 minutes et 5 visites ; le maximum reste 5). Les clients lisent ces valeurs au lieu de recopier ces règles.

## Soumission et documents

Toutes les activités calendaires (congés et permissions inclus) sont créées en brouillon et soumises avec la semaine. L’écran Congés crée une seule activité avec une date de début et de fin. Les décisions individuelles sont refusées ; le responsable traite le planning. Le refus se répercute sur les activités rattachées. Retirer un planning soumis ou refusé les remet en brouillon pour révision. Une validation ne doit jamais inclure les nouveaux brouillons ajoutés après la soumission.

Les activités calendaires ne sont pas des visites exécutables : pas de GPS, rappel de durée ou compte-rendu, pas de statut manqué calculé à leur échéance, et pas de décompte dans les objectifs de visites ou les étapes de tournée.

Les documents d’un produit sont associés exclusivement par identifiant (rattachement polymorphe `App\Models\Product`). Les titres, préfixes et dosages ne permettent pas de partager des documents entre produits. `GET /api/v1/documents?product_id=UUID` filtre cette affectation exacte. Appliquer aussi `2026_10_22_130000_remove_orphaned_document_subsidiary_constraint.php` pour retirer les contraintes restantes vers les anciennes tables supprimées ; les documents existants sont conservés.

Les congés et permissions réservent leur intervalle : une visite ne peut pas être planifiée pendant une absence non annulée/non refusée, et une absence ne peut pas recouvrir une activité existante. Plusieurs visites peuvent toujours partager un créneau en dehors de ces absences.

## Contrôle de qualité et organisation actuelle

La hiérarchie utilise `users.manager_id` et le pays, sans recréer les anciens modèles de filiales, secteurs ou unités organisationnelles. Les tests de ces parcours utilisent désormais cette structure et les prix produits par pays.

La migration `2026_10_23_090000_remove_orphaned_legacy_constraints.php` retire les clés étrangères encore dirigées vers les tables supprimées et rend facultatifs les anciens identifiants de filiale et d’unité. Les lignes et leurs anciennes valeurs sont conservées. Appliquer les migrations au déploiement avant d’utiliser les parcours corrigés.

Une panne ou une configuration absente du service push ne doit pas empêcher la notification interne ni la création d’un utilisateur. Cette situation est journalisée ; aucun client Firebase n’est initialisé pour un utilisateur sans jeton.

Vérification backend : `php artisan test`. Vérification web : `npm run typecheck`, `npm test`, `npm run build`. Le contrôle TypeScript parcourt les projets référencés ; une simple commande `vue-tsc --noEmit` à la racine ne suffit pas avec la configuration Nuxt actuelle.
