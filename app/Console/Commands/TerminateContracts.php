<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TerminateContracts extends Command
{
    protected $signature   = 'contracts:terminate';
    protected $description = 'Hentikan kontrak yang sudah mencapai tanggal efektif terminasi';

    public function handle(): void
    {
        $contracts = Contract::where('status', 'terminating')
            ->whereHas('termination', function ($q) {
                $q->whereDate('effective_date', '<=', Carbon::today());
            })
            ->with('signers.user', 'termination')
            ->get();

        foreach ($contracts as $contract) {
            $contract->update(['status' => 'terminated']);

            $effectiveDate = $contract->termination->effective_date
                ->locale('id')->isoFormat('D MMMM YYYY');

            // Notifikasi ke HRD
            Notification::create([
                'user_id'     => $contract->created_by,
                'contract_id' => $contract->id,
                'type'        => 'contract_terminated',
                'message'     => "{$contract->title} resmi dihentikan per {$effectiveDate}.",
                'is_read'     => false,
            ]);

            // Notifikasi ke manager
            foreach ($contract->signers as $signer) {
                if ($signer->signer_type === 'internal' && $signer->user_id) {
                    Notification::create([
                        'user_id'     => $signer->user_id,
                        'contract_id' => $contract->id,
                        'type'        => 'contract_terminated',
                        'message'     => "{$contract->title} resmi dihentikan per {$effectiveDate}.",
                        'is_read'     => false,
                    ]);
                }
            }

            $this->line("Terminated: {$contract->title}");
        }
    }
}
