<?php
$u=App\Models\User::where("email","admin@crm.local")->first(); 
$req=Illuminate\Http\Request::create("/api/v1/accounts","GET"); 
$req->headers->set("Authorization","Bearer ".$u->createToken("test")->plainTextToken); 
$req->headers->set("Accept","application/json"); 
$res=app()->make(Illuminate\Contracts\Http\Kernel::class)->handle($req); 
$data=json_decode($res->getContent(),true)["data"]; 
foreach($data as $a) { 
    if ($a["id"] == "01a0e454-07c3-70bf-acae-21e66b17c7fd") { 
        dump($a["delegue"], $a["user"], $a["type"]); 
    } 
}
