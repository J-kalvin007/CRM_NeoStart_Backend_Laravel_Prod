<?php
$roles = Spatie\Permission\Models\Role::all();
foreach($roles as $role) {
    echo $role->name . " - guard: " . $role->guard_name . "\n";
}
