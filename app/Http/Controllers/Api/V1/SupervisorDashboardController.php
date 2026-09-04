<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
}
