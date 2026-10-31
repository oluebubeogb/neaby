<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;

/** Prop shapes for messaging. */
class Chat
{
    public static function person(?User $u): ?array
    {
        if (! $u) {
            return null;
        }
        $store = $u->relationLoaded('store') ? $u->store : null;

        return [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
            'avatar' => Present::user($u)['avatar'] ?? null,
            'initials' => $u->initials(),
            'store' => $store ? ['name' => $store->name, 'slug' => $store->slug, 'accent' => $store->accent] : null,
        ];
    }

    public static function context(Conversation $c): ?array
    {
        if ($c->context_type === 'product' && $c->context_id) {
            $p = Product::query()->with('cover')->find($c->context_id);

            return $p ? ['type' => 'product', 'title' => $p->title, 'subtitle' => Present::money($p->price_minor, $p->currency), 'url' => route('products.show', $p), 'image' => Present::imageUrl($p->cover?->path)] : null;
        }
        if ($c->context_type === 'store' && $c->context_id) {
            $s = Store::query()->find($c->context_id);

            return $s ? ['type' => 'store', 'title' => $s->name, 'subtitle' => $s->tagline, 'url' => route('stores.show', $s), 'image' => Present::imageUrl($s->logo_path)] : null;
        }

        return null;
    }

    public static function preview(?Message $m): ?string
    {
        if (! $m) {
            return null;
        }

        return match ($m->type) {
            'image' => '📷 Photo',
            'voice' => '🎙 Voice note',
            'call' => '📞 '.($m->body ?: 'Voice call'),
            default => mb_strimwidth((string) $m->body, 0, 80, '…'),
        };
    }

    public static function thread(Conversation $c, User $me): array
    {
        $other = $c->other($me);

        return [
            'id' => $c->id,
            'other' => self::person($other),
            'last' => $c->latestMessage ? [
                'preview' => self::preview($c->latestMessage),
                'mine' => $c->latestMessage->sender_id === $me->id,
                'at' => $c->latestMessage->created_at?->toIso8601String(),
                'ago' => $c->latestMessage->created_at?->diffForHumans(short: true),
            ] : null,
            'unread' => $c->isUnreadFor($me),
        ];
    }

    public static function message(Message $m, User $me): array
    {
        return [
            'id' => $m->id,
            'mine' => $m->sender_id === $me->id,
            'type' => $m->type,
            'body' => $m->body,
            'media' => $m->media_path ? route('messages.media', $m) : null,
            'meta' => $m->meta ?? (object) [],
            'at' => $m->created_at?->toIso8601String(),
        ];
    }
}
