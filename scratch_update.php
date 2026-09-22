<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = \Illuminate\Http\Request::create('/api/v1/contacts/01a0c1ff-70ce-7223-af1e-ff47cf75dfee', 'PUT', [
    'nom' => 'NomMedecin4_3_UPDATED',
    'prenom' => 'Prenom_3',
    'telephone' => '+22890000000',
    'potentiel' => 'fort',
    'meta' => [
        'is_kol' => true,
        'rpps' => '12345'
    ]
]);
$request->headers->set('Accept', 'application/json');
// Mock authentication
$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user);

$response = app()->handle($request);
echo $response->getContent();
