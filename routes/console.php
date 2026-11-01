<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Auto-pull level stok dari ERP HPY tiap jam (gate via setting stock_auto_sync)
Schedule::command('stock:sync-erp')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Auto-perbaiki mapping customer ERP yang nyangkut (nama HPY terhapus/diganti)
// supaya SO/DN/Payment tidak gagal diam-diam. Harian jam 03:00.
Schedule::command('customers:reconcile-erp')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->runInBackground();

// Backup DB otomatis ke Google Drive tiap hari jam 02:00, lalu bersihkan yang lama.
Schedule::command('backup:clean')->dailyAt('01:55')->withoutOverlapping();
Schedule::command('backup:run --only-db')->dailyAt('02:00')->withoutOverlapping();
