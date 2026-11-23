<?php

namespace Database\Seeders;

use App\Services\ToolingMasterSyncService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Isi master tooling dari file contoh `database/seeders/data/yahata_tools.xlsx`
     * (salinan `yahata tools (1).xlsx`, sheet "control stock").
     */
    public function run(ToolingMasterSyncService $sync): void
    {
        $path = database_path('seeders/data/yahata_tools.xlsx');

        if (! is_file($path)) {
            $this->command?->warn("File contoh tidak ditemukan: {$path}");

            return;
        }

        $result = $sync->import($path, false, 'SEEDER');

        $this->command?->info(sprintf(
            'Master tooling: %d item baru, %d diperbarui, %d dilewati (ref %s).',
            $result['created'],
            $result['updated'],
            $result['skipped'],
            $result['reference_no'],
        ));

        foreach ($result['warnings'] as $warning) {
            $this->command?->warn($warning);
        }
    }
}
