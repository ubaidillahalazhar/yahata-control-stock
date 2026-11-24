<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tool_usage_logs', function (Blueprint $table) {
            $table->string('batch_no', 40)->nullable()->after('notes')->index()
                ->comment('Nomor batch import Excel (PLT-...); null = input manual dari aplikasi');
        });
    }

    public function down(): void
    {
        Schema::table('tool_usage_logs', function (Blueprint $table) {
            $table->dropIndex(['batch_no']);
            $table->dropColumn('batch_no');
        });
    }
};
