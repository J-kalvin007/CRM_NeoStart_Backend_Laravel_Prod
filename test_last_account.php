<?php
$a = App\Models\Account::orderBy("updated_at", "desc")->first();
dump($a->id, $a->user_id, $a->delegue_id, $a->delegue?->name, $a->account_type_id);
