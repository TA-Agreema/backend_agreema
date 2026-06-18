<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $label,
        public string $message,
        public string $contractTitle,
        public ?string $contractUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[Agreema] {$this->label} - {$this->contractTitle}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.contract.contract-notification');
    }
}
