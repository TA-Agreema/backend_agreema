<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExternalSigningRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Contract $contract,
        public readonly string   $signingUrl,
        public readonly int      $iteration,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Iterasi {$this->iteration}] Permintaan Review Kontrak: {$this->contract->contract_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contract.external-signing-request',
            with: [
                'contract'   => $this->contract,
                'signingUrl' => $this->signingUrl,
                'iteration'  => $this->iteration,
            ],
        );
    }
}
