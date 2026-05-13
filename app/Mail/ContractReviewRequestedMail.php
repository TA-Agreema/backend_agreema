<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractReviewRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Contract $contract,
        public readonly string   $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Review Diperlukan] Kontrak: {$this->contract->contract_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contract.review-requested',
            with: [
                'contract'  => $this->contract,
                'reviewUrl' => $this->reviewUrl,
            ],
        );
    }
}
