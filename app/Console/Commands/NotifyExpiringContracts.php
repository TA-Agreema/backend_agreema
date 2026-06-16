<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyExpiringContracts extends Command
{
    protected $signature   = 'contracts:notify-expiring';
    protected $description = 'Kirim notifikasi pengingat kontrak yang akan kedaluwarsa';

    public function handle(): void
    {
        $reminderDays = [30, 15, 7, 1];

        foreach ($reminderDays as $days) {
            $targetDate = Carbon::today()->addDays($days);

            $contracts = Contract::where('status', 'active')
                ->whereNotNull('end_date')
                ->whereDate('end_date', $targetDate)
                ->with('signers.user')
                ->get();

            foreach ($contracts as $contract) {
                $endDate = $contract->end_date->locale('id')->isoFormat('D MMMM YYYY');
                $message = "{$contract->title} akan berakhir dalam {$days} hari ({$endDate}).";

                // Notifikasi ke HRD
                // Cek apakah notifikasi hari ini sudah pernah dikirim (hindari duplikasi)
                $alreadySent = Notification::where('user_id', $contract->created_by)
                    ->where('contract_id', $contract->id)
                    ->where('type', 'contract_expiring')
                    ->whereRaw("message LIKE ?", ["%dalam {$days} hari%"])
                    ->whereDate('created_at', Carbon::today())
                    ->exists();

                if (!$alreadySent) {
                    Notification::create([
                        'user_id'     => $contract->created_by,
                        'contract_id' => $contract->id,
                        'type'        => 'contract_expiring',
                        'message'     => $message,
                        'is_read'     => false,
                    ]);

                    // Notifikasi ke manager (internal signer)
                    foreach ($contract->signers as $signer) {
                        if ($signer->signer_type === 'internal' && $signer->user_id) {
                            Notification::create([
                                'user_id'     => $signer->user_id,
                                'contract_id' => $contract->id,
                                'type'        => 'contract_expiring',
                                'message'     => $message,
                                'is_read'     => false,
                            ]);
                        }
                    }

                    $this->line("Notified [{$days}d]: {$contract->title}");
                }
            }
        }

        $this->line("Selesai.");
    }
}
