<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic in-app notice (Inbox) with an optional branded email for the
 * important ones (orders, memberships, supplier requests).
 */
class NeabyNotice extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public string $kind = 'info', // order | membership | resale | sponsored | info
        public bool $mail = true,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($this->mail && config('neaby.mail_notifications', true) && ! empty($notifiable->email)) {
            $channels[] = \App\Notifications\Channels\SafeMailChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $kicker = match ($this->kind) {
            'order' => 'order update',
            'membership' => 'membership',
            'resale' => 'resale',
            'sponsored' => 'sponsored',
            default => 'notification',
        };

        $m = (new MailMessage)->subject($this->title.' · neaby')
            ->markdown('notifications::email', ['kicker' => $kicker])
            ->greeting($this->title)
            ->line('Hi '.(($notifiable->name ?? null) ?: ($notifiable->username ?? null) ?: 'there').' — '.$this->body);
        if ($this->url) {
            $m->action('Open neaby', $this->url);
        }

        return $m;
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'kind' => $this->kind];
    }
}
