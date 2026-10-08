<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ids = ['01a11ad3-bb62-7299-a65f-d51724c38fbd', '01a11ad5-9b3a-72d8-9f7b-ccde2c27911e'];
foreach ($ids as $id) {
    $a = \App\Models\Activity::with(['contact.account', 'contacts.account', 'account', 'type', 'activityType', 'delegue', 'user'])->find($id);
    if (!$a) continue;
    $resource = new \App\Http\Resources\ActivityResource($a);
    echo "ID: {$a->id}\n";
    echo "  Resource JSON: " . json_encode($resource->resolve(), JSON_PRETTY_PRINT) . "\n";
    echo "--------------------------------------------------\n";
}
