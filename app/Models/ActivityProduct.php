<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ActivityProduct extends Pivot
{
    use HasUuids;

    protected $table = 'activity_product';

    public $incrementing = false;
    protected $keyType = 'string';
}
