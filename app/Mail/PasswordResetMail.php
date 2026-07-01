<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Agreema] Reset Password Akun',
            using: [
                function (Email $message): void {
                    $message->embedFromPath(
                        public_path('LogoAgreema.png'),
                        'agreema-logo',
                        'image/png',
                    );
                },
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.password-reset',
            with: [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
            ],
        );
    }
}
