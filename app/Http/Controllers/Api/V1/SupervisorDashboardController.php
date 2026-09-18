<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Activity;

class SupervisorDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        return response()->json([
            'supervision' => [
                'region' => 'SUPERVISION RÉGIONALE',
                'team' => 'Équipe Ouest / Togo-Bénin',
                'activeDelegates' => 6,
                'connectedPercentage' => 100,
            ],
            'kpis' => [
                [
                    'title' => 'VISITES MTD',
                    'value' => '184',
                    'subtitle' => '/220',
                    'progress' => 0.84,
                    'bottomLabel' => '↗ 84% de l\'objectif',
                ],
                [
                    'title' => 'COUVERTURE',
                    'value' => '88%',
                    'progress' => 0.88,
                    'bottomLabel' => 'Cible A/B prioritaire',
                ],
                [
                    'title' => 'FRAIS EN COURS',
                    'value' => '4',
                    'subtitleText' => 'À valider',
                    'bottomBadge' => '⏳ Action requise',
                ],
                [
                    'title' => 'ALERTES STOCK',
                    'value' => '2',
                    'subtitleText' => 'Ruptures signalées',
                    'bottomBadge' => 'Lomé & Cotonou',
                ],
            ],
            'liveActivities' => [
                [
                    'name' => 'Jean Dupont',
                    'sector' => 'Secteur Lomé Centre',
                    'statusBadgeText' => 'En visite (12 min)',
                    'subText' => 'Dr. Aka (Cardiologie - CHU Sylvanus Oly...',
                ],
                [
                    'name' => 'Sarah Lawson',
                    'sector' => 'Secteur Cotonou Littoral',
                    'statusBadgeText' => 'En route (GPS actif)',
                    'subText' => 'Vers Clinique Pasteur (Arrivée estimée 1...',
                ],
                [
                    'name' => 'Paul Koffi',
                    'sector' => 'Secteur Porto-Novo',
                    'statusBadgeText' => 'Disponible',
                    'subText' => 'Compte rendu rédigé (Cabinet Dr. Mens...',
                ]
            ],
            'validations' => [
                [
                    'title' => 'Frais carburant tournée',
                    'submittedBy' => 'Soumis par Jean Dupont • Aujourd\'hui',
                    'amountEur' => '65,50 €',
                    'amountLocal' => '42 900 FCFA',
                    'receipt' => 'Justificatif TotalEnergies Lomé',
                ]
            ]
        ]);
    }

    public function teamPlanning(Request $request)
    {
        $sectorFilter = $request->query('sector', 'Tous les secteurs');
        $delegateFilter = $request->query('delegate', 'Tous');
        $temporality = $request->query('temporality', 'Jour');
        $dateStr = $request->query('date', date('Y-m-d'));

        $sectors = [
            'Tous les secteurs',
            'Lomé Centre',
            'Lomé Ouest',
            'Tsévié',
            'Maritime',
            'Kara',
            'Savanes',
        ];

        $delegates = [
            [
                'id' => 'del-all',
                'name' => 'Tous',
                'avatar' => 'ALL',
                'role' => 'Équipe au complet',
                'sector' => 'Tous les secteurs',
                'coverage' => '85%',
                'status' => 'Validée',
                'planned_visits' => '24/28',
            ],
            [
                'id' => 'del-1',
                'name' => 'Sophie Lawson',
                'avatar' => 'SL',
                'role' => 'Lomé Centre (Principal) & Tsévié (Temp)',
                'sector' => 'Lomé Centre',
                'coverage' => '95%',
                'status' => 'Validée',
                'planned_visits' => '5/5 prévues',
            ],
            [
                'id' => 'del-2',
                'name' => 'Dr. Ousmane Sow',
                'avatar' => 'OS',
                'role' => 'Lomé Ouest',
                'sector' => 'Dakar Plateau',
                'coverage' => '92%',
                'status' => 'Validée',
                'planned_visits' => '4/4 prévues',
            ],
            [
                'id' => 'del-3',
                'name' => 'Fatou Ndiaye',
                'avatar' => 'FN',
                'role' => 'Maritime',
                'sector' => 'Fann & Point E',
                'coverage' => '88%',
                'status' => 'Validée',
                'planned_visits' => '3/4 prévues',
            ],
            [
                'id' => 'del-4',
                'name' => 'Mamadou Kane',
                'avatar' => 'MK',
                'role' => 'Kara',
                'sector' => 'Grand Yoff',
                'coverage' => '75%',
                'status' => 'En attente',
                'planned_visits' => '2/5 prévues',
            ],
            [
                'id' => 'del-5',
                'name' => 'Jean Dupont',
                'avatar' => 'JD',
                'role' => 'Savanes',
                'sector' => 'Dakar Nord',
                'coverage' => '58%',
                'status' => 'En attente',
                'planned_visits' => '3/5 prévues',
            ],
        ];

        // Tenter d'enrichir avec les vrais utilisateurs de la base de données s'ils existent
        try {
            $dbUsers = User::whereHas('roles', function($q) {
                $q->whereIn('name', ['delegue', 'delegate', 'medical_rep']);
            })->orWhere('status', 1)->take(10)->get();

            if ($dbUsers->isNotEmpty()) {
                // Enrichir la liste
                $mappedUsers = [];
                $mappedUsers[] = $delegates[0]; // 'Tous'
                foreach ($dbUsers as $index => $u) {
                    $initials = strtoupper(substr($u->name, 0, 2));
                    $mappedUsers[] = [
                        'id' => (string) $u->id,
                        'name' => $u->name,
                        'avatar' => $initials,
                        'role' => $u->email ?? 'Délégué Médical',
                        'sector' => $u->sector ?? ($sectors[($index % (count($sectors) - 1)) + 1]),
                        'coverage' => (75 + ($index * 5)) . '%',
                        'status' => $index % 2 == 0 ? 'Validée' : 'En attente',
                        'planned_visits' => (3 + ($index % 3)) . '/4 prévues',
                    ];
                }
                $delegates = $mappedUsers;
            }
        } catch (\Throwable $e) {
            // Utiliser la liste statique riche si DB non configurée
        }

        return response()->json([
            'status' => 'success',
            'date' => $dateStr,
            'temporality' => $temporality,
            'selected_sector' => $sectorFilter,
            'selected_delegate' => $delegateFilter,
            'sectors' => $sectors,
            'delegates' => $delegates,
            'summary' => [
                'total_delegates' => count($delegates) - 1,
                'validated_plannings' => 3,
                'pending_plannings' => 2,
                'global_coverage' => '84%',
            ]
        ]);
    }
}

