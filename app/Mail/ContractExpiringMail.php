<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContractExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string $role 'creator' | 'internal' | 'external'
     */
    public function __construct(
        public Contract $contract,
        public int $days,
        public string $recipientName = 'Pengguna',
        public string $role = 'internal',
    ) {}

    public function build()
    {
        return $this->subject("Pengingat: Kontrak {$this->contract->title} akan berakhir dalam {$this->days} hari")
            ->view('emails.contract.contract-expiring')
            ->with([
                'contract'      => $this->contract,
                'days'          => $this->days,
                'recipientName' => $this->recipientName,
                'role'          => $this->role,
                'endDate'       => $this->contract->end_date
                    ->locale('id')
                    ->isoFormat('D MMMM YYYY'),
            ]);
    }
}
