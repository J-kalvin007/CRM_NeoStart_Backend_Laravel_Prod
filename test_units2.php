<?php
$u = App\Models\User::where('email', 'admin@crm.local')->first();
$req = Illuminate\Http\Request::create('/api/v1/hierarchy/units', 'GET');
$req->headers->set('Authorization', 'Bearer ' . $u->createToken('test')->plainTextToken);
$req->headers->set('Accept', 'application/json');
$res = app()->make(Illuminate\Contracts\Http\Kernel::class)->handle($req);
$content = json_decode($res->getContent(), true);
dump(count($content['data']['units'] ?? []));
dump($content['data']['units'][0]['id'] ?? null);
