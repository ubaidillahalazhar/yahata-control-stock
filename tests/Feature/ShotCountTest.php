<?php

namespace Tests\Feature;

use App\Enums\UsageSource;
use App\Enums\UsageType;
use App\Models\DamageReport;
use App\Models\ToolsInventory;
use App\Models\User;
use App\Notifications\DamageCountOverdue;
use App\Notifications\DamageReportNeedsReview;
use App\Notifications\DamageReportSubmitted;
use App\Services\ShotCountService;
use App\Services\ToolLifeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShotCountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ToolsInventory $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-25 09:00:00');
        $this->admin = User::query()->where('email', 'admin@test.local')->firstOrFail();
        $this->tool = ToolsInventory::factory()->create(['current_stock' => 5, 'life_time' => 200_000]);
    }

    /** Life sistem dari input Excel/aplikasi sebelum laporan. */
    private function usage(int $qty, string $date = '2026-11-24'): void
    {
        app(ToolLifeService::class)->recordUsage($this->tool->id, $date, $qty, 'Sari');
    }

    /** @return array<string, mixed> */
    private function report(array $override = []): array
    {
        return [
            'tool_id' => $this->tool->id,
            'operator_name' => 'Operator',
            'shift' => 1,
            'qty_replaced' => 1,
            'actual_shot_count' => 3_150,
            'damage_reason' => 'CHIPPING',
            'action' => 'REPLACE_NEW',
            ...$override,
        ];
    }

    private function latestReport(): DamageReport
    {
        return DamageReport::query()->latest('id')->firstOrFail();
    }

    #[Test]
    public function replace_new_with_higher_counter_records_adjust_and_resets_life(): void
    {
        $this->usage(2_900);
        $pic = $this->createPic();

        $this->actingAs($pic)->post('/damage-reports', $this->report())->assertSessionHasNoErrors();

        $report = $this->latestReport();
        $this->assertSame(2_900, $report->system_shot_count);
        $this->assertSame(200_000, $report->life_time_snapshot);
        $this->assertSame(250, $report->shot_variance);
        $this->assertFalse($report->needs_review);

        $adjust = $report->usageLogs()->where('type', UsageType::Adjust->value)->sole();
        $this->assertSame(250, $adjust->qty);
        $this->assertSame(UsageSource::Damage, $adjust->source);
        $this->assertSame('2026-11-25', $adjust->usage_date->toDateString());

        $reset = $report->usageLogs()->where('type', UsageType::Reset->value)->sole();
        $this->assertSame(2_900, $reset->qty, 'RESET mencatat life yang di-nol-kan');
        $this->assertSame(0, $this->tool->fresh()->life_used, 'tool baru mulai dari 0');
        $this->assertSame(4, $this->tool->fresh()->current_stock);

        $this->assertSame(DamageReportSubmitted::class, $this->admin->notifications()->sole()->type);
    }

    #[Test]
    public function lower_counter_is_flagged_for_review_without_changing_data(): void
    {
        $this->usage(2_900);

        $this->actingAs($this->createPic())
            ->post('/damage-reports', $this->report(['actual_shot_count' => 2_500]))
            ->assertSessionHasNoErrors();

        $report = $this->latestReport();
        $this->assertSame(-400, $report->shot_variance);
        $this->assertTrue($report->needs_review);
        $this->assertTrue($report->isAwaitingReview());
        $this->assertSame(0, $report->usageLogs()->where('type', UsageType::Adjust->value)->count());
        $this->assertSame(0, $this->tool->fresh()->life_used, 'siklus tetap ditutup');

        // Satu notifikasi saja: "perlu ditinjau" (sudah memuat info laporan).
        $this->assertSame(DamageReportNeedsReview::class, $this->admin->notifications()->sole()->type);
    }

    #[Test]
    public function admin_marks_flagged_report_as_reviewed(): void
    {
        $this->usage(2_900);
        $this->actingAs($this->createPic())->post('/damage-reports', $this->report(['actual_shot_count' => 2_500]));
        $report = $this->latestReport();

        $this->actingAs($this->admin)
            ->post("/damage-reports/{$report->id}/review", ['review_note' => 'Excel tgl 20 dobel, sudah dibatalkan'])
            ->assertSessionHas('success');

        $report->refresh();
        $this->assertFalse($report->isAwaitingReview());
        $this->assertSame($this->admin->id, $report->reviewed_by);
        $this->assertSame('Excel tgl 20 dobel, sudah dibatalkan', $report->review_note);
    }

    #[Test]
    public function pic_cannot_review(): void
    {
        $this->usage(2_900);
        $pic = $this->createPic();
        $this->actingAs($pic)->post('/damage-reports', $this->report(['actual_shot_count' => 2_500]));

        $this->post("/damage-reports/{$this->latestReport()->id}/review")->assertRedirect('/pic/dashboard');
        $this->assertTrue($this->latestReport()->isAwaitingReview());
    }

    #[Test]
    public function pending_count_is_filled_later_against_snapshot_not_new_tool_usage(): void
    {
        $this->usage(2_900);
        $pic = $this->createPic();

        $this->actingAs($pic)
            ->post('/damage-reports', $this->report(['actual_shot_count' => null, 'count_pending' => true]))
            ->assertSessionHasNoErrors();

        $report = $this->latestReport();
        $this->assertTrue($report->count_pending);
        $this->assertNull($report->actual_shot_count);
        $this->assertSame(2_900, $report->system_shot_count);
        $this->assertSame(0, $this->tool->fresh()->life_used, 'stok & life tetap diproses saat laporan dibuat');
        $this->assertSame(4, $this->tool->fresh()->current_stock);

        // Tool baru sudah dipakai 500 sebelum count siklus lama dibawa.
        $this->travelTo('2026-11-26 10:00:00');
        $this->usage(500, '2026-11-26');

        $this->post("/damage-reports/{$report->id}/count", ['actual_shot_count' => 3_150])->assertSessionHasNoErrors();

        $report->refresh();
        $this->assertFalse($report->count_pending);
        $this->assertSame(250, $report->shot_variance);
        $adjust = $report->usageLogs()->where('type', UsageType::Adjust->value)->sole();
        $this->assertSame('2026-11-25', $adjust->usage_date->toDateString(), 'koreksi milik tanggal laporan');
        $this->assertSame(500, $this->tool->fresh()->life_used, 'ADJUST tidak menambah life tool baru');

        $this->post("/damage-reports/{$report->id}/count", ['actual_shot_count' => 3_200])
            ->assertSessionHasErrors('actual_shot_count');
    }

    #[Test]
    public function only_reporter_or_admin_can_fill_pending_count(): void
    {
        $this->actingAs($this->createPic())
            ->post('/damage-reports', $this->report(['actual_shot_count' => null, 'count_pending' => true]));
        $report = $this->latestReport();

        $this->actingAs($this->createPic(['username' => 'sari', 'name' => 'Sari']))
            ->post("/damage-reports/{$report->id}/count", ['actual_shot_count' => 100])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post("/damage-reports/{$report->id}/count", ['actual_shot_count' => 100])
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function count_pending_is_only_allowed_for_replace_new_and_count_is_otherwise_required(): void
    {
        $this->actingAs($this->createPic());

        $this->post('/damage-reports', $this->report(['action' => 'REGRIND_REPAIR', 'actual_shot_count' => null, 'count_pending' => true]))
            ->assertSessionHasErrors('count_pending');
        $this->post('/damage-reports', $this->report(['actual_shot_count' => null]))
            ->assertSessionHasErrors('actual_shot_count');

        $this->assertSame(0, DamageReport::query()->count());
    }

    #[Test]
    public function regrind_repair_does_not_touch_life_or_stock(): void
    {
        $this->usage(2_900);

        $this->actingAs($this->createPic())
            ->post('/damage-reports', $this->report(['action' => 'REGRIND_REPAIR']))
            ->assertSessionHasNoErrors();

        $report = $this->latestReport();
        $this->assertNull($report->system_shot_count);
        $this->assertSame(0, $report->usageLogs()->count());
        $this->assertSame(2_900, $this->tool->fresh()->life_used);
        $this->assertSame(5, $this->tool->fresh()->current_stock);
    }

    #[Test]
    public function adjust_counts_toward_monthly_actual_but_not_as_input_or_replacement(): void
    {
        $this->usage(2_900);
        $this->actingAs($this->createPic())->post('/damage-reports', $this->report());

        $actual = ToolLifeService::monthlyActuals($this->tool->id, ['2026-11'])['2026-11'];

        $this->assertSame(3_150, $actual['actual_qty'], '2.900 Excel + 250 koreksi counter');
        $this->assertSame(1, $actual['usage_inputs']);
        $this->assertSame(1, $actual['tools_replaced']);
    }

    #[Test]
    public function overdue_pending_count_notifies_admins_once_via_feed_polling(): void
    {
        $this->actingAs($this->createPic())
            ->post('/damage-reports', $this->report(['actual_shot_count' => null, 'count_pending' => true]));
        $this->admin->notifications()->delete(); // abaikan notifikasi "laporan baru"

        $this->travelTo('2026-11-26 08:00:00'); // baru 23 jam
        $this->actingAs($this->admin)->getJson('/notifications/feed')->assertJsonPath('unread_count', 0);

        $this->travelTo('2026-11-26 09:30:00'); // > 24 jam
        $this->getJson('/notifications/feed')
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('items.0.kind', 'count_overdue');
        $this->getJson('/notifications/feed')->assertJsonPath('unread_count', 1);

        $this->assertSame(DamageCountOverdue::class, $this->admin->notifications()->sole()->type);
        $this->assertTrue($this->latestReport()->isCountOverdue());
    }

    #[Test]
    public function confirmation_rule_uses_ten_percent_of_system_value(): void
    {
        $this->assertFalse(ShotCountService::needsConfirmation(290, 2_900));
        $this->assertTrue(ShotCountService::needsConfirmation(291, 2_900));
        $this->assertTrue(ShotCountService::needsConfirmation(-291, 2_900));
        $this->assertTrue(ShotCountService::needsConfirmation(5, 0), 'sistem belum punya data → selalu konfirmasi');
    }

    #[Test]
    public function damage_history_filters_pending_and_review(): void
    {
        $this->usage(2_900);
        $this->actingAs($this->createPic());
        $this->post('/damage-reports', $this->report(['actual_shot_count' => null, 'count_pending' => true]));
        $this->usage(2_900, '2026-11-25'); // tool baru dipakai lagi, lalu rusak dengan counter lebih kecil
        $this->post('/damage-reports', $this->report(['actual_shot_count' => 10]));

        $this->actingAs($this->admin)->get('/damage-reports?count=pending')
            ->assertInertia(fn ($page) => $page->count('reports.data', 1)
                ->where('attention.count_pending', 1)
                ->where('attention.needs_review', 1));
        $this->get('/damage-reports?review=needed')
            ->assertInertia(fn ($page) => $page->count('reports.data', 1)->where('reports.data.0.awaiting_review', true));
    }
}
