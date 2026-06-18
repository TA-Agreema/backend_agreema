<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Contract;
use App\Models\User;

class ContractStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Contract $contract,
        public string $oldStatus,
        public User $actor,
        public string $notifType = '',
        public string $notifMessage = '',
        public ?string $actionUrl = null,
        public ?string $recipientName = null,
    ){}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subjects = [
            'review_requested'           => 'Peninjauan Kontrak Dibutuhkan',
            'manager_approved'           => 'Kontrak Disetujui',
            'manager_rejected'           => 'Kontrak Ditolak',
            'manager_revision_requested' => 'Revisi Kontrak Diminta',
            'signed_document_uploaded'   => 'Dokumen Kontrak Telah Diupload',
            'all_reviewers_signed'       => 'Semua Peninjau Telah Menandatangani',
            'contract_activated'         => 'Kontrak Telah Aktif',
            'external_approved'          => 'Kontrak Disetujui Semua Pihak',
        ];

        return new Envelope(
            subject: "Status Kontrak Diperbarui: \"{$this->contract->title}\"",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.contract.contract-status-changed',
            with: [
                'recipientName' => $this->recipientName,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
