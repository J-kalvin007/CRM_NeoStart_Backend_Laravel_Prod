<?php
$users = App\Models\User::all();
foreach($users as $u) {
    echo $u->email . " - " . implode(",", $u->getRoleNames()->toArray()) . "\n";
}
