<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\ToolsInventory;
use App\Models\User;
use App\Notifications\DamageReportSubmitted;
use App\Notifications\PicRegistered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->where('email', 'admin@test.local')->firstOrFail();
    }

    #[Test]
    public function registration_notifies_every_active_admin(): void
    {
        $second = $this->makeAdmin('dua@test.local');
        $disabled = $this->makeAdmin('mati@test.local', UserStatus::Disabled);
        $this->app['auth']->guard()->forgetUser();

        $this->post('/register', [
            'name' => 'Ahmad Fauzi',
            'username' => 'ahmad',
            'machine_name' => 'Heading #01',
            'pin' => '246810',
            'pin_confirmation' => '246810',
        ])->assertSessionHasNoErrors();

        foreach ([$this->admin, $second] as $admin) {
            $notification = $admin->notifications()->sole();
            $this->assertSame(PicRegistered::class, $notification->type);
            $this->assertSame('registration', $notification->data['kind']);
            $this->assertSame('/accounts', $notification->data['url']);
            $this->assertStringContainsString('Ahmad Fauzi (ahmad)', $notification->data['body']);
        }
        $this->assertCount(0, $disabled->notifications);
    }

    #[Test]
    public function pic_damage_report_notifies_admins(): void
    {
        $pic = $this->createPic();
        $tool = ToolsInventory::factory()->create(['current_stock' => 3]);

        $this->actingAs($pic)->post('/damage-reports', $this->reportPayload($tool))->assertSessionHasNoErrors();

        $notification = $this->admin->notifications()->sole();
        $this->assertSame(DamageReportSubmitted::class, $notification->type);
        $this->assertStringContainsString('Budi Operator', $notification->data['body']);
        $this->assertMatchesRegularExpression('#^/damage-reports/\d+$#', $notification->data['url']);
        $this->assertCount(0, $pic->notifications, 'PIC tidak menerima notifikasi admin');
    }

    #[Test]
    public function admin_is_not_notified_about_own_report(): void
    {
        $other = $this->makeAdmin('dua@test.local');
        $tool = ToolsInventory::factory()->create(['current_stock' => 3]);

        $this->actingAs($this->admin)->post('/damage-reports', $this->reportPayload($tool))->assertSessionHasNoErrors();

        $this->assertCount(0, $this->admin->fresh()->notifications);
        $this->assertCount(1, $other->notifications);
    }

    #[Test]
    public function feed_returns_unread_count_items_and_pending_accounts(): void
    {
        $this->createPic(['username' => 'sari', 'status' => UserStatus::Pending]);
        $this->admin->notify(new PicRegistered(User::query()->where('username', 'sari')->sole()));

        $this->actingAs($this->admin)
            ->getJson('/notifications/feed')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('pending_accounts', 1)
            ->assertJsonPath('items.0.kind', 'registration')
            ->assertJsonPath('items.0.read', false);
    }

    #[Test]
    public function admin_marks_one_or_all_notifications_as_read(): void
    {
        $pic = $this->createPic();
        $this->admin->notify(new PicRegistered($pic));
        $this->admin->notify(new PicRegistered($pic));
        [$first] = $this->admin->notifications()->pluck('id');

        $this->actingAs($this->admin)->postJson("/notifications/{$first}/read")->assertOk();
        $this->assertSame(1, $this->admin->unreadNotifications()->count());

        $this->postJson('/notifications/read-all')->assertOk();
        $this->assertSame(0, $this->admin->unreadNotifications()->count());
    }

    #[Test]
    public function admin_cannot_mark_another_admins_notification(): void
    {
        $other = $this->makeAdmin('dua@test.local');
        $other->notify(new PicRegistered($this->createPic()));
        $id = $other->notifications()->value('id');

        $this->actingAs($this->admin)->postJson("/notifications/{$id}/read")->assertOk();

        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    #[Test]
    public function pic_cannot_read_admin_feed(): void
    {
        $this->actingAs($this->createPic())
            ->get('/notifications/feed')
            ->assertRedirect('/pic/dashboard');
    }

    private function makeAdmin(string $email, UserStatus $status = UserStatus::Active): User
    {
        return User::query()->create([
            'name' => 'Admin '.$email,
            'email' => $email,
            'role' => User::ROLE_ADMIN,
            'status' => $status,
            'password' => 'password',
        ]);
    }

    /** @return array<string, mixed> */
    private function reportPayload(ToolsInventory $tool): array
    {
        return [
            'tool_id' => $tool->id,
            'operator_name' => 'Operator',
            'shift' => 1,
            'qty_replaced' => 1,
            'actual_shot_count' => 150000,
            'damage_reason' => 'CHIPPING',
            'action' => 'REPLACE_NEW',
        ];
    }
}
