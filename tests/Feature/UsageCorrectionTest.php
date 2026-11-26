<?php

namespace Tests\Feature;

use App\Models\ToolsInventory;
use App\Models\ToolUsageLog;
use App\Models\User;
use App\Services\ToolLifeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pemakaian Life Time: Koreksi input, peringatan angka janggal, batas 24 jam untuk PIC.
 */
class UsageCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private ToolsInventory $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-26 08:00:00');
        $this->tool = ToolsInventory::factory()->create(['life_time' => 100_000]);
    }

    private function input(int $qty, string $date = '2026-11-26', array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/tool-usages', [
            'tool_id' => $this->tool->id,
            'usage_date' => $date,
            'qty' => $qty,
            'pic_name' => 'Nama Bebas',
            ...$extra,
        ]);
    }

    private function correct(ToolUsageLog $log, int $qty, string $date = '2026-11-26', array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post("/tool-usages/{$log->id}/correct", [
            'usage_date' => $date,
            'qty' => $qty,
            'pic_name' => 'Admin',
            'reason' => 'salah ketik',
            ...$extra,
        ]);
    }

    private function latestLog(): ToolUsageLog
    {
        return ToolUsageLog::query()->latest('id')->firstOrFail();
    }

    #[Test]
    public function correction_cancels_old_row_and_links_new_row_keeping_history(): void
    {
        $this->input(600)->assertSessionHasNoErrors();
        $old = $this->latestLog();

        $this->correct($old, 500, '2026-11-25')->assertSessionHasNoErrors()->assertSessionHas('success');

        $old->refresh();
        $new = $this->latestLog();
        $this->assertTrue($old->isCancelled());
        $this->assertSame('Dikoreksi: salah ketik', $old->cancel_reason);
        $this->assertSame($old->id, $new->corrects_id);
        $this->assertSame(500, $new->qty);
        $this->assertSame('2026-11-25', $new->usage_date->toDateString());
        $this->assertStringContainsString('Koreksi dari 26/11/2026 · 600 pcs', $new->notes);
        $this->assertSame(500, $this->tool->fresh()->life_used);
        $this->assertSame(2, ToolUsageLog::query()->count(), 'Baris lama tidak dihapus');
    }

    #[Test]
    public function correction_needs_reason_and_an_actual_change(): void
    {
        $this->input(600);
        $log = $this->latestLog();

        $this->correct($log, 500, extra: ['reason' => ''])->assertSessionHasErrors('reason');
        $this->correct($log, 600)->assertSessionHasErrors('qty');
        $this->assertFalse($log->fresh()->isCancelled());
    }

    #[Test]
    public function correcting_an_excel_row_keeps_it_in_the_batch(): void
    {
        $life = app(ToolLifeService::class);
        $log = $life->recordUsage($this->tool->id, '2026-11-20', 900, 'Sari', batchNo: 'PLT-20261120-080000');

        $this->correct($log, 90, '2026-11-20')->assertSessionHasNoErrors();
        $this->assertSame('PLT-20261120-080000', $this->latestLog()->batch_no);

        $this->assertSame(1, $life->cancelBatch('PLT-20261120-080000', 'Admin'));
        $this->assertSame(0, $this->tool->fresh()->life_used);
    }

    #[Test]
    public function unusual_quantity_requires_confirmation(): void
    {
        // > 10% life time (100.000 → batas 10.000)
        $this->input(12_000)->assertSessionHasErrors('confirm_unusual');
        $this->assertSame(0, ToolUsageLog::query()->count());

        $this->input(12_000, extra: ['confirm_unusual' => true])->assertSessionHasNoErrors();
        $this->assertSame(12_000, $this->tool->fresh()->life_used);
    }

    #[Test]
    public function quantity_far_above_daily_average_requires_confirmation(): void
    {
        $life = app(ToolLifeService::class);
        foreach (['2026-11-20', '2026-11-21', '2026-11-22', '2026-11-23', '2026-11-24'] as $date) {
            $life->recordUsage($this->tool->id, $date, 100, 'Sari');
        }

        $this->input(250)->assertSessionHasNoErrors();                // ≤ 3× rata-rata (100)
        $this->input(400)->assertSessionHasErrors('confirm_unusual'); // > 3× rata-rata
        $this->assertStringContainsString('rata-rata harian', session('errors')->first('confirm_unusual'));
    }

    #[Test]
    public function correction_to_unusual_quantity_also_requires_confirmation(): void
    {
        $this->input(600);
        $log = $this->latestLog();

        $this->correct($log, 60_000)->assertSessionHasErrors('confirm_unusual');
        $this->assertFalse($log->fresh()->isCancelled());

        $this->correct($log, 60_000, extra: ['confirm_unusual' => true])->assertSessionHasNoErrors();
    }

    #[Test]
    public function pic_records_under_own_name_and_can_change_own_input_within_24_hours(): void
    {
        $pic = $this->createPic();
        $this->actingAs($pic);

        $this->input(600)->assertSessionHasNoErrors();
        $log = $this->latestLog();
        $this->assertSame($pic->name, $log->pic_name, 'Nama PIC dipaksa sesuai akun');
        $this->assertSame($pic->id, $log->user_id);

        $this->travel(20)->hours();
        $this->correct($log, 500)->assertSessionHasNoErrors();
        $corrected = $this->latestLog();
        $this->assertSame($pic->name, $corrected->pic_name);

        // Koreksi tidak memperpanjang batas: 24 jam dihitung dari input pertama.
        $this->travel(5)->hours();
        $this->correct($corrected, 400)->assertSessionHasErrors('usage');
        $this->post("/tool-usages/{$corrected->id}/cancel", ['pic_name' => 'x'])->assertSessionHasErrors('usage');
        $this->assertStringContainsString('Batas 24 jam', session('errors')->first('usage'));
        $this->assertFalse($corrected->fresh()->isCancelled());

        // Admin tetap bisa.
        $this->actingAs(User::query()->where('role', 'admin')->firstOrFail());
        $this->correct($corrected, 400)->assertSessionHasNoErrors();
    }

    #[Test]
    public function pic_cannot_change_input_of_others_excel_rows_or_whole_batches(): void
    {
        $life = app(ToolLifeService::class);
        $adminInput = $this->input(600)->assertSessionHasNoErrors() ? $this->latestLog() : null;
        $excelRow = $life->recordUsage($this->tool->id, '2026-11-25', 300, 'Budi', batchNo: 'PLT-20261125-080000');

        $this->actingAs($this->createPic());

        $this->post("/tool-usages/{$adminInput->id}/cancel", ['pic_name' => 'x'])->assertSessionHasErrors('usage');
        $this->correct($excelRow, 30, '2026-11-25')->assertSessionHasErrors('usage');
        $this->post('/tool-usages/batches/PLT-20261125-080000/cancel', ['pic_name' => 'x'])->assertRedirect('/pic/dashboard');

        $this->assertSame(0, ToolUsageLog::query()->whereNotNull('cancelled_at')->count());
    }

    #[Test]
    public function list_tells_each_viewer_which_rows_they_may_change(): void
    {
        $pic = $this->createPic();
        $this->input(600); // oleh admin
        $this->actingAs($pic);
        $this->input(700);

        $this->get('/tool-usages')->assertInertia(fn ($page) => $page
            ->where('logs.data.0.qty', 700)
            ->where('logs.data.0.can_change', true)
            ->where('logs.data.0.change_until_display', '27/11/2026 08:00')
            ->where('logs.data.1.qty', 600)
            ->where('logs.data.1.can_change', false)
            ->where('changeWindowHours', 24));
    }
}
