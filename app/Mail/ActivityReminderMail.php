<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ActivityReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Collection $activities
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Rappel : Vos visites prévues pour demain',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.activity_reminder',
            with: [
                'user' => $this->user,
                'activities' => $this->activities,
                'frontendUrl' => config('app.frontend_url', 'http://localhost:3000'),
            ]
        );
    }
}
