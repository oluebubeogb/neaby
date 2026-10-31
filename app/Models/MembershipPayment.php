<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPayment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['period_start' => 'datetime', 'period_end' => 'datetime'];
    }
}
