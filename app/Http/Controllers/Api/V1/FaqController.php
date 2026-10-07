<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FaqCategory;
use App\Models\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index()
    {
        $categories = FaqCategory::where('is_active', true)
            ->with(['faqs' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order', 'asc');
            }])
            ->orderBy('sort_order', 'asc')
            ->get();

        // If categories table is empty, return default structured FAQs
        if ($categories->isEmpty()) {
            return response()->json([
                'categories' => [
                    [
                        'id' => 'cat-compte',
                        'name' => 'Compte',
                        'sort_order' => 1,
                        'faqs' => [
                            [
                                'id' => 'faq-1',
                                'question' => 'Comment modifier mes informations personnelles ?',
                                'answer' => 'Rendez-vous dans la rubrique "Mon Profil" depuis le menu principal pour mettre à jour votre nom, email ou numéro de téléphone.',
                            ],
                            [
                                'id' => 'faq-2',
                                'question' => 'Comment modifier mon mot de passe ?',
                                'answer' => 'Rendez-vous dans la rubrique "Paramètres" -> "Sécurité" -> "Modifier le mot de passe".',
                            ],
                            [
                                'id' => 'faq-3',
                                'question' => 'Que faire si je rencontre un problème avec mon compte ?',
                                'answer' => 'Vous pouvez contacter l\'équipe support via "Aide & Support" -> "Contacter le support" ou par email.',
                            ],
                        ],
                    ],
                    [
                        'id' => 'cat-app',
                        'name' => 'Utilisation de l\'application',
                        'sort_order' => 2,
                        'faqs' => [
                            [
                                'id' => 'faq-4',
                                'question' => 'Comment planifier une visite terrain ?',
                                'answer' => 'Allez dans l\'onglet Agenda / Planning, puis cliquez sur "Planifier une visite" et sélectionnez un établissement et un prescripteur.',
                            ],
                            [
                                'id' => 'faq-5',
                                'question' => 'Comment l\'application fonctionne-t-elle hors-ligne ?',
                                'answer' => 'Toutes vos actions saisies sans connexion réseau sont enregistrées localement et synchronisées automatiquement dès le retour du réseau.',
                            ],
                            [
                                'id' => 'faq-6',
                                'question' => 'Où trouver les documents et brochures produits ?',
                                'answer' => 'Consultez la "Bibliothèque documentaire" depuis le menu latéral pour accéder aux fiches techniques et e-detailings.',
                            ],
                        ],
                    ],
                    [
                        'id' => 'cat-products',
                        'name' => 'Produits / Services',
                        'sort_order' => 3,
                        'faqs' => [
                            [
                                'id' => 'faq-7',
                                'question' => 'Comment consulter le catalogue de produits ?',
                                'answer' => 'Accédez à la rubrique "Produits" pour filtrer par classe thérapeutique, catégorie ou laboratoire.',
                            ],
                            [
                                'id' => 'faq-8',
                                'question' => 'Comment enregistrer la remise d\'échantillons médicaux ?',
                                'answer' => 'Lors de la réalisation du compte-rendu de visite, complétez la section "Échantillons remis" en précisant les quantités.',
                            ],
                        ],
                    ],
                    [
                        'id' => 'cat-payment',
                        'name' => 'Paiement / Frais',
                        'sort_order' => 4,
                        'faqs' => [
                            [
                                'id' => 'faq-9',
                                'question' => 'Comment déclarer une note de frais ?',
                                'answer' => 'Accédez à la section "Frais" et soumettez votre note avec la photo du justificatif pour validation par votre superviseur.',
                            ],
                        ],
                    ],
                ]
            ]);
        }

        return response()->json(['categories' => $categories]);
    }

    public function categories()
    {
        return response()->json(FaqCategory::where('is_active', true)->orderBy('sort_order')->get());
    }
}
