<?php

namespace Tests\Feature;

use App\Models\ToolsInventory;
use App\Models\ToolUsageLog;
use App\Services\ToolLifeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolLifeTest extends TestCase
{
    use RefreshDatabase;

    private function useLife(ToolsInventory $tool, int $qty, string $date): \Illuminate\Testing\TestResponse
    {
        return $this->post('/tool-usages', [
            'tool_id' => $tool->id,
            'usage_date' => $date,
            'qty' => $qty,
            'pic_name' => 'Budi',
        ]);
    }

    #[Test]
    public function thresholds_are_fixed_green_yellow_red(): void
    {
        $cases = [
            [0, 'green', 'SECURE', 0.0],
            [499_999, 'green', 'SECURE', 49.9],
            [500_000, 'yellow', 'SECURE', 50.0],
            [799_999, 'yellow', 'SECURE', 79.9],
            [800_000, 'red', 'ORDER', 80.0],
            [1_200_000, 'red', 'ORDER', 120.0],
        ];

        foreach ($cases as [$used, $level, $status, $percent]) {
            $summary = ToolLifeService::summary(1_000_000, $used);

            $this->assertSame($level, $summary['life_level'], "used {$used}");
            $this->assertSame($status, $summary['life_status'], "used {$used}");
            $this->assertSame($percent, $summary['life_usage_percent'], "used {$used}");
        }

        $this->assertSame(0, ToolLifeService::summary(1_000_000, 1_200_000)['life_remaining']);
        $this->assertSame(200_000, ToolLifeService::summary(1_000_000, 1_200_000)['life_over']);
    }

    #[Test]
    public function random_day_inputs_accumulate_into_life_used(): void
    {
        $tool = ToolsInventory::factory()->create(['life_time' => 1_000_000]);

        $this->useLife($tool, 600_000, now()->subDays(10)->toDateString())->assertSessionHasNoErrors();
        $this->useLife($tool, 100_000, now()->toDateString())->assertSessionHasNoErrors();

        $tool->refresh();
        $this->assertSame(700_000, $tool->life_used);
        $this->assertSame('yellow', $tool->lifeSummary()['life_level']);
        $this->assertSame(300_000, $tool->lifeSummary()['life_remaining']);
        $this->assertDatabaseCount('tool_usage_logs', 2);
    }

    #[Test]
    public function future_dates_are_rejected(): void
    {
        $tool = ToolsInventory::factory()->create(['life_time' => 1_000_000]);

        $this->useLife($tool, 10, now()->addDay()->toDateString())->assertSessionHasErrors('usage_date');

        $this->assertDatabaseCount('tool_usage_logs', 0);
    }

    #[Test]
    public function cancelling_an_input_restores_life_used_and_keeps_the_row(): void
    {
        $tool = ToolsInventory::factory()->create(['life_time' => 1_000_000]);
        $this->useLife($tool, 300_000, now()->toDateString());
        $this->useLife($tool, 600_000, now()->toDateString());
        $this->assertSame('red', $tool->fresh()->lifeSummary()['life_level']);

        $log = ToolUsageLog::query()->where('qty', 600_000)->firstOrFail();

        $this->post("/tool-usages/{$log->id}/cancel", ['pic_name' => 'Sari', 'cancel_reason' => 'Salah ketik'])
            ->assertSessionHasNoErrors();

        $this->assertSame(300_000, $tool->fresh()->life_used);
        $this->assertNotNull($log->fresh()->cancelled_at);
        $this->assertSame('Sari', $log->fresh()->cancelled_by);
        $this->assertDatabaseCount('tool_usage_logs', 2);

        $this->post("/tool-usages/{$log->id}/cancel", ['pic_name' => 'Sari'])->assertSessionHasErrors('usage');
    }

    #[Test]
    public function reset_starts_a_new_tool_and_older_backdated_usage_does_not_count(): void
    {
        $tool = ToolsInventory::factory()->create(['life_time' => 1_000_000, 'current_stock' => 4]);
        $this->useLife($tool, 900_000, now()->subDays(5)->toDateString());

        $this->post("/tools/{$tool->id}/life-reset", ['usage_date' => now()->subDays(2)->toDateString(), 'pic_name' => 'Budi'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $tool->fresh()->life_used);
        $this->assertSame(4, $tool->fresh()->current_stock, 'Reset tidak mengubah stok');
        $this->assertDatabaseHas('tool_usage_logs', ['type' => 'RESET', 'qty' => 900_000, 'life_used_after' => 0]);

        // Input mundur sebelum tanggal reset = milik tool lama.
        $this->useLife($tool, 50_000, now()->subDays(4)->toDateString());
        $this->assertSame(0, $tool->fresh()->life_used);

        // Input setelah reset = tool baru.
        $this->useLife($tool, 120_000, now()->toDateString());
        $this->assertSame(120_000, $tool->fresh()->life_used);

        // Reset dibatalkan → pemakaian tool lama kembali dihitung.
        $reset = ToolUsageLog::query()->where('type', 'RESET')->firstOrFail();
        $this->post("/tool-usages/{$reset->id}/cancel", ['pic_name' => 'Budi']);
        $this->assertSame(1_070_000, $tool->fresh()->life_used);
    }

    #[Test]
    public function pages_render_with_life_data(): void
    {
        $tool = ToolsInventory::factory()->create(['life_time' => 1_000_000]);
        $this->useLife($tool, 820_000, now()->toDateString());

        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('tools.0.life_status', 'ORDER')
            ->where('tools.0.life_level', 'red')
            ->where('tools.0.life_remaining', 180_000));

        $this->get("/tools/{$tool->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->component('Tools/Show')
            ->has('usageLogs', 1));

        $this->get('/tool-usages?month='.now()->format('Y-m'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('ToolUsages/Index')
            ->where('totals.qty', 820_000)
            ->has('logs.data', 1));
    }
}
