<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Mail-only: the signed link that finalises an account deletion request. */
class ConfirmAccountDeletion extends Notification
{
    public function __construct(public string $url, public int $minutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm account deletion · neaby')
            ->markdown('notifications::email', ['kicker' => 'security'])
            ->error()
            ->greeting('Hi '.($notifiable->name ?: $notifiable->username).',')
            ->line('We received a request to permanently delete your neaby account, your store and listings.')
            ->action('Confirm deletion', $this->url)
            ->line("This link expires in {$this->minutes} minutes and only works while you are signed in.")
            ->line('If this wasn\'t you, ignore this email and change your password — nothing will be deleted.');
    }
}
