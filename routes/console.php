<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Contract;
use App\Models\ContractTermination;

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
