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
        public readonly string $signingUrl,
        public readonly int $iteration,
        public readonly string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Permohonan Peninjauan Dokumen - {$this->contract->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contract.external-signing-request',
            with: [
                'contract' => $this->contract,
                'signingUrl' => $this->signingUrl,
                'iteration' => $this->iteration,
                'recipientName' => $this->recipientName,
            ],
        );
    }
}
