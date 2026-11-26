<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Koreksi input Pemakaian Life Time + batas waktu 24 jam untuk PIC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tool_usage_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('pic_name')->constrained('users')->nullOnDelete()
                ->comment('Akun yang menginput baris ini');
            $table->foreignId('corrects_id')->nullable()->after('damage_report_id')->constrained('tool_usage_logs')->nullOnDelete()
                ->comment('Baris lama yang dikoreksi (dibatalkan) oleh baris ini');
            $table->timestamp('first_input_at')->nullable()->after('corrects_id')
                ->comment('Waktu input pertama dalam rantai koreksi (dasar batas 24 jam PIC)');
        });
    }

    public function down(): void
    {
        Schema::table('tool_usage_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corrects_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('first_input_at');
        });
    }
};
