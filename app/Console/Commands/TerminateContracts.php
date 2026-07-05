<?php

namespace App\Console\Commands;

use App\Mail\ContractTerminatedMail;
use App\Models\Contract;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TerminateContracts extends Command
{
    protected $signature   = 'contracts:terminate';
    protected $description = 'Hentikan kontrak yang sudah mencapai tanggal efektif terminasi';

    public function handle(): void
    {
        $reasonLabels = [
            'mutual_agreement'   => 'Kesepakatan Bersama',
            'breach_of_contract' => 'Pelanggaran Kontrak',
            'force_majeure'      => 'Force Majeure',
            'expiration'         => 'Berakhirnya Masa Kontrak',
            'other'              => 'Lainnya',
        ];

        // Cari kontrak active yang tanggal efektif terminasinya sudah lewat
        $contracts = Contract::where('status', 'active')
            ->whereHas('termination', function ($q) {
                $q->whereDate('effective_date', '<=', Carbon::today());
            })
            ->with('signers.user', 'termination', 'creator')
            ->get();

        if ($contracts->isEmpty()) {
            $this->info('Tidak ada kontrak yang perlu diterminasi hari ini.');
            return;
        }

        foreach ($contracts as $contract) {
            try {
                $contract->update(['status' => 'terminated']);

                $termination    = $contract->termination;
                $effectiveDate  = Carbon::parse($termination->effective_date)
                    ->locale('id')->isoFormat('D MMMM YYYY');
                $reasonLabel    = $reasonLabels[$termination->termination_reason]
                    ?? $termination->termination_reason;

                $message = "{$contract->title} resmi dihentikan pada {$effectiveDate}. Alasan: {$reasonLabel}.";

                // In-app notifikasi + email ke HRD
                Notification::create([
                    'user_id'     => $contract->created_by,
                    'contract_id' => $contract->id,
                    'type'        => 'contract_terminated',
                    'message'     => $message,
                    'is_read'     => false,
                ]);

                if ($contract->creator?->email) {
                    Mail::to($contract->creator->email)->send(
                        new ContractTerminatedMail(
                            contract: $contract,
                            recipientName: $contract->creator->name,
                            reason: $reasonLabel,
                            effectiveDate: $effectiveDate,
                        )
                    );
                }

                // In-app notifikasi + email ke internal signer (manager)
                foreach ($contract->signers as $signer) {
                    if ($signer->signer_type === 'internal' && $signer->user_id) {
                        Notification::create([
                            'user_id'     => $signer->user_id,
                            'contract_id' => $contract->id,
                            'type'        => 'contract_terminated',
                            'message'     => $message,
                            'is_read'     => false,
                        ]);

                        if ($signer->user?->email) {
                            try {
                                Mail::to($signer->user->email)->send(
                                    new ContractTerminatedMail(
                                        contract: $contract,
                                        recipientName: $signer->user->name,
                                        reason: $reasonLabel,
                                        effectiveDate: $effectiveDate,
                                    )
                                );
                            } catch (\Exception $e) {
                                Log::error('Gagal kirim email terminated ke manager', [
                                    'contract_id' => $contract->id,
                                    'email'       => $signer->user->email,
                                    'error'       => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                }

                $this->line("✅ Terminated: {$contract->title}");

            } catch (\Exception $e) {
                Log::error('TerminateContracts error', [
                    'contract_id' => $contract->id,
                    'error'       => $e->getMessage(),
                ]);
                $this->error("❌ Gagal: {$contract->title} — {$e->getMessage()}");
            }
        }

        $this->info("Selesai. {$contracts->count()} kontrak diproses.");
    }
}