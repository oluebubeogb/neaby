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
        ]);

        try {
            Notification::route('mail', $this->argument('to'))->notifyNow(
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
