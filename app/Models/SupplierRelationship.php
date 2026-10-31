<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierRelationship extends Model
{
    protected $guarded = ['id'];

    public function reseller()
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    public function supplier()
    {
        return $this->belongsTo(User::class, 'original_supplier_id');
    }
}
