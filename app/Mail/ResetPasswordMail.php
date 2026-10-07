<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $resetToken
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SKYNITY - Réinitialisation de votre mot de passe',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #131B2E;'>
                    <h2>Bonjour {$this->user->name},</h2>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe sur SKYNITY CRM.</p>
                    <p>Voici votre code / jeton de réinitialisation :</p>
                    <div style='background-color: #F2F3FF; padding: 15px; font-size: 20px; font-weight: bold; letter-spacing: 2px; color: #0050CB; text-align: center; border-radius: 8px; margin: 15px 0;'>
                        {$this->resetToken}
                    </div>
                    <p>Ce jeton est valable pendant 1 heure. Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email.</p>
                    <br>
                    <p>Cordialement,<br>L'équipe SKYNITY</p>
                </div>
            "
        );
    }
}
