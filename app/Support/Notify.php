<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\NeabyNotice;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Notifications must never break a payment or order flow (e.g. bad SMTP). */
class Notify
{
    public static function send(?User $user, NeabyNotice $notice): void
    {
        if (! $user) {
            return;
        }
        try {
            $user->notify($notice);
        } catch (Throwable $e) {
            Log::warning('Notification failed', ['user' => $user->id, 'title' => $notice->title, 'error' => $e->getMessage()]);
            if ($notice->mail) {
                try {
                    $notice->mail = false;
                    $user->notify($notice);
                } catch (Throwable) {
                }
            }
        }
    }
}
