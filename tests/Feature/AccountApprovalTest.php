<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\DamageReport;
use App\Models\ToolsInventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->where('email', 'admin@test.local')->firstOrFail();
        // Mulai sebagai tamu; tiap test login sendiri bila perlu.
        $this->app['auth']->guard()->forgetUser();
    }

    #[Test]
    public function registration_creates_pending_pic_without_logging_in(): void
    {
        $this->post('/register', [
            'name' => 'Ahmad Fauzi',
            'username' => '  Ahmad.Heading ',
            'machine_name' => 'Heading #01',
            'pin' => '246810',
            'pin_confirmation' => '246810',
        ])->assertRedirect('/login')->assertSessionHasNoErrors();

        $this->assertGuest();
        $user = User::query()->where('username', 'ahmad.heading')->firstOrFail();
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertSame(User::ROLE_PIC, $user->role);
        $this->assertTrue(Hash::check('246810', $user->password));
    }

    /** @return array<string, array{array<string, string>, string}> */
    public static function invalidRegistrations(): array
    {
        return [
            'username angka semua' => [['username' => '90011'], 'username'],
            'username karakter aneh' => [['username' => 'budi!'], 'username'],
            'PIN bukan 6 digit' => [['pin' => '1234', 'pin_confirmation' => '1234'], 'pin'],
            'PIN berisi huruf' => [['pin' => '12ab56', 'pin_confirmation' => '12ab56'], 'pin'],
            'konfirmasi PIN beda' => [['pin_confirmation' => '999999'], 'pin'],
        ];
    }

    #[Test]
    #[DataProvider('invalidRegistrations')]
    public function registration_rejects_invalid_username_or_pin(array $override, string $errorField): void
    {
        $this->post('/register', [
            'name' => 'Ahmad',
            'username' => 'ahmad',
            'machine_name' => 'Heading #01',
            'pin' => '246810',
            'pin_confirmation' => '246810',
            ...$override,
        ])->assertSessionHasErrors($errorField);

        $this->assertDatabaseMissing('users', ['name' => 'Ahmad']);
    }

    #[Test]
    public function registration_rejects_duplicate_username(): void
    {
        $this->createPic(['username' => 'budi']);

        $this->post('/register', [
            'name' => 'Budi Lain',
            'username' => 'BUDI',
            'machine_name' => 'Rolling #03',
            'pin' => '246810',
            'pin_confirmation' => '246810',
        ])->assertSessionHasErrors('username');
    }

    #[Test]
    #[DataProvider('blockedStatuses')]
    public function non_active_accounts_cannot_login_even_with_correct_pin(UserStatus $status, string $expected): void
    {
        $this->createPic(['status' => $status])->forceFill(['rejection_reason' => 'NIK tidak dikenal'])->save();

        $this->post('/login', ['login' => 'budi', 'password' => '123456'])
            ->assertSessionHasErrors(['login' => $expected]);

        $this->assertGuest();
    }

    /** @return array<string, array{UserStatus, string}> */
    public static function blockedStatuses(): array
    {
        return [
            'pending' => [UserStatus::Pending, 'Akun Anda masih menunggu persetujuan admin. Silakan hubungi admin tooling.'],
            'rejected' => [UserStatus::Rejected, 'Pendaftaran akun Anda ditolak admin: NIK tidak dikenal'],
            'disabled' => [UserStatus::Disabled, 'Akun Anda dinonaktifkan admin. Silakan hubungi admin tooling.'],
        ];
    }

    #[Test]
    public function approved_pic_logs_in_with_username_and_pin_and_lands_on_pic_dashboard(): void
    {
        $pic = $this->createPic();

        $this->post('/login', ['login' => ' BUDI ', 'password' => '123456', 'remember' => true])
            ->assertRedirect('/pic/dashboard');

        $this->assertAuthenticatedAs($pic);
        $this->assertNull($pic->fresh()->remember_token, 'PIC di PC bersama tidak boleh "ingat saya"');
    }

    #[Test]
    public function admin_logs_in_with_email_and_lands_on_dashboard(): void
    {
        $this->post('/login', ['login' => 'Admin@Test.local', 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($this->admin);
    }

    #[Test]
    public function login_is_locked_after_five_wrong_pins(): void
    {
        $this->createPic();

        foreach (range(1, 5) as $ignored) {
            $this->post('/login', ['login' => 'budi', 'password' => '000000'])->assertSessionHasErrors('login');
        }

        // PIN benar pun ditolak selama terkunci.
        $this->post('/login', ['login' => 'budi', 'password' => '123456'])
            ->assertSessionHasErrors(['login' => 'Terlalu banyak percobaan gagal. Coba lagi dalam 5 menit.']);
        $this->assertGuest();
    }

    #[Test]
    public function admin_approves_pending_registration(): void
    {
        $pic = $this->createPic(['status' => UserStatus::Pending]);

        $this->actingAs($this->admin)
            ->post("/accounts/{$pic->id}/status", ['status' => 'active'])
            ->assertSessionHas('success');

        $pic->refresh();
        $this->assertSame(UserStatus::Active, $pic->status);
        $this->assertSame($this->admin->id, $pic->approved_by);
        $this->assertNotNull($pic->approved_at);
    }

    #[Test]
    public function admin_rejects_registration_with_reason(): void
    {
        $pic = $this->createPic(['status' => UserStatus::Pending]);

        $this->actingAs($this->admin)
            ->post("/accounts/{$pic->id}/status", ['status' => 'rejected', 'reason' => 'Bukan karyawan line'])
            ->assertSessionHas('success');

        $this->assertSame(UserStatus::Rejected, $pic->fresh()->status);
        $this->assertSame('Bukan karyawan line', $pic->fresh()->rejection_reason);
    }

    #[Test]
    public function disabled_pic_is_logged_out_on_next_request(): void
    {
        $pic = $this->createPic();
        $this->actingAs($pic)->get('/damage-reports/create')->assertOk();

        $pic->forceFill(['status' => UserStatus::Disabled])->save();

        $this->get('/damage-reports/create')
            ->assertRedirect('/login')
            ->assertSessionHas('error', 'Akun Anda dinonaktifkan admin. Silakan hubungi admin tooling.');
        $this->assertGuest();
    }

    #[Test]
    public function admin_cannot_change_own_status(): void
    {
        $this->actingAs($this->admin)
            ->post("/accounts/{$this->admin->id}/status", ['status' => 'disabled'])
            ->assertSessionHas('error', 'Anda tidak dapat mengubah status akun Anda sendiri.');

        $this->assertSame(UserStatus::Active, $this->admin->fresh()->status);
    }

    #[Test]
    public function pic_cannot_manage_accounts(): void
    {
        $pending = $this->createPic(['username' => 'sari', 'status' => UserStatus::Pending]);

        $this->actingAs($this->createPic())
            ->post("/accounts/{$pending->id}/status", ['status' => 'active'])
            ->assertRedirect('/pic/dashboard');
        $this->delete("/accounts/{$this->admin->id}")->assertRedirect('/pic/dashboard');

        $this->assertSame(UserStatus::Pending, $pending->fresh()->status);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    #[Test]
    public function reset_pin_forces_pic_to_change_it_before_doing_anything(): void
    {
        $pic = $this->createPic();

        $this->actingAs($this->admin)->post("/accounts/{$pic->id}/reset-secret")->assertSessionHas('success');
        $pic->refresh();
        $this->assertTrue($pic->must_change_password);
        $this->assertFalse(Hash::check('123456', $pic->password));

        preg_match('/: (\d{6}) —/', session('success'), $match);
        $temporary = $match[1];

        $this->app['auth']->guard()->forgetUser();
        $this->post('/login', ['login' => 'budi', 'password' => $temporary])->assertRedirect('/account/pin');
        $this->get('/damage-reports/create')->assertRedirect('/account/pin');

        $this->put('/account/pin', ['current' => $temporary, 'pin' => '654321', 'pin_confirmation' => '654321'])
            ->assertRedirect('/pic/dashboard');

        $pic->refresh();
        $this->assertFalse($pic->must_change_password);
        $this->assertTrue(Hash::check('654321', $pic->password));
        $this->get('/damage-reports/create')->assertOk();
    }

    #[Test]
    public function pic_changes_own_pin_only_with_correct_current_pin(): void
    {
        $pic = $this->createPic();
        $this->actingAs($pic);

        $this->put('/account/pin', ['current' => '000000', 'pin' => '654321', 'pin_confirmation' => '654321'])
            ->assertSessionHasErrors('current');
        $this->put('/account/pin', ['current' => '123456', 'pin' => '123456', 'pin_confirmation' => '123456'])
            ->assertSessionHasErrors('pin');

        $this->assertTrue(Hash::check('123456', $pic->fresh()->password));
    }

    /** @return array<string, array{string}> */
    public static function adminOnlyPages(): array
    {
        return [
            'dashboard' => ['/'],
            'transaksi' => ['/transactions/create'],
            'audit log' => ['/audit-log'],
            'label QR' => ['/qr-labels'],
            'sync excel' => ['/sync'],
            'akun' => ['/accounts'],
        ];
    }

    #[Test]
    #[DataProvider('adminOnlyPages')]
    public function pic_is_redirected_away_from_admin_pages(string $url): void
    {
        $this->actingAs($this->createPic())
            ->get($url)
            ->assertRedirect('/pic/dashboard');
    }

    #[Test]
    public function pic_report_is_recorded_under_own_account_name(): void
    {
        $pic = $this->createPic();
        $tool = ToolsInventory::factory()->create(['current_stock' => 3]);

        $this->actingAs($pic)->post('/damage-reports', [
            'tool_id' => $tool->id,
            'operator_name' => 'Nama Orang Lain',
            'shift' => 1,
            'qty_replaced' => 1,
            'actual_shot_count' => 150000,
            'damage_reason' => 'CHIPPING',
            'action' => 'REPLACE_NEW',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('damage_reports', [
            'tool_id' => $tool->id,
            'user_id' => $pic->id,
            'operator_name' => 'Budi Operator',
        ]);
    }

    #[Test]
    public function pic_sees_only_own_reports(): void
    {
        $pic = $this->createPic();
        $other = $this->createPic(['username' => 'sari', 'name' => 'Sari']);
        $tool = ToolsInventory::factory()->create();

        $own = $this->makeReport($tool, $pic);
        $foreign = $this->makeReport($tool, $other);

        $this->actingAs($pic)->get('/damage-reports')
            ->assertInertia(fn ($page) => $page
                ->where('reports.data.0.id', $own->id)
                ->count('reports.data', 1));

        $this->get("/damage-reports/{$own->id}")->assertOk();
        $this->get("/damage-reports/{$foreign->id}")->assertForbidden();

        // Admin tetap melihat semuanya.
        $this->actingAs($this->admin)->get('/damage-reports')
            ->assertInertia(fn ($page) => $page->count('reports.data', 2));
    }

    #[Test]
    public function admin_created_pic_is_active_immediately(): void
    {
        $this->actingAs($this->admin)->post('/accounts', [
            'name' => 'Dedi',
            'role' => 'pic',
            'machine_name' => 'Rolling #02',
            'username' => 'dedi',
            'pin' => '135790',
            'pin_confirmation' => '135790',
        ])->assertSessionHasNoErrors();

        $dedi = User::query()->where('username', 'dedi')->firstOrFail();
        $this->assertSame(UserStatus::Active, $dedi->status);
        $this->assertSame($this->admin->id, $dedi->approved_by);
    }

    #[Test]
    public function admin_account_requires_email_and_nik_must_be_unique(): void
    {
        $this->admin->forceFill(['nik' => '90011'])->save();

        $this->actingAs($this->admin)->post('/accounts', [
            'name' => 'Admin Baru',
            'role' => 'admin',
            'nik' => '90011',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors(['email', 'nik']);
    }

    private function makeReport(ToolsInventory $tool, User $user): DamageReport
    {
        return $tool->damageReports()->create([
            'user_id' => $user->id,
            'operator_name' => $user->name,
            'shift' => 1,
            'qty_replaced' => 1,
            'actual_shot_count' => 1000,
            'damage_reason' => 'CHIPPING',
            'action' => 'REGRIND_REPAIR',
            'stock_before' => 4,
            'stock_after' => 4,
            'reported_at' => now(),
        ]);
    }
}
