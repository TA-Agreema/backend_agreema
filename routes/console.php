<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Schedule untuk kontrak aktif
Schedule::command('contracts:activate')->Hourly();
// Schedule untuk kontrak yang akan kedaluwarsa
Schedule::command('contracts:notify-expiring')->Hourly();
// Schedule untuk notif kontrak expired
Schedule::command('contracts:notify-expired')->daily();
// Schedule untuk kontrak yang dihentikan
Schedule::command('contracts:terminate')->Hourly();
// Schedule untuk mengirim notifikasi kontrak yang mulai hari ini
Schedule::command('contracts:send-started-notifications')
    ->dailyAt('07:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('Gagal menjalankan contracts:send-started-notifications');
    });

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');