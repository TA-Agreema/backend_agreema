<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContractTerminatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Contract $contract,
        public readonly string $recipientName,
        public readonly string $reason,
        public readonly string $effectiveDate,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Kontrak Telah Dihentikan] {$this->contract->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contract.contract-terminated',
            with: [
                'contract'      => $this->contract,
                'recipientName' => $this->recipientName,
                'reason'        => $this->reason,
                'effectiveDate' => $this->effectiveDate,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
