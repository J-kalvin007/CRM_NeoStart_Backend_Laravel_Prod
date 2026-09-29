<?php
$a = App\Models\Account::first();
$a->update(['delegue_id' => 1]);
dump($a->fresh()->delegue_id);
