<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Run a mail send without letting a broken SMTP server turn the request into a 500.
 * Failures are logged (never with credentials) and reported back as false.
 */
final class SafeMail
{
    public static function attempt(callable $send, string $context = 'mail'): bool
    {
        try {
            $send();

            return true;
        } catch (Throwable $e) {
            Log::error("neaby mail failed: {$context}", [
                'error' => $e->getMessage(),
                'class' => $e::class,
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'scheme' => config('mail.mailers.smtp.scheme'),
            ]);

            return false;
        }
    }

    public static function friendly(): string
    {
        return "We couldn't send the email right now. Please try again in a few minutes.";
    }
}
