<?php

namespace App\Console\Commands;

use App\Notifications\NeabyNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Sends one branded test mail and prints the resolved (non-secret) mail config + any transport error. */
class NeabyMailTestCommand extends Command
{
    protected $signature = 'neaby:mail-test {to : Recipient address}';

    protected $description = 'Send a test email through the configured mailer and show the exact error if it fails';

    public function handle(): int
    {
        $smtp = config('mail.mailers.smtp');
        $this->table(['setting', 'value'], [
            ['mailer', config('mail.default')],
            ['host', $smtp['host'] ?? ''],
            ['port', $smtp['port'] ?? ''],
            ['scheme (normalised)', var_export($smtp['scheme'] ?? null, true)],
            ['username', $smtp['username'] ?? ''],
            ['password', empty($smtp['password']) ? '(empty)' : '(set, hidden)'],
            ['from', config('mail.from.address').' / '.config('mail.from.name')],
            ['timeout', $smtp['timeout'] ?? ''],
            ['ipv4 first', ($smtp['prefer_ipv4'] ?? true) ? 'yes → '.(\App\Mail\NeabySmtpTransportFactory::ipv4For((string) ($smtp['host'] ?? '')) ?? '(no A record / is IP)') : 'no'],
        ]);

        // Raw reachability check (what Paddy's logs would show as ECONNREFUSED / ETIMEDOUT).
        if (config('mail.default') === 'smtp' && ! empty($smtp['host'])) {
            $ip = \App\Mail\NeabySmtpTransportFactory::ipv4For((string) $smtp['host']) ?? $smtp['host'];
            $t = microtime(true);
            $fp = @fsockopen(($smtp['scheme'] === 'smtps' ? 'ssl://' : '').$ip, (int) $smtp['port'], $errno, $errstr, 10);
            if ($fp) {
                stream_set_timeout($fp, 10);
                $greet = trim((string) fgets($fp, 512));
                fclose($fp);
                $this->line(sprintf('TCP %s:%s ok in %dms — greeting: %s', $ip, $smtp['port'], (microtime(true) - $t) * 1000, $greet ?: '(none)'));
            } else {
                $this->warn("TCP {$ip}:{$smtp['port']} failed: [{$errno}] {$errstr} — check host/port, firewall, or that the VPS allows outbound SMTP.");
            }
        }

        try {
            // Force the raw 'mail' channel. (AnonymousNotifiable::notifyNow() drops the
            // channel list, and NeabyNotice::via() skips mail for a notifiable
            // without ->email — so the old call reported "Sent" without sending.)
            Notification::sendNow(
                Notification::route('mail', $this->argument('to')),
                new NeabyNotice('Test email', 'If you can read this, neaby mail is working. ₦ sign check: ₦7,500.', url('/home'), 'info'),
                ['mail']
            );
        } catch (Throwable $e) {
            $this->error('Send failed: '.$e::class);
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sent. Check the inbox (and spam) for '.$this->argument('to').'.');

        return self::SUCCESS;
    }
}
