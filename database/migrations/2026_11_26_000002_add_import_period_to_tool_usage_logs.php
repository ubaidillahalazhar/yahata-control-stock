<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Import Machine Result per periode (bulan). Upload ulang periode yang sama membatalkan
 * baris import sebelumnya untuk periode itu (dicari lewat source + import_period).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tool_usage_logs', function (Blueprint $table) {
            $table->string('import_period', 7)->nullable()->after('batch_no')
                ->comment('Y-m periode import Machine Result');
            $table->index(['source', 'import_period']);
        });
    }

    public function down(): void
    {
        Schema::table('tool_usage_logs', function (Blueprint $table) {
            $table->dropIndex(['source', 'import_period']);
            $table->dropColumn('import_period');
        });
    }
};
