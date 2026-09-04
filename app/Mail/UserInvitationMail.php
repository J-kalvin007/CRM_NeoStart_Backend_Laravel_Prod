<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $temporaryPassword
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Vos identifiants de connexion CRM',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user_invitation',
            with: [
                'temporaryPassword' => $this->temporaryPassword,
            ]
        );
    }
}
