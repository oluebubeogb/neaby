<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['current_period_start' => 'datetime', 'current_period_end' => 'datetime', 'grace_ends_at' => 'datetime', 'cancelled_at' => 'datetime', 'cancel_at_period_end' => 'boolean'];
    }

    public function plan()
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payments()
    {
        return $this->hasMany(MembershipPayment::class);
    }

    /** Usable right now (active or cancelled-but-still-paid-up, and not past the period end). */
    public function isCurrent(): bool
    {
        return in_array($this->status, ['active', 'cancelled'], true)
            && $this->current_period_end && $this->current_period_end->isFuture();
    }
}
