<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['user_one_read_at' => 'datetime', 'user_two_read_at' => 'datetime', 'last_message_at' => 'datetime'];
    }

    public static function between(User $a, User $b): self
    {
        [$one, $two] = $a->id < $b->id ? [$a->id, $b->id] : [$b->id, $a->id];

        return self::query()->firstOrCreate(['user_one_id' => $one, 'user_two_id' => $two]);
    }

    public function scopeFor(Builder $q, User $u): Builder
    {
        return $q->where(fn ($w) => $w->where('user_one_id', $u->id)->orWhere('user_two_id', $u->id));
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function involves(User $u): bool
    {
        return $this->user_one_id === $u->id || $this->user_two_id === $u->id;
    }

    public function otherId(User $u): int
    {
        return $this->user_one_id === $u->id ? $this->user_two_id : $this->user_one_id;
    }

    public function other(User $u): ?User
    {
        return $this->user_one_id === $u->id ? $this->userTwo : $this->userOne;
    }

    public function readColumn(User $u): string
    {
        return $this->user_one_id === $u->id ? 'user_one_read_at' : 'user_two_read_at';
    }

    public function isUnreadFor(User $u): bool
    {
        $read = $this->{$this->readColumn($u)};

        return $this->last_message_at && (! $read || $read->lt($this->last_message_at))
            && $this->latestMessage?->sender_id !== $u->id;
    }

    public static function unreadCount(User $u): int
    {
        return self::query()->for($u)->whereNotNull('last_message_at')
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('user_one_id', $u->id)->where(fn ($x) => $x->whereNull('user_one_read_at')->orWhereColumn('user_one_read_at', '<', 'last_message_at')))
                ->orWhere(fn ($w) => $w->where('user_two_id', $u->id)->where(fn ($x) => $x->whereNull('user_two_read_at')->orWhereColumn('user_two_read_at', '<', 'last_message_at'))))
            ->whereHas('messages', fn ($m) => $m->where('sender_id', '!=', $u->id))
            ->count();
    }
}
