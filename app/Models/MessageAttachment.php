<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MessageAttachment extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['path'];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}
