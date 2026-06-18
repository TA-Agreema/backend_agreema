<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Contract;
use App\Models\ContractTermination;

// Schedule untuk kontrak aktif
Schedule::command('contracts:activate')->everyMinute();
// Schedule untuk kontrak yang akan kedaluwarsa
Schedule::command('contracts:notify-expiring')->everyMinute();
// Schedule untuk kontrak yang dihentikan
Schedule::command('contracts:terminate')->everyMinute();
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

// Cek status kontrak setiap hari (berjalan otomatis di background)
Schedule::call(function () {
    $terminations = ContractTermination::whereHas('contract', function ($query) {
        $query->where('status', 'active');
    })->whereDate('effective_date', '<=', now())->get();

    foreach ($terminations as $termination) {
        $termination->contract->update(['status' => 'terminated']);
    }

    Contract::where('status', 'active')
        ->whereDoesntHave('termination')
        ->whereNotNull('end_date')
        ->whereDate('end_date', '<=', now())
        ->update(['status' => 'expired']);
})->everyMinute();
