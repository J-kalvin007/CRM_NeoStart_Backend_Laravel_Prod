<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Objection extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id',
        'title',
        'recommended_argument',
        'supporting_document_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supportingDocument()
    {
        return $this->belongsTo(Document::class, 'supporting_document_id');
    }
}
