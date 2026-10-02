<?php

namespace Tests\Feature;

use App\Mail\SkynityNotificationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_fcm_token_registration_endpoint(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/user/fcm-token', [
            'fcm_token' => 'test-fcm-token-123456',
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'FCM Token updated successfully']);

        $this->assertEquals('test-fcm-token-123456', $user->fresh()->fcm_token);
    }

    public function test_send_notification_stores_in_database_and_dispatches_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'name' => 'Target User',
            'email' => 'target@example.com',
        ]);

        \App\Http\Controllers\Api\V1\NotificationController::sendNotification(
            $user,
            'Test Notification Title',
            'This is a test notification message.',
            'info',
            'delegate'
        );

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => 'App\Models\User',
        ]);

        Mail::assertSent(SkynityNotificationMail::class, function ($mail) {
            return $mail->hasTo('target@example.com') &&
                   $mail->notificationTitle === 'Test Notification Title';
        });
    }

    public function test_mark_as_read_and_mark_all_as_read(): void
    {
        $user = User::factory()->create();

        \App\Http\Controllers\Api\V1\NotificationController::sendNotification(
            $user,
            'Unread Notif 1',
            'Message 1'
        );

        $notif = DB::table('notifications')->where('notifiable_id', $user->id)->first();
        $this->assertNull($notif->read_at);

        // Mark as read
        $response = $this->actingAs($user)->postJson("/api/v1/notifications/{$notif->id}/mark-as-read");
        $response->assertStatus(204);

        $updatedNotif = DB::table('notifications')->where('id', $notif->id)->first();
        $this->assertNotNull($updatedNotif->read_at);

        // Delete notification
        $delRes = $this->actingAs($user)->deleteJson("/api/v1/notifications/{$notif->id}");
        $delRes->assertStatus(204);

        $this->assertDatabaseMissing('notifications', ['id' => $notif->id]);
    }
}
