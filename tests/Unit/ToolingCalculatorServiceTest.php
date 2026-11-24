<?php

namespace Tests\Unit;

use App\Services\ToolingCalculatorService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Membandingkan hasil ToolingCalculatorService dengan NILAI CACHE Excel
 * dari file asli `yahata tools (1).xlsx` (tests/Fixtures/control_stock_expected.json).
 */
class ToolingCalculatorServiceTest extends TestCase
{
    private const FLOAT_DELTA = 1e-9;

    public static function excelRows(): iterable
    {
        $fixture = json_decode((string) file_get_contents(__DIR__.'/../Fixtures/control_stock_expected.json'), true);

        foreach ($fixture['rows'] as $row) {
            yield "Excel baris {$row['excel_row']} — {$row['tools_name_yes']}" => [$row['input'], $row['expected']];
        }
    }

    #[Test]
    #[DataProvider('excelRows')]
    public function it_matches_every_excel_formula(array $input, array $expected): void
    {
        $result = (new ToolingCalculatorService)->calculate(
            (int) $input['current_stock'],
            (int) $input['life_time'],
            $input['forecasts'],
            (int) $input['lead_time_days'],
        );

        foreach ($expected as $key => $value) {
            if (is_array($value)) {
                $this->assertCount(count($value), $result[$key], $key);

                foreach ($value as $i => $item) {
                    $this->assertEqualsWithDelta($item, $result[$key][$i], self::FLOAT_DELTA, "{$key}[{$i}]");
                }

                continue;
            }

            if (is_string($value)) {
                $this->assertSame($value, $result[$key], $key);

                continue;
            }

            $this->assertEqualsWithDelta($value, $result[$key], self::FLOAT_DELTA, $key);
        }
    }

    #[Test]
    public function forecast_average_ignores_first_month_and_zero_values(): void
    {
        $this->assertSame(0.0, ToolingCalculatorService::forecastAverage([999999.0, 0.0, 0.0, 0.0, 0.0]));
        $this->assertSame(150.0, ToolingCalculatorService::forecastAverage([999999.0, 100.0, 0.0, 200.0, 0.0]));
    }

    #[Test]
    public function text_forecast_values_are_cast_to_numbers(): void
    {
        $this->assertSame([0.0, 1200.0, 0.0, 0.0, 0.0], ToolingCalculatorService::normalizeForecasts(['0', ' 1200 ', '', null]));
    }

    #[Test]
    public function rounding_follows_excel_semantics(): void
    {
        $this->assertSame(0.29, ToolingCalculatorService::excelRound(0.285, 2));
        $this->assertSame(-3.0, ToolingCalculatorService::excelRound(-2.5, 0));
        $this->assertSame(1.0, ToolingCalculatorService::excelRoundUp(0.02 * 50, 0));
        $this->assertSame(3.0, ToolingCalculatorService::excelRoundUp(2.955, 0));
        $this->assertSame(-2.0, ToolingCalculatorService::excelRoundUp(-1.2, 0));
    }

    #[Test]
    public function additional_safety_stock_uses_actual_formula_threshold(): void
    {
        $calc = new ToolingCalculatorService;

        $this->assertSame(1, $calc->calculate(1, 400000, [0, 1, 1, 1, 1], 30)['safety_stock_add_d']);
        $this->assertSame(0, $calc->calculate(1, 400001, [0, 1, 1, 1, 1], 30)['safety_stock_add_d']);
        // Header Excel "< 500.000" TIDAK dipakai: 450.000 tetap 0 sesuai rumus aktual.
        $this->assertSame(0, $calc->calculate(1, 450000, [0, 1, 1, 1, 1], 30)['safety_stock_add_d']);
    }

    #[Test]
    public function zero_life_time_does_not_divide_by_zero(): void
    {
        $result = (new ToolingCalculatorService)->calculate(5, 0, [100, 100, 100, 100, 100], 30);

        $this->assertSame(0.0, $result['tooling_need_a']);
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 0.0], $result['monthly_need']);
    }
}
