<?php
$u = App\Models\User::where('email', 'admin@crm.local')->first();
auth('sanctum')->setUser($u);
dump($u->hasAnyRole(['superadmin', 'admin']));
