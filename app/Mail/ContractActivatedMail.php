<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractActivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contract $contract,
        public string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Kontrak Sah: {$this->contract->title} ({$this->contract->contract_number})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contract.contract-sanctioned',
            with: [
                'contract'      => $this->contract,
                'recipientName' => $this->recipientName,
            ],
        );
    }

    public function attachments(): array
    {
        // Lampirkan dokumen bertanda tangan jika ada (alur upload manual)
        if (!empty($this->contract->signed_document_path)) {
            $fullPath = storage_path('app/public/' . $this->contract->signed_document_path);

            if (file_exists($fullPath)) {
                return [
                    Attachment::fromPath($fullPath)
                        ->as(($this->contract->contract_number ?? 'kontrak') . '.pdf')
                        ->withMime('application/pdf'),
                ];
            }
        }

        return [];
    }
}
