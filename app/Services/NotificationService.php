<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Contract;
use App\Models\User;
use App\Mail\ContractStatusChangedMail;
use Illumate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function notifyStatusChanged(
        Contract $contract,
        string $oldStatus,
        User $actor,
        string $type = 'contract_status_changed',
        string $message = '',
        ?string $actionUrl = null,
    ): void {
        $contract->loadMissing(['creator', 'signers.user', 'signers']);

        $recipients = $this->getRecipients($contract, $actor);

        if (empty($message)) {
            $message = "Kontrak \"{$contract->title}\" ({$contract->contract_number}) berubah dari {$oldStatus} → {$contract->status} oleh {$actor->name}.";
        }

        foreach ($recipients as $user) {
            // In-app notification
            Notification::create([
                'user_id'     => $user->id,
                'contract_id' => $contract->id,
                'type'        => $type,
                'message'     => $message,
                'is_read'     => false,
            ]);

            // Email notification
            if ($user->email) {
                $url = $actionUrl ?? config('app.frontend_url') . '/approvals/' . $contract->id;

                try {
                    Mail::to($user->email)->send(new ContractStatusChangedMail(
                        contract:     $contract,
                        oldStatus:    $oldStatus,
                        actor:        $actor,
                        notifType:    $type,
                        notifMessage: $message,
                        actionUrl:    $url,
                        recipientName: $user->name,
                    ));
                } catch (\Exception $e) {
                    Log::error('NotificationService: gagal kirim email', [
                        'user_id'     => $user->id,
                        'contract_id' => $contract->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
    * Tentukan siapa yang harus menerima notifikasi berdasarkan status kontrak.
    */
    private function getRecipients(Contract $contract, User $actor): array
    {
        $recipients = collect();

        switch ($contract->status) {
            case 'review':
                // Beritahu semua internal signer (reviewer/manager)
                foreach ($contract->signers as $signer) {
                    if ($signer->signer_type === 'internal' && $signer->user) {
                        $recipients->push($signer->user);
                    }
                }
                break;

            case 'revision':
            case 'approved':
            case 'rejected':
            case 'active':
                // Beritahu pembuat kontrak
                if ($contract->creator) {
                    $recipients->push($contract->creator);
                }
                break;
        }

        // Jangan notifikasi diri sendiri
        return $recipients->filter(fn($u) => $u && $u->id !== $actor->id)->unique('id')->values()->all();
    }
}
