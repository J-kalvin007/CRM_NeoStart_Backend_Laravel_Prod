<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SkynityNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $notificationTitle;
    public string $messageText;
    public ?string $actionUrl;

    public function __construct(User $user, string $notificationTitle, string $messageText, ?string $actionUrl = null)
    {
        $this->user = $user;
        $this->notificationTitle = $notificationTitle;
        $this->messageText = $messageText;
        $this->actionUrl = $actionUrl;
    }

    public function build()
    {
        return $this->subject('[SKYNITY] ' . $this->notificationTitle)
                    ->view('emails.skynity_notification', [
                        'userName' => $this->user->name,
                        'title' => $this->notificationTitle,
                        'messageText' => $this->messageText,
                        'actionUrl' => $this->actionUrl,
                    ]);
    }
}
