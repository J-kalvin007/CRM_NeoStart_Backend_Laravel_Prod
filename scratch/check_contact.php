<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contact;

$id = '01a0c1ff-6fd4-7068-9f36-aca0c01d6a3d';
$contact = Contact::withTrashed()->find($id);

if ($contact) {
    echo json_encode($contact->toArray(), JSON_PRETTY_PRINT);
} else {
    echo "Contact not found even in trashed.\n";
}
