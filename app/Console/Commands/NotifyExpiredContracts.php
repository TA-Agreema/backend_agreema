<?php

namespace App\Console\Commands;

use App\Mail\ContractExpiredMail;
use App\Models\Contract;
use App\Models\Notification;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyExpiredContracts extends Command
{
    protected $signature   = 'contracts:notify-expired';
    protected $description = 'Kirim notifikasi kontrak yang sudah kedaluwarsa hari ini';

    public function handle(): void
    {
        $contracts = Contract::where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', Carbon::today())
            ->with(['signers.user', 'creator'])
            ->get();

        foreach ($contracts as $contract) {
            // Update status ke expired
            $contract->update(['status' => 'expired']);

            $endDate        = $contract->end_date->locale('id')->isoFormat('D MMMM YYYY');
            $messageHrd     = "{$contract->title} telah kedaluwarsa pada {$endDate}.";
            $messageManager = "{$contract->title} yang telah Bapak/Ibu setujui telah kedaluwarsa pada {$endDate}.";

            // Notifikasi in-app ke HRD
            Notification::create([
                'user_id'     => $contract->created_by,
                'contract_id' => $contract->id,
                'type'        => 'contract_expired',
                'message'     => $messageHrd,
                'is_read'     => false,
            ]);

            // Email ke HRD (creator) — sekali per kontrak, di luar loop signer
            if ($contract->creator?->email) {
                $this->sendMail(
                    email: $contract->creator->email,
                    contract: $contract,
                    recipientName: $contract->creator->name,
                    role: 'creator',
                );
            }

            // Loop signer: notifikasi internal + email internal & eksternal
            foreach ($contract->signers as $signer) {
                if ($signer->signer_type === 'internal' && $signer->user_id) {
                    Notification::create([
                        'user_id'     => $signer->user_id,
                        'contract_id' => $contract->id,
                        'type'        => 'contract_expired',
                        'message'     => $messageManager,
                        'is_read'     => false,
                    ]);

                    if ($signer->user?->email) {
                        $this->sendMail(
                            email: $signer->user->email,
                            contract: $contract,
                            recipientName: $signer->user->name,
                            role: 'internal',
                        );
                    }
                }

                if ($signer->signer_type === 'external' && $signer->external_email) {
                    $this->sendMail(
                        email: $signer->external_email,
                        contract: $contract,
                        recipientName: $signer->signer_name ?? 'Pihak Eksternal',
                        role: 'external',
                    );
                }
            }

            $this->line("Expired: {$contract->title}");
        }

        $this->line('Selesai.');
    }

    private function sendMail(string $email, Contract $contract, string $recipientName, string $role): void
    {
        try {
            $this->info("Kirim email [{$role}] ke: {$email}");
            Log::info("Expired email [{$role}]: {$email}");
            Mail::to($email)->send(new ContractExpiredMail($contract, $recipientName, $role));
        } catch (Exception $e) {
            Log::error('NotifyExpiredContracts: gagal mengirim email', [
                'contract_id' => $contract->id,
                'email'       => $email,
                'role'        => $role,
                'error'       => $e->getMessage(),
            ]);
            $this->error("Gagal kirim email ke {$email}: {$e->getMessage()}");
        }
    }
}