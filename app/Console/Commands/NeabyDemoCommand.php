<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\NeabyDemoSeeder;
use Illuminate\Console\Command;

/**
 * NEABY DEMO DATA — delete this file together with
 * database/seeders/NeabyDemoSeeder.php once neaby is in real use.
 *
 *   php artisan neaby:demo seed            # add the demo cast (idempotent)
 *   php artisan neaby:demo seed --fresh    # wipe + rebuild
 *   php artisan neaby:demo wipe            # remove only demo users and everything they own
 *   php artisan neaby:demo status
 *   php artisan neaby:demo boot            # used by docker-entrypoint: reads NEABY_SEED_DEMO
 */
class NeabyDemoCommand extends Command
{
    protected $signature = 'neaby:demo {action=status : seed|wipe|status|boot} {--fresh : wipe first when seeding} {--force : skip confirmation}';

    protected $description = 'Seed or wipe the neaby demo cast (neaby1@beuden.com.ng …, password = email). Never calls Listing AI.';

    public function handle(): int
    {
        $action = $this->argument('action');
        if ($action === 'boot') {
            $flag = strtolower(trim((string) (getenv('NEABY_SEED_DEMO') ?: env('NEABY_SEED_DEMO', 'false'))));
            $action = match ($flag) {
                'true', '1', 'yes', 'on', 'seed' => 'seed',
                'wipe', 'remove', 'off-and-wipe' => 'wipe',
                default => 'none',
            };
            if ($action === 'none') {
                $this->line('[neaby] demo data: NEABY_SEED_DEMO is off — leaving data as it is.');

                return self::SUCCESS;
            }
            $this->input->setOption('force', true);
        }

        return match ($action) {
            'seed' => $this->seed(),
            'wipe' => $this->wipe(),
            'status' => $this->status(),
            default => $this->invalid($action),
        };
    }

    private function seed(): int
    {
        if ($this->option('fresh')) {
            $r = NeabyDemoSeeder::wipe();
            $this->info("Wiped {$r['users']} demo user(s).");
        }
        $this->call('db:seed', ['--class' => NeabyDemoSeeder::class, '--force' => true]);
        $this->status();

        return self::SUCCESS;
    }

    private function wipe(): int
    {
        if (! $this->option('force') && ! $this->confirm('Delete ALL demo users (neabyN@beuden.com.ng, *@neaby.test) and everything they own?', true)) {
            return self::FAILURE;
        }
        $r = NeabyDemoSeeder::wipe();
        $this->info("Demo data removed: {$r['users']} user(s), {$r['images']} image(s). Real users are untouched.");

        return self::SUCCESS;
    }

    private function status(): int
    {
        $count = User::query()->where('email', 'like', 'neaby%@'.NeabyDemoSeeder::EMAIL_DOMAIN)->count();
        $this->line($count
            ? "Demo data is ON: {$count} demo user(s). Login e.g. neaby1@beuden.com.ng / neaby1@beuden.com.ng"
            : 'Demo data is OFF (no demo users).');

        return self::SUCCESS;
    }

    private function invalid(string $a): int
    {
        $this->error("Unknown action “{$a}”. Use seed, wipe, status or boot.");

        return self::INVALID;
    }
}
