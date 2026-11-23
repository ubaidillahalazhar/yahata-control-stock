<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\DamageReport;
use App\Models\ToolsInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockMutationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function stock_in_increases_stock_and_writes_history(): void
    {
        $tool = ToolsInventory::factory()->create(['current_stock' => 2]);

        $this->post('/transactions', [
            'tool_id' => $tool->id,
            'type' => 'IN',
            'qty' => 5,
            'reference_no' => 'DO-0001',
            'pic_name' => 'Budi',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(7, $tool->fresh()->current_stock);
        $this->assertDatabaseHas('stock_transactions', [
            'tool_id' => $tool->id,
            'type' => TransactionType::In->value,
            'qty_change' => 5,
            'stock_before' => 2,
            'stock_after' => 7,
            'reference_no' => 'DO-0001',
            'pic_name' => 'Budi',
        ]);
    }

    #[Test]
    public function stock_out_cannot_make_stock_negative(): void
    {
        $tool = ToolsInventory::factory()->create(['current_stock' => 1]);

        $this->post('/transactions', [
            'tool_id' => $tool->id,
            'type' => 'OUT',
            'qty' => 3,
            'pic_name' => 'Budi',
        ])->assertSessionHasErrors('qty');

        $this->assertSame(1, $tool->fresh()->current_stock);
        $this->assertDatabaseCount('stock_transactions', 0);
    }

    #[Test]
    public function damage_report_replace_new_cuts_stock_and_logs_damage_replacement(): void
    {
        $tool = ToolsInventory::factory()->create(['current_stock' => 4]);

        $response = $this->post('/damage-reports', [
            'tool_id' => $tool->id,
            'operator_name' => 'Andi',
            'shift' => 2,
            'qty_replaced' => 1,
            'actual_shot_count' => 185000,
            'damage_reason' => 'CHIPPING',
            'action' => 'REPLACE_NEW',
        ]);

        $report = DamageReport::query()->firstOrFail();
        $response->assertRedirect(route('damage-reports.show', $report));

        $this->assertSame(3, $tool->fresh()->current_stock);
        $this->assertMatchesRegularExpression('/^DR-\d{8}-\d{5}$/', $report->report_no);
        $this->assertSame(4, $report->stock_before);
        $this->assertSame(3, $report->stock_after);
        $this->assertDatabaseHas('stock_transactions', [
            'tool_id' => $tool->id,
            'type' => TransactionType::DamageReplacement->value,
            'qty_change' => -1,
            'damage_report_id' => $report->id,
            'reference_no' => $report->report_no,
        ]);
    }

    #[Test]
    public function damage_report_regrind_does_not_change_stock(): void
    {
        $tool = ToolsInventory::factory()->create(['current_stock' => 4]);

        $this->post('/damage-reports', [
            'tool_id' => $tool->id,
            'operator_name' => 'Andi',
            'shift' => 1,
            'qty_replaced' => 2,
            'actual_shot_count' => 90000,
            'damage_reason' => 'WEAR_NORMAL',
            'action' => 'REGRIND_REPAIR',
        ])->assertSessionHasNoErrors();

        $this->assertSame(4, $tool->fresh()->current_stock);
        $this->assertDatabaseCount('damage_reports', 1);
        $this->assertDatabaseCount('stock_transactions', 0);
    }

    #[Test]
    public function damage_report_replace_new_is_rejected_when_stock_is_insufficient(): void
    {
        $tool = ToolsInventory::factory()->create(['current_stock' => 0]);

        $this->post('/damage-reports', [
            'tool_id' => $tool->id,
            'operator_name' => 'Andi',
            'shift' => 3,
            'qty_replaced' => 1,
            'actual_shot_count' => 1000,
            'damage_reason' => 'BROKEN',
            'action' => 'REPLACE_NEW',
        ])->assertSessionHasErrors('qty_replaced');

        $this->assertDatabaseCount('damage_reports', 0);
    }

    #[Test]
    public function opname_apply_sets_physical_stock_and_logs_difference(): void
    {
        $a = ToolsInventory::factory()->create(['current_stock' => 4]);
        $b = ToolsInventory::factory()->create(['current_stock' => 2]);

        $this->post('/sync/opname-apply', [
            'pic_name' => 'Tim Opname',
            'items' => [
                ['tool_id' => $a->id, 'actual_stock' => 6, 'template_system_stock' => 4],
                ['tool_id' => $b->id, 'actual_stock' => 2, 'template_system_stock' => 2],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(6, $a->fresh()->current_stock);
        $this->assertSame(2, $b->fresh()->current_stock);
        $this->assertDatabaseCount('stock_transactions', 1);
        $this->assertDatabaseHas('stock_transactions', [
            'tool_id' => $a->id,
            'type' => TransactionType::OpnameAdjust->value,
            'qty_change' => 2,
            'stock_before' => 4,
            'stock_after' => 6,
            'pic_name' => 'Tim Opname',
        ]);
    }

    #[Test]
    public function pages_render_successfully(): void
    {
        $tool = ToolsInventory::factory()->create();

        $this->get('/')->assertOk();
        $this->get("/tools/{$tool->id}")->assertOk();
        $this->get('/transactions/create')->assertOk();
        $this->get("/damage-reports/create?tool_id={$tool->id}")->assertOk();
        $this->get('/damage-reports')->assertOk();
        $this->get('/qr-labels')->assertOk();
        $this->get('/sync')->assertOk();
        $this->get('/audit-log')->assertOk();
        $this->getJson('/api/v1/tools?status=ORDER')->assertOk();
        $this->getJson('/api/v1/tools/summary')->assertOk()->assertJsonPath('data.total_items', 1);
    }
}
