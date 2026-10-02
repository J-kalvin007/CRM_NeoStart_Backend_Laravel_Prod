<?php
$u = App\Models\User::where('email', 'admin@crm.local')->first();
$req = Illuminate\Http\Request::create('/api/v1/accounts', 'POST', [
    'nom' => 'Test Account',
    'account_type_id' => App\Models\AccountType::first()->id,
    'organizational_unit_id' => App\Models\OrganizationalUnit::first()->id,
]);
$req->headers->set('Authorization', 'Bearer ' . $u->createToken('test')->plainTextToken);
$req->headers->set('Accept', 'application/json');
$res = app()->make(Illuminate\Contracts\Http\Kernel::class)->handle($req);
echo "Status: " . $res->getStatusCode() . "\n";
echo $res->getContent();
