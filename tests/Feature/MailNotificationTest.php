<?php

namespace Tests\Feature;

use App\Mail\ActivityReminderMail;
use App\Mail\LoginOtpMail;
use App\Mail\UserInvitationMail;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_invitation_email_is_sent_on_invite(): void
    {
        Mail::fake();

        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/auth/invite', [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(201);

        Mail::assertSent(UserInvitationMail::class, function ($mail) {
            return $mail->hasTo('johndoe@example.com');
        });
    }

    public function test_login_otp_email_is_sent_when_otp_enabled(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'otpuser@example.com',
            'password' => Hash::make('password123'),
            'otp_enabled' => true,
            'force_password_change' => false,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'otpuser@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(202)
                 ->assertJson(['status' => 'requires_otp']);

        Mail::assertSent(LoginOtpMail::class, function ($mail) {
            return $mail->hasTo('otpuser@example.com');
        });
    }

    public function test_activity_reminder_email_command_sends_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'delegate@example.com',
        ]);

        $tomorrow = \Carbon\Carbon::tomorrow()->toDateString();

        Activity::create([
            'user_id' => $user->id,
            'title' => 'Visite médicale de rappel',
            'type_activite' => 'visite_medicale',
            'statut' => 'planifiee',
            'date_activite' => $tomorrow,
            'heure_debut' => '09:00:00',
        ]);

        $this->artisan('app:send-reminders')
             ->assertExitCode(0);

        Mail::assertSent(ActivityReminderMail::class, function ($mail) {
            return $mail->hasTo('delegate@example.com');
        });
    }
}
