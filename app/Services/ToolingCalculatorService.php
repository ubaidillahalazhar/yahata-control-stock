<?php

namespace App\Services;

use App\Models\ToolsInventory;

/**
 * Replikasi 1:1 rumus sheet "control stock" pada file `yahata tools (1).xlsx`.
 *
 * Perilaku pembulatan meniru Excel:
 *  - ROUND   : half away from zero, dihitung pada presisi 15 digit signifikan.
 *  - ROUNDUP : selalu menjauhi nol.
 *
 * Class ini murni (tanpa query database) sehingga dapat dipakai ulang oleh
 * API Resource, Export Excel, Artisan command, maupun unit test.
 */
final class ToolingCalculatorService
{
    public const FORECAST_MONTHS = 5;

    /** Kolom R memakai bulan ke-2 s.d. ke-5 (N:Q). Bulan pertama (kolom M) diabaikan. */
    public const FORECAST_AVG_START_INDEX = 1;

    /** AD = (AC / 20) * AN → asumsi 20 hari kerja per bulan. */
    public const WORKING_DAYS_PER_MONTH = 20;

    /** AE = AC * 20%. */
    public const FLUCTUATION_RATE = 0.2;

    /**
     * AF = IF(L <= 400000, "1", "0").
     * Header Excel menulis "< 500.000 Pcs", tetapi rumus aktual mengevaluasi <= 400000.
     */
    public const ADDITIONAL_SAFETY_MAX_LIFETIME = 400000;

    public const STATUS_SECURE = 'SECURE';

    public const STATUS_ORDER = 'ORDER';

    public const CHECK_OK = 'OK';

    public const CHECK_FAILED = 'CEK FORMULA';

    /**
     * Hitung seluruh kolom kalkulasi untuk satu item tooling.
     */
    public function forTool(ToolsInventory $tool): array
    {
        return $this->calculate(
            currentStock: (int) $tool->current_stock,
            lifeTime: (int) $tool->life_time,
            forecasts: (array) ($tool->forecasts ?? []),
            leadTimeDays: (int) $tool->lead_time_days,
        );
    }

    /**
     * @param  int  $currentStock  Kolom K
     * @param  int  $lifeTime  Kolom L
     * @param  array<int, mixed>  $forecasts  Kolom M..Q (5 bulan, boleh berisi string '0')
     * @param  int  $leadTimeDays  Kolom AN
     * @return array{
     *     forecasts: list<float>,
     *     forecast_avg: float,
     *     monthly_need: list<float>,
     *     monthly_balance: list<float>,
     *     tooling_need_a: float,
     *     safety_stock_lead_time_b: int,
     *     safety_stock_fluct_c: float,
     *     safety_stock_add_d: int,
     *     min_stock_e: int,
     *     max_total_stand_by: int,
     *     status_info: string,
     *     est_prod_qty_min: int,
     *     est_prod_qty_stand_by: int,
     *     koreksi_min_stock: string,
     *     koreksi_stand_by: string,
     * }
     */
    public function calculate(int $currentStock, int $lifeTime, array $forecasts, int $leadTimeDays): array
    {
        $forecasts = self::normalizeForecasts($forecasts);

        // (1) R = AVERAGEIF(N:Q, ">0")
        $forecastAvg = self::forecastAverage($forecasts);

        // (2) S..W = ROUND(Forecast_i / $L, 2)
        $monthlyNeed = array_map(
            static fn (float $qty): float => self::perLifeTime($qty, $lifeTime),
            $forecasts,
        );

        // (3) X..AB = ROUND($K - Need_i, 2)
        $monthlyBalance = array_map(
            static fn (float $need): float => self::excelRound($currentStock - $need, 2),
            $monthlyNeed,
        );

        // (4) AC = ROUND(R / L, 2)
        $toolingNeedA = self::perLifeTime($forecastAvg, $lifeTime);

        // (5) AD = ROUNDUP((AC / 20) * AN, 0)
        $safetyStockLeadTimeB = (int) self::excelRoundUp(
            ($toolingNeedA / self::WORKING_DAYS_PER_MONTH) * $leadTimeDays,
            0,
        );

        // (6) AE = AC * 20%
        $safetyStockFluctC = self::excelPrecision($toolingNeedA * self::FLUCTUATION_RATE);

        // (7) AF = IF(L <= 400000, 1, 0)
        $safetyStockAddD = $lifeTime <= self::ADDITIONAL_SAFETY_MAX_LIFETIME ? 1 : 0;

        // (8) AG = ROUNDUP(AC + AF, 0)
        $minStockE = (int) self::excelRoundUp($toolingNeedA + $safetyStockAddD, 0);

        // (9) AH = ROUNDUP(AD + AE + AF, 0)
        $maxTotalStandBy = (int) self::excelRoundUp(
            $safetyStockLeadTimeB + $safetyStockFluctC + $safetyStockAddD,
            0,
        );

        // (10) AI = IF(K >= AG, "SECURE", "ORDER")
        $statusInfo = $currentStock >= $minStockE ? self::STATUS_SECURE : self::STATUS_ORDER;

        // (11) AJ..AM — estimasi kapasitas & validasi formula
        $estProdQtyMin = $minStockE * $lifeTime;
        $estProdQtyStandBy = $maxTotalStandBy * $lifeTime;

        return [
            'forecasts' => $forecasts,
            'forecast_avg' => $forecastAvg,
            'monthly_need' => $monthlyNeed,
            'monthly_balance' => $monthlyBalance,
            'tooling_need_a' => $toolingNeedA,
            'safety_stock_lead_time_b' => $safetyStockLeadTimeB,
            'safety_stock_fluct_c' => $safetyStockFluctC,
            'safety_stock_add_d' => $safetyStockAddD,
            'min_stock_e' => $minStockE,
            'max_total_stand_by' => $maxTotalStandBy,
            'status_info' => $statusInfo,
            'est_prod_qty_min' => $estProdQtyMin,
            'est_prod_qty_stand_by' => $estProdQtyStandBy,
            'koreksi_min_stock' => $estProdQtyMin >= $forecastAvg ? self::CHECK_OK : self::CHECK_FAILED,
            'koreksi_stand_by' => $estProdQtyStandBy >= $forecastAvg ? self::CHECK_OK : self::CHECK_FAILED,
        ];
    }

