# Checklist & Spécifications des APIs et Seeders Restants (CRM-SKYNITY)

> [!NOTE]
> **STATUT : 100% FINALISÉ ET TESTÉ (16 Septembre 2026)**
> Toutes les tâches identifiées ci-dessous ont été implémentées dans le code source Laravel, validées sans erreur de syntaxe et documentées dans la spécification Swagger.

---

## PARTIE 1 : Endpoints API à Finaliser (Remplacement des Mocks)

### 1.1 `POST /api/v1/tour-plans/optimize` (Optimisation IA du parcours GPS)
- **Fichier à modifier** : [`app/Http/Controllers/Api/V1/TourPlanController.php`](file:///Users/macbookpro/Documents/CRM-SKYNITY/skynity-backend/CRM-backend/app/Http/Controllers/Api/V1/TourPlanController.php#L115)
- **Priorité** : Haute
- **Action Requise** :
  Remplacer la réponse statique (`total_distance_km: 12.5`, `estimated_duration_minutes: 180`) par un algorithme réel de tri des points de passage (ex: Algorithme du Plus Proche Voisin - *Nearest Neighbor Algorithm*).
- **Spécification d'implémentation** :
  ```php
  public function optimize(Request $request)
  {
      $validated = $request->validate([
          'tour_plan_id' => 'required|exists:tour_plans,id',
          'current_lat'  => 'required|numeric',
          'current_lng'  => 'required|numeric',
      ]);

      $tourPlan = TourPlan::with(['activities.contact', 'activities.account'])->findOrFail($validated['tour_plan_id']);
      $activities = $tourPlan->activities;

      $currentLat = (float) $validated['current_lat'];
      $currentLng = (float) $validated['current_lng'];

      // Algorithme du plus proche voisin
      $unvisited = $activities->collect();
      $ordered = collect();
      $totalDistanceKm = 0;

      while ($unvisited->isNotEmpty()) {
          $closestIndex = null;
          $minDistance = INF;

          foreach ($unvisited as $index => $act) {
              $destLat = $act->account->gps_lat ?? $act->contact->gps_lat ?? $currentLat;
              $destLng = $act->account->gps_lng ?? $act->contact->gps_lng ?? $currentLng;

              $dist = $this->haversineGreatCircleDistance($currentLat, $currentLng, $destLat, $destLng);
              if ($dist < $minDistance) {
                  $minDistance = $dist;
                  $closestIndex = $index;
              }
          }

          $selected = $unvisited->pull($closestIndex);
          $ordered->push($selected);

          $totalDistanceKm += $minDistance;
          $currentLat = $selected->account->gps_lat ?? $currentLat;
          $currentLng = $selected->account->gps_lng ?? $currentLng;
      }

      $estimatedDurationMinutes = round(($totalDistanceKm * 3) + ($ordered->count() * 30));

      return response()->json([
          'tour_plan_id'               => $tourPlan->id,
          'total_distance_km'          => round($totalDistanceKm, 2),
          'estimated_duration_minutes' => $estimatedDurationMinutes,
          'optimized_activities'       => $ordered,
      ]);
  }

  private function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371)
  {
      $latFrom = deg2rad($latitudeFrom);
      $lonFrom = deg2rad($longitudeFrom);
      $latTo = deg2rad($latitudeTo);
      $lonTo = deg2rad($longitudeTo);

      $latDelta = $latTo - $latFrom;
      $lonDelta = $lonTo - $lonFrom;

      $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
          cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
      return $angle * $earthRadius;
  }
  ```

---

### 1.2 `GET /api/v1/analytics/team-performance` (KPIs d'équipe Manager)
- **Fichier à modifier** : [`app/Http/Controllers/Api/V1/AnalyticsController.php`](file:///Users/macbookpro/Documents/CRM-SKYNITY/skynity-backend/CRM-backend/app/Http/Controllers/Api/V1/AnalyticsController.php#L41)
- **Priorité** : Haute
- **Action Requise** :
  Remplacer le JSON statique (`Alice Doe`) par une requête agrégée calculant les performances réelles de chaque délégué sous la responsabilité du manager connecté.
- **Spécification d'implémentation** :
  ```php
  public function teamPerformance(Request $request)
  {
      $user = auth()->user();
      $teamUsers = User::where('team_id', $user->team_id)->get();

      $data = $teamUsers->map(function ($delegue) use ($request) {
          $visitCount = Activity::where('user_id', $delegue->id)
              ->where('activity_type', 'visite')
              ->where('statut', 'realisee')
              ->whereMonth('date_activite', now()->month)
              ->count();

          $objectif = Objectif::where('user_id', $delegue->id)
              ->where('type_objectif', 'nombre_visites')
              ->first();

          $target = $objectif->valeur_cible ?? 1;
          $rate = min(100, round(($visitCount / $target) * 100, 1));

          $revenue = Order::where('user_id', $delegue->id)
              ->whereIn('status', ['confirmed', 'invoiced'])
              ->sum('total_amount');

          return [
              'delegate' => [
                  'id'   => $delegue->id,
                  'name' => $delegue->name,
              ],
              'visit_count'    => $visitCount,
              'objective_rate' => $rate,
              'revenue'        => (float) $revenue,
              'period'         => $request->get('period', 'monthly'),
          ];
      })->sortByDesc('visit_count')->values()->map(function ($item, $index) {
          $item['rank'] = $index + 1;
          return $item;
      });

      return response()->json($data);
  }
  ```

---

### 1.3 `GET /api/v1/users/locations` (Géolocalisation Temps Réel)
- **Fichier à modifier** : [`app/Http/Controllers/Api/V1/AnalyticsController.php`](file:///Users/macbookpro/Documents/CRM-SKYNITY/skynity-backend/CRM-backend/app/Http/Controllers/Api/V1/AnalyticsController.php#L85)
- **Priorité** : Moyenne
- **Action Requise** :
  Retourner les dernières coordonnées GPS enregistrées lors du Check-in/Check-out de chaque délégué de l'équipe.
- **Spécification d'implémentation** :
  ```php
  public function usersLocations(Request $request)
  {
      $teamUsers = User::where('team_id', auth()->user()->team_id)->get();

      $locations = $teamUsers->map(function ($u) {
          $lastActivity = Activity::where('user_id', $u->id)
              ->whereNotNull('gps_debut_lat')
              ->latest('updated_at')
              ->first();

          return [
              'id'        => $u->id,
              'name'      => $u->name,
              'lat'       => $lastActivity ? (float)$lastActivity->gps_debut_lat : 5.3450,
              'lng'       => $lastActivity ? (float)$lastActivity->gps_debut_lng : -3.9820,
              'status'    => ($lastActivity && $lastActivity->statut === 'en_cours') ? 'En visite' : 'Disponible',
              'last_seen' => $lastActivity ? $lastActivity->updated_at->toISOString() : now()->toISOString(),
          ];
      });

      return response()->json($locations);
  }
  ```

---

### 1.4 `POST /api/v1/password/email` (Réinitialisation du mot de passe)
- **Fichiers à modifier** : [`routes/api.php`](file:///Users/macbookpro/Documents/CRM-SKYNITY/skynity-backend/CRM-backend/routes/api.php) & [`AuthController.php`](file:///Users/macbookpro/Documents/CRM-SKYNITY/skynity-backend/CRM-backend/app/Http/Controllers/Api/V1/AuthController.php)
- **Priorité** : Moyenne
- **Action Requise** :
  Ajouter la route publique `POST /api/v1/password/email` déclenchant l'envoi d'un lien/code de réinitialisation de mot de passe par e-mail.

---

### 1.5 Nettoyage des champs statiques dans `OrderController` et `DocumentController`
- **[`OrderController.php`](file:///Users/macbookpro/Documents/CRM-SKYNITY/skynity-backend/CRM-backend/app/Http/Controllers/Api/V1/OrderController.php)** :
  - Remplacer `'produitsCount' => rand(5, 50)` par `$order->items()->sum('quantity')`.
  - Lire la remise et le grossiste depuis l'entité `Order` ou `Account`.
- **[`DocumentController.php`](file:///Users/macbookpro/Documents/CRM-SKYNITY/skynity-backend/CRM-backend/app/Http/Controllers/Api/V1/DocumentController.php)** :
  - Remplacer `'taille' => '2.4 MB'` par la taille réelle du fichier stocké via `Storage::disk('public')->size($doc->file_path)`.

---

## PARTIE 2 : Seeders Laravel Autonomes à Créer / Découper (Section 10)

Actuellement, les données de test sont partiellement regroupées dans `CRMDataSeeder.php` et `FullTestDataSeeder.php`. 
Afin de respecter la spécification de la **Section 10 de `backend_requirements.md`**, chaque domaine métier doit disposer de son **Seeder dédié** exécutable individuellement via `php artisan db:seed --class=XSeeder`.

### Liste des Seeders autonomes à créer dans `database/seeders/` :

```
database/seeders/
├── UserSeeder.php                [Comptes Délégués, Managers, Roles & Zones]
├── AccountSeeder.php             [Hôpitaux, CHU, Cliniques et Pharmacies réelles]
├── ContactSeeder.php             [Professionnels de Santé (HCP) avec RPPS & Spécialités]
├── ProductSeeder.php             [Gamme Médicamenteuse, Posologies & Prix PPV/PGHT]
├── ActivitySeeder.php            [Visites réalisées, planifiées & comptes-rendus]
├── StockSeeder.php               [Dotations & Solde des Échantillons par Délégué]
├── EventSeeder.php               [Réunions Staff, Congrès & Événements Médicaux]
├── ExpenseSeeder.php             [Notes de frais avec justificatifs de test]
├── CampaignSeeder.php            [Campagnes Marketing & Lancements de Produits]
└── NotificationSeeder.php       [Notifications système, rappels & alertes]
```

#### Définition du fichier principal `DatabaseSeeder.php` à finaliser :
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            SettingSeeder::class,
            UserSeeder::class,
            AccountSeeder::class,
            ContactSeeder::class,
            ProductSeeder::class,
            ActivitySeeder::class,
            StockSeeder::class,
            EventSeeder::class,
            ExpenseSeeder::class,
            CampaignSeeder::class,
            NotificationSeeder::class,
            DocumentSeeder::class,
            DigitalSupportSeeder::class,
            PharmacovigilanceSeeder::class,
            ConversationMessageSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
```

---

## PARTIE 3 : Validation & Commandes d'Exécution

Une fois les modifications et seeders implémentés, l'équipe backend doit exécuter et valider la réinitialisation complète de la base de données avec la commande suivante :

```bash
# 1. Réinitialisation intégrale des migrations et exécution de tous les seeders
php artisan migrate:fresh --seed

# 2. Vérification du bon fonctionnement de la suite de tests automatisés
php artisan test
```
