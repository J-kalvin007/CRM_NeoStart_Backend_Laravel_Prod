<?php

namespace App\Models;

use App\Models\Traits\HasActivityLogs;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationalUnit extends Model
{
<<<<<<< HEAD
    use HasFactory, HasUuids;
    use HasActivityLogs;
=======
    use HasFactory, HasUuids, HasActivityLogs;
>>>>>>> 3f45c15dfd167361f9f9e2f581bcc5692bacd07a

    protected $fillable = [
        'name',
        'type',
        'parent_id',
<<<<<<< HEAD
=======
        'manager_id',
>>>>>>> 3f45c15dfd167361f9f9e2f581bcc5692bacd07a
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function parent()
    {
<<<<<<< HEAD
        return $this->belongsTo(self::class, 'parent_id');
=======
        return $this->belongsTo(OrganizationalUnit::class, 'parent_id');
>>>>>>> 3f45c15dfd167361f9f9e2f581bcc5692bacd07a
    }

    public function children()
    {
<<<<<<< HEAD
        return $this->hasMany(self::class, 'parent_id');
=======
        return $this->hasMany(OrganizationalUnit::class, 'parent_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_scopes');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
>>>>>>> 3f45c15dfd167361f9f9e2f581bcc5692bacd07a
    }
}
