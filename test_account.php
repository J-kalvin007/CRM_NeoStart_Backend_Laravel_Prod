<?php
$a = App\Models\Account::factory()->raw(['organizational_unit_id' => App\Models\OrganizationalUnit::first()->id]);
dump($a);
