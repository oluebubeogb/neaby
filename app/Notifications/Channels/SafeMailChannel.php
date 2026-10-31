<?php

namespace App\Notifications\Channels;

use App\Support\SafeMail;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;

/** Mail channel for in-app notices: an SMTP outage never breaks the action that triggered the notice. */
class SafeMailChannel extends MailChannel
{
    public function send($notifiable, Notification $notification)
    {
        $sent = null;
        SafeMail::attempt(function () use (&$sent, $notifiable, $notification) {
            $sent = parent::send($notifiable, $notification);
        }, 'notice '.class_basename($notification));

        return $sent;
    }
}
