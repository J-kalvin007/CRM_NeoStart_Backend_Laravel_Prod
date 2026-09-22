<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Expense;
use App\Models\User;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        // Ne créer aucune dépense pré-soumise. Les dépenses seront uniquement créées par les délégués via l'application.
    }
}
