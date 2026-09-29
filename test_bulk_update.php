<?php
$a = App\Models\Account::first();
$user = App\Models\User::where("email", "admin@crm.local")->first();
$token = $user->createToken("test")->plainTextToken;

$request = Illuminate\Http\Request::create("/api/v1/accounts/" . $a->id, "PUT", [
    'user_id' => $user->id,
    'delegue_id' => $user->id
]);
$request->headers->set("Authorization", "Bearer " . $token);
$request->headers->set("Accept", "application/json");

$response = app()->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
dump($response->getStatusCode());
dump($response->getContent());
