<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContractRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $contract;
    public $reason;

    public function __construct(Contract $contract, string $reason)
    {
        $this->contract = $contract;
        $this->reason = $reason;
    }

    public function build()
    {
        return $this->subject('Penolakan Kontrak: ' . $this->contract->contract_number)
                    ->view('emails.contract.rejected');
    }
}