    /**
     * Pastikan forecast selalu berisi tepat 5 angka (string '0' / kosong → 0).
     *
     * @param  array<int, mixed>  $forecasts
     * @return list<float>
     */
    public static function normalizeForecasts(array $forecasts): array
    {
        $values = array_slice(array_values($forecasts), 0, self::FORECAST_MONTHS);
        $values = array_pad($values, self::FORECAST_MONTHS, 0);

        return array_map(static fn (mixed $value): float => self::toNumber($value), $values);
    }

    /**
     * R = AVERAGEIF(N:Q, ">0") — bulan ke-2 s.d. ke-5. Jika tidak ada nilai > 0 hasilnya 0.
     *
     * @param  list<float>  $forecasts
     */
    public static function forecastAverage(array $forecasts): float
    {
        $window = array_slice($forecasts, self::FORECAST_AVG_START_INDEX, self::FORECAST_MONTHS - 1);
        $positives = array_values(array_filter($window, static fn (float $value): bool => $value > 0));

        if ($positives === []) {
            return 0.0;
        }

        return self::excelPrecision(array_sum($positives) / count($positives));
    }

    /**
     * Cast nilai sel Excel ke angka. Teks angka ('0', ' 1200 ') di-cast, teks lain → 0.
     */
    public static function toNumber(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : 0.0;
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        if (is_string($value)) {
            $clean = str_replace([' ', "\u{00A0}"], '', trim($value));

            return is_numeric($clean) ? (float) $clean : 0.0;
        }

        return 0.0;
    }

    /**
     * Excel ROUND(): half away from zero.
     */
    public static function excelRound(float $value, int $digits = 0): float
    {
        $factor = 10 ** $digits;
        $scaled = self::excelPrecision($value * $factor);
        $rounded = $scaled >= 0 ? floor($scaled + 0.5) : -floor(-$scaled + 0.5);

        return self::excelPrecision($rounded / $factor);
    }

    /**
     * Excel ROUNDUP(): selalu dibulatkan menjauhi nol.
     */
    public static function excelRoundUp(float $value, int $digits = 0): float
    {
        $factor = 10 ** $digits;
        $scaled = self::excelPrecision($value * $factor);
        $rounded = $scaled >= 0 ? ceil($scaled) : -ceil(-$scaled);

        return self::excelPrecision($rounded / $factor);
    }

    /**
     * Excel menyimpan angka dengan presisi 15 digit signifikan. Normalisasi ini
     * menghilangkan noise floating point (mis. 2.0000000000000004) sebelum pembulatan,
     * sehingga ROUNDUP tidak "meloncat" ke bilangan bulat berikutnya.
     */
    public static function excelPrecision(float $value): float
    {
        if ($value == 0.0 || ! is_finite($value)) {
            return 0.0;
        }

        return (float) sprintf('%.15G', $value);
    }

    /**
     * ROUND(qty / life_time, 2). Life time 0 menghasilkan #DIV/0! di Excel; di sistem bernilai 0.
     */
    private static function perLifeTime(float $qty, int $lifeTime): float
    {
        if ($lifeTime <= 0) {
            return 0.0;
        }

        return self::excelRound($qty / $lifeTime, 2);
    }
}
