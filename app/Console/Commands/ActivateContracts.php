<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ActivateContracts extends Command
{
    protected $signature = 'contracts:activate';
    protected $description = 'Aktifkan kontrak yang sudah melewati start_date';

    public function handle(): void
    {
        $contracts = Contract::where('status', 'signed')
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', Carbon::today())
            ->get();

        foreach ($contracts as $contract) {
            $contract->update(['status' => 'active']);

            $endDate = $contract->end_date
                ? $contract->end_date->locale('id')->isoFormat('D MMMM YYYY')
                : '(belum ditentukan)';

            Notification::create([
                'user_id' => $contract->created_by,
                'contract_id' => $contract->id,
                'type' => 'contract_activated',
                'message' => "Kontrak \"{$contract->title}\" kini telah aktif sampai pada tanggal {$endDate}.",
                'is_read' => false,
            ]);

            // Notifikasi ke internal signer (manager)
            foreach ($contract->signers as $signer) {
                if ($signer->signer_type === 'internal' && $signer->user_id && $signer->user_id !== $contract->created_by) {
                    Notification::create([
                        'user_id' => $signer->user_id,
                        'contract_id' => $contract->id,
                        'type' => 'contract_activated',
                        'message' => "Kontrak \"{$contract->title}\" kini telah aktif sampai pada tanggal {$endDate}.",
                        'is_read' => false,
                    ]);
                }
            }

            $this->line("Activated: {$contract->title}");
        }
    }
}
