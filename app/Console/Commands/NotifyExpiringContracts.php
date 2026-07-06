<?php

namespace App\Console\Commands;

use App\Mail\ContractExpiringMail;
use App\Models\Contract;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
                ->with('signers.user', 'creator')
                ->get();

            foreach ($contracts as $contract) {
                $endDate = $contract->end_date->locale('id')->isoFormat('D MMMM YYYY');

                // Cek duplikasi notifikasi hari ini
                $alreadySent = Notification::where('user_id', $contract->created_by)
                    ->where('contract_id', $contract->id)
                    ->where('type', 'contract_expiring')
                    ->whereRaw("message LIKE ?", ["%dalam {$days} hari%"])
                    ->whereDate('created_at', Carbon::today())
                    ->exists();

                if ($alreadySent) continue;

                // In-app notifikasi ke HRD
                Notification::create([
                    'user_id'     => $contract->created_by,
                    'contract_id' => $contract->id,
                    'type'        => 'contract_expiring',
                    'message'     => "{$contract->title} akan berakhir pada tanggal {$endDate} ({$days} hari lagi). Segera lakukan perpanjangan kontrak jika diperlukan.",
                    'is_read'     => false,
                ]);

                // Email ke HRD
                if ($contract->creator?->email) {
                    try {
                        Mail::to($contract->creator->email)->send(
                            new ContractExpiringMail(
                                contract: $contract,
                                days: $days,
                                recipientName: $contract->creator->name,
                                role: 'creator',
                            )
                        );
                    } catch (\Exception $e) {
                        Log::error('Gagal kirim email expiring ke HRD', [
                            'contract_id' => $contract->id,
                            'email'       => $contract->creator->email,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }

                // In-app notifikasi + email ke internal signer (manager)
                foreach ($contract->signers as $signer) {
                    if ($signer->signer_type === 'internal' && $signer->user_id) {
                        Notification::create([
                            'user_id'     => $signer->user_id,
                            'contract_id' => $contract->id,
                            'type'        => 'contract_expiring',
                            'message'     => "{$contract->title} akan berakhir pada tanggal {$endDate} ({$days} hari lagi). Segera hubungi pembuat kontrak untuk melakukan perpanjangan jika diperlukan.",
                            'is_read'     => false,
                        ]);

                        if ($signer->user?->email) {
                            try {
                                Mail::to($signer->user->email)->send(
                                    new ContractExpiringMail(
                                        contract: $contract,
                                        days: $days,
                                        recipientName: $signer->user->name,
                                        role: 'internal',
                                    )
                                );
                            } catch (\Exception $e) {
                                Log::error('Gagal kirim email expiring ke manager', [
                                    'contract_id' => $contract->id,
                                    'email'       => $signer->user->email,
                                    'error'       => $e->getMessage(),
                                ]);
                            }
                        }
                    }

                    // Email ke eksternal signer
                    if ($signer->signer_type === 'external' && !empty($signer->external_email)) {
                        try {
                            Mail::to($signer->external_email)->send(
                                new ContractExpiringMail(
                                    contract: $contract,
                                    days: $days,
                                    recipientName: $signer->signer_name ?? 'Pihak Eksternal',
                                    role: 'external',
                                )
                            );
                        } catch (\Exception $e) {
                            Log::error('Gagal kirim email expiring ke eksternal', [
                                'contract_id' => $contract->id,
                                'email'       => $signer->external_email,
                                'error'       => $e->getMessage(),
                            ]);
                        }
                    }
                }

                $this->line("Notified [{$days}d]: {$contract->title}");
            }
        }

        $this->info('Selesai.');
    }
}
