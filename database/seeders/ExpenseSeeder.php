<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Expense;
use App\Models\User;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        $expenses = [
            [
                'title' => 'Frais de Carburant Tournée Maritime',
                'type' => 'travel',
                'amount' => 25000.00,
                'currency' => 'XOF',
                'expense_date' => now()->subDays(3)->toDateString(),
                'status' => 'submitted',
                'receipt_file_path' => 'expenses/receipt_carburant_001.jpg'
            ],
            [
                'title' => 'Déjeuner de Travail Dr Lawson',
                'type' => 'meal',
                'amount' => 12500.00,
                'currency' => 'XOF',
                'expense_date' => now()->subDays(2)->toDateString(),
                'status' => 'approved',
                'receipt_file_path' => 'expenses/receipt_repas_002.jpg'
            ]
        ];

        if ($user && $user->subsidiary_id) {
            foreach ($expenses as $exp) {
                Expense::create(array_merge($exp, [
                    'user_id' => $user->id,
                    'subsidiary_id' => $user->subsidiary_id
                ]));
            }
        }
    }
}
