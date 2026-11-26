<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 — shot count (angka counter mesin) terhubung ke life time.
 * Counter mesin di-nol-kan setiap ganti tool → angka counter = shot sejak tool dipasang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            // Boleh kosong: laporan "count belum dibawa" diisi belakangan.
            $table->unsignedBigInteger('actual_shot_count')->nullable()->default(null)->change();
            $table->unsignedBigInteger('system_shot_count')->nullable()->after('actual_shot_count')
                ->comment('Snapshot life_used sistem saat laporan dibuat');
            $table->unsignedInteger('life_time_snapshot')->nullable()->after('system_shot_count');
            $table->bigInteger('shot_variance')->nullable()->after('life_time_snapshot')
                ->comment('actual_shot_count - system_shot_count (negatif = Excel kelebihan input)');
            $table->boolean('count_pending')->default(false)->index()->after('shot_variance');
            $table->timestamp('count_filled_at')->nullable()->after('count_pending');
            $table->timestamp('overdue_notified_at')->nullable()->after('count_filled_at');
            $table->boolean('needs_review')->default(false)->index()->after('overdue_notified_at');
            $table->foreignId('reviewed_by')->nullable()->after('needs_review')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('review_note')->nullable()->after('reviewed_at');
        });

        Schema::table('tool_usage_logs', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->index()->after('type')
                ->comment('manual | excel | damage');
            $table->foreignId('damage_report_id')->nullable()->after('batch_no')->constrained('damage_reports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tool_usage_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('damage_report_id');
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });

        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['count_pending']);
            $table->dropIndex(['needs_review']);
            $table->dropColumn([
                'system_shot_count', 'life_time_snapshot', 'shot_variance', 'count_pending',
                'count_filled_at', 'overdue_notified_at', 'needs_review', 'reviewed_at', 'review_note',
            ]);
        });
    }
};
