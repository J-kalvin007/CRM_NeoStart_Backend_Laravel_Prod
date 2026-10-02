<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contact;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\UpdateContactRequest;

$contact = Contact::latest()->first();

$data = [
    'nom' => 'Test',
    'prenom' => 'Testing',
    'telephone' => '12345678',
    'potentiel' => 'moyen',
    'account_id' => App\Models\Account::first()->id,
    'meta' => [
        'is_kol' => true,
        'rpps' => '123123'
    ]
];

// Let's mimic what ContactController@update does
$request = new UpdateContactRequest();
$request->merge($data);

// Bypass validation for the test, just see if model can be updated:
$contact->update($data);
echo json_encode($contact->fresh()->toArray(), JSON_PRETTY_PRINT);

