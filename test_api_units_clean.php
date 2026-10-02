<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$u = App\Models\User::where('email', 'admin@crm.local')->first();
if (!$u) {
    echo "User not found\n";
    exit;
}

$req = Illuminate\Http\Request::create("/api/v1/hierarchy/units", "GET");
$req->headers->set("Authorization", "Bearer " . $u->createToken("test")->plainTextToken);
$req->headers->set("Accept", "application/json");

$res = $kernel->handle($req);

echo "Status: " . $res->getStatusCode() . "\n";
$content = json_decode($res->getContent(), true);

if (isset($content['data']['units'])) {
    echo "Units count: " . count($content['data']['units']) . "\n";
    if (count($content['data']['units']) > 0) {
        echo "First unit ID: " . $content['data']['units'][0]['id'] . "\n";
        echo "First unit Name: " . $content['data']['units'][0]['name'] . "\n";
    }
} else {
    echo "Unexpected response:\n";
    print_r($content);
}
