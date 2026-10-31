<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Call extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['answered_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function callee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    public function signals(): HasMany
    {
        return $this->hasMany(CallSignal::class);
    }

    public function involves(User $u): bool
    {
        return $this->caller_id === $u->id || $this->callee_id === $u->id;
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['ringing', 'active'], true);
    }
}
