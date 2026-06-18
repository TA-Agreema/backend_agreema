<?php

namespace App\Console\Commands;

use App\Mail\ContractStartedMail;
use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendContractStartedNotifications extends Command
{
    protected $signature = 'contracts:send-started-notifications';
    protected $description = 'Kirim email notifikasi kontrak yang mulai hari ini';

    public function handle(): void
    {
        $today = Carbon::today()->toDateString();

        $contracts = Contract::with([
            'signers.user',   // internal signers
            'creator',        // pembuat kontrak
        ])
        ->whereDate('start_date', $today)
        ->where('status', 'active') // pastikan hanya yg sudah active
        ->get();

        if ($contracts->isEmpty()) {
            $this->info('Tidak ada kontrak yang mulai hari ini.');
            return;
        }

        foreach ($contracts as $contract) {
            $this->sendForContract($contract);
        }

        $this->info("Selesai memproses {$contracts->count()} kontrak.");
    }

    private function sendForContract(Contract $contract): void
    {
        $sent = [];

        // 1. Kirim ke creator
        if ($contract->creator && $contract->creator->email) {
            Mail::to($contract->creator->email)
                ->send(new ContractStartedMail($contract, $contract->creator->name, 'creator'));
            $sent[] = $contract->creator->email;
        }

        // 2. Kirim ke semua signer
        foreach ($contract->signers as $signer) {
            // Internal signer
            if ($signer->signer_type === 'internal' && $signer->user) {
                $email = $signer->user->email;
                $name  = $signer->user->name;
            }
            // External signer
            elseif ($signer->signer_type === 'external' && $signer->external_email) {
                $email = $signer->external_email;
                $name  = $signer->signer_name ?? 'Pihak Eksternal';
            } else {
                continue;
            }

            // Hindari kirim duplikat jika creator juga signer
            if (in_array($email, $sent)) continue;

            Mail::to($email)->send(new ContractStartedMail($contract, $name, 'signer'));
            $sent[] = $email;
        }

        $this->line("  ✓ Kontrak #{$contract->contract_number} → " . count($sent) . " penerima");
    }
}