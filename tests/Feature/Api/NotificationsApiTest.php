<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class NotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_count_and_patch_mark_read_follow_spec_aliases(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $notification = $user->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\SystemNotification',
            'data' => ['title' => 'Driver license expiring soon'],
        ]);

        $count = $this->getJson('/api/v1/notifications/count');
        $count->assertOk()->assertJsonPath('data.count', 1);

        $markRead = $this->patchJson("/api/v1/notifications/{$notification->id}/read");
        $markRead->assertOk()->assertJsonPath('data.read', true);

        $countAfter = $this->getJson('/api/v1/notifications/count');
        $countAfter->assertOk()->assertJsonPath('data.count', 0);
    }
}

