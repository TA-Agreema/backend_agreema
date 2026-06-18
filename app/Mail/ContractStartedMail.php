<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class ContractStartedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contract $contract,
        public string $recipientName,
        public string $recipientType // 'signer' | 'creator'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Kontrak Aktif: {$this->contract->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contract.started',
        );
    }

    public function attachments(): array
    {
        // Konversi contract_number ke nama file
        // "PJ-008/SLAB/VI/2026" → "PJ-008_SLAB_VI_2026"
        $fileName = str_replace(['/', '-'], ['_', '-'], $this->contract->contract_number);
        $pdfPath = storage_path("app/public/contracts/pdf/{$fileName}.pdf");

        if (file_exists($pdfPath)) {
            return [
                Attachment::fromPath($pdfPath)
                    ->as("{$fileName}.pdf")
                    ->withMime('application/pdf'),
            ];
        }

        // Log jika PDF tidak ditemukan
        \Log::warning("PDF tidak ditemukan untuk kontrak: {$this->contract->contract_number}", [
            'path' => $pdfPath
        ]);

        return [];
    }
}