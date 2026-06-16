<?php

namespace App\Helpers;

use App\Mail\ContractNotificationMail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class NotificationHelper
{
    public static function send(
        int $userId,
        int $contractId,
        string $type,
        string $message,
        string $label,
        string $contractTitle,
        ?string $contractUrl = null,
    ): void {
        // Simpan ke database (bell)
        NotificationHelper::send(
            userId:        $contract->created_by,
            contractId:    $contract->id,
            type:          'contract_submitted',
            message:       "Kontrak {$contract->title} berhasil diajukan dan sedang menunggu peninjauan.",
            label:         'Kontrak Diajukan',
            contractTitle: $contract->title,
            contractUrl:   config('app.url') . '/contracts/' . $contract->id . '/view',
        );

        // Kirim email
        $user = User::find($userId);
        if ($user && $user->email) {
            Mail::to($user->email)->queue(
                new ContractNotificationMail(
                    recipientName: $user->name,
                    label:         $label,
                    message:       $message,
                    contractTitle: $contractTitle,
                    contractUrl:   $contractUrl,
                )
            );
        }
    }
}
