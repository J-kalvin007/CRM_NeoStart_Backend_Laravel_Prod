<?php
$user = App\Models\User::where("email", "admin@crm.local")->first();
$token = $user->createToken("test")->plainTextToken;

$request = Illuminate\Http\Request::create("/api/v1/accounts", "GET");
$request->headers->set("Authorization", "Bearer " . $token);
$request->headers->set("Accept", "application/json");

$response = app()->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
dump($response->getStatusCode());
$content = json_decode($response->getContent(), true);
if (isset($content['data'])) {
    dump("Count accounts returned: " . count($content['data']));
} else {
    dump("No data key in response. " . substr($response->getContent(), 0, 500));
}
