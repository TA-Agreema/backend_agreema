<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Aktifkan kontrak yang start_date sudah tercapai — jam 08:00
Schedule::command('contracts:activate')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
// Terminasi kontrak yang effective_date sudah tercapai — jam 08:00
Schedule::command('contracts:terminate')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
// Notifikasi kontrak kedaluwarsa (expired) — jam 08:00
Schedule::command('contracts:notify-expired')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
// Notifikasi pengingat kontrak akan berakhir (H-30, H-15, H-7, H-1) — jam 08:00
Schedule::command('contracts:notify-expiring')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
// Schedule untuk mengirim notifikasi kontrak yang mulai hari ini
Schedule::command('contracts:send-started-notifications')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('Gagal menjalankan contracts:send-started-notifications');
    });

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
