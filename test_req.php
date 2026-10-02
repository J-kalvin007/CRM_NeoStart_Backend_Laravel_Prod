<?php
$a = App\Models\Account::first();
$u = App\Models\User::first();
$admin = App\Models\User::where('email', 'admin@crm.local')->first();

$request = Illuminate\Http\Request::create("/api/v1/accounts/{$a->id}", 'PUT', [
    'delegue_id' => $u->id,
    'user_id' => $u->id
]);
$request->headers->set('Accept', 'application/json');

// Simulate acting as admin
$request->setUserResolver(function () use ($admin) {
    return $admin;
});
// Alternatively, since Sanctum is used, this might not be enough for a full app()->handle. 
// We can just call the controller method directly to bypass the routing middleware auth.
$controller = app()->make(App\Http\Controllers\Api\V1\AccountController::class);
$updateRequest = App\Http\Requests\UpdateAccountRequest::createFrom($request);
$updateRequest->setContainer(app());
// Actually, let's just mock auth and use app()->handle:
Auth::login($admin);
$response = app()->handle($request);
dump($response->getStatusCode());
dump($response->getContent());
