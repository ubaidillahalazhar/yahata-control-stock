<?php

use App\Services\ToolingMasterSyncService;
use Illuminate\Support\Facades\Artisan;

/*
 * Import master tooling dari terminal:
 *   php artisan tooling:import "storage/app/yahata tools (1).xlsx"
 *   php artisan tooling:import file.xlsx --sync-stock --pic="Admin Gudang"
 */
Artisan::command('tooling:import {path : Path file .xlsx} {--sync-stock : Samakan stok item lama dengan kolom K} {--pic= : Nama PIC}', function (ToolingMasterSyncService $sync) {
    $path = (string) $this->argument('path');
    $absolute = is_file($path) ? realpath($path) : base_path($path);

    if (! $absolute || ! is_file($absolute)) {
        $this->error("File tidak ditemukan: {$path}");

        return 1;
    }

    $result = $sync->import($absolute, (bool) $this->option('sync-stock'), $this->option('pic') ?: 'ARTISAN');

    $this->info("Referensi: {$result['reference_no']}");
    $this->table(['Baru', 'Diperbarui', 'Dilewati', 'Stok disesuaikan', 'Log stok awal'], [[
        $result['created'], $result['updated'], $result['skipped'], $result['stock_adjusted'], $result['initial_stock_logged'],
    ]]);

    foreach ($result['warnings'] as $warning) {
        $this->warn($warning);
    }

    foreach ($result['errors'] as $error) {
        $this->error($error);
    }

    return 0;
})->purpose('Import master tooling dari file Excel "control stock"');
