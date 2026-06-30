<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContractExpiredMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string $role 'creator' | 'internal' | 'external'
     */
    public function __construct(
        public Contract $contract,
        public string $recipientName = 'Pengguna',
        public string $role = 'internal',
    ) {}

    public function build()
    {
        return $this
            ->subject("Kontrak Kedaluwarsa: {$this->contract->title}")
            ->view('emails.contract.contract-expired')
            ->with([
                'contract'      => $this->contract,
                'recipientName' => $this->recipientName,
                'role'          => $this->role,
                'endDate'       => $this->contract->end_date
                    ->locale('id')
                    ->isoFormat('D MMMM YYYY'),
            ]);
    }
}