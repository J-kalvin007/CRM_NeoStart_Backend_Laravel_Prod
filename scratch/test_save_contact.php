<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contact;
use App\Models\ContactType;
use App\Models\Account;
use App\Models\User;

$type = ContactType::first();
$account = Account::first();
$user = User::first();

echo "Creating contact...\n";
$contact = Contact::create([
    'nom' => 'Test',
    'prenom' => 'Contact',
    'potentiel' => 'fort',
    'telephone' => '+228 90 00 00 00',
    'account_id' => $account?->id,
    'contact_type_id' => $type?->id,
    'user_id' => $user?->id,
    'meta' => [
        'is_kol' => true,
        'rpps' => '123456789'
    ]
]);

$contact->refresh();
echo "Contact potentiel: " . $contact->potentiel . "\n";
echo "Contact phone: " . $contact->telephone . "\n";
echo "Contact account: " . $contact->account_id . "\n";
echo "Contact type: " . $contact->contact_type_id . "\n";
echo "Contact meta: " . json_encode($contact->meta) . "\n";

echo "Updating contact...\n";
$contact->update([
    'potentiel' => 'faible',
    'telephone' => '+336000000',
    'meta' => [
        'is_kol' => false
    ]
]);

$contact->refresh();
echo "Contact potentiel updated: " . $contact->potentiel . "\n";
echo "Contact phone updated: " . $contact->telephone . "\n";
echo "Contact meta updated: " . json_encode($contact->meta) . "\n";

$contact->delete();
