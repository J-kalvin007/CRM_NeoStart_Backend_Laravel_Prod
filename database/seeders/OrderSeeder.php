<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $account = \App\Models\Account::firstOrCreate(
            ['nom' => 'Pharmacie de la Mairie'],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'user_id' => \App\Models\User::first()->id ?? null
            ]
        );

        $orders = [
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'account_id' => $account->id,
                'user_id' => \App\Models\User::first()->id ?? null,
                'order_number' => 'CMD-2026-8812',
                'total_amount' => 1250000,
                'status' => 'invoiced',
                'order_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'account_id' => $account->id,
                'user_id' => \App\Models\User::first()->id ?? null,
                'order_number' => 'CMD-2026-8815',
                'total_amount' => 8400000,
                'status' => 'pending',
                'order_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'account_id' => $account->id,
                'user_id' => \App\Models\User::first()->id ?? null,
                'order_number' => 'CMD-2026-8819',
                'total_amount' => 620000,
                'status' => 'confirmed',
                'order_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        foreach ($orders as $orderData) {
            \Illuminate\Support\Facades\DB::table('orders')->updateOrInsert(
                ['order_number' => $orderData['order_number']],
                $orderData
            );
        }
    }
}
