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
        public string $token
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenue sur le CRM - Définissez votre mot de passe',
        );
    }

    public function content(): Content
    {
        // On suppose que le front-end tourne sur une URL précise,
        // ici on passe le lien directement à la vue
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $setupLink = "{$frontendUrl}/setup-password?token={$this->token}&email={$this->user->email}";

        return new Content(
            view: 'emails.user_invitation',
            with: [
                'setupLink' => $setupLink,
            ]
        );
    }
}
