<?php
$admin = App\Models\User::where('email', 'admin@crm.local')->first();
$request = Illuminate\Http\Request::create("/api/v1/accounts", 'GET');
$request->headers->set('Accept', 'application/json');

Auth::login($admin);
$response = app()->handle($request);
dump($response->getStatusCode());
$content = json_decode($response->getContent(), true);
dump(count($content['data'] ?? []));
