<?php

use App\Services\Commerce\Inventory;
use App\Services\Growth\SellerQuality;
use App\Services\Growth\SponsoredService;
use App\Services\Membership\MembershipService;
use App\Services\Resale\ResaleService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/*
| Neaby housekeeping. Run the scheduler in production
| (`php artisan schedule:work` as a separate Coolify process, or cron
| `* * * * * php artisan schedule:run`). All jobs are idempotent.
*/
Artisan::command('neaby:reservations:release', function (Inventory $inventory) {
    $this->info('Released '.$inventory->releaseExpired().' expired checkout reservation(s).');
})->purpose('Release expired stock holds from abandoned checkouts');

Artisan::command('neaby:memberships:sweep', function (MembershipService $memberships, ResaleService $resale, SponsoredService $sponsored) {
    $r = $memberships->sweep();
    $this->info('Memberships: '.json_encode($r));
    $this->info('Expired supplier requests: '.$resale->expireStale());
    $this->info('Ended sponsored placements: '.$sponsored->endExpired());
})->purpose('Expire lapsed memberships (accepted commitments untouched), stale requests and sponsored slots');

Artisan::command('neaby:quality:recompute', function (SellerQuality $quality) {
    $this->info('Recomputed '.$quality->recomputeAll().' store quality score(s).');
})->purpose('Recompute seller quality scores used by For You ranking');

Schedule::command('neaby:reservations:release')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('neaby:memberships:sweep')->hourly()->withoutOverlapping();
Schedule::command('neaby:quality:recompute')->dailyAt('03:15')->withoutOverlapping();