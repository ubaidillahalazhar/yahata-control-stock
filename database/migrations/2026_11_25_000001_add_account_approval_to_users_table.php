<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Akun PIC: registrasi mandiri → disetujui admin, login username + PIN.
 * Kolom `nik` = NIK karyawan asli untuk SSO Superapps (saat ini hanya admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->unique()->after('name')
                ->comment('Login lokal PIC (huruf kecil, unik)');
            $table->string('nik', 30)->nullable()->unique()->after('username')
                ->comment('NIK karyawan, identik dengan Superapps (SSO)');
            $table->string('status', 20)->default('pending')->index()->after('role')
                ->comment('pending | active | rejected | disabled');
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('rejection_reason')->nullable()->after('approved_at');
            $table->boolean('must_change_password')->default(false)->after('password')
                ->comment('true setelah admin reset PIN/password → wajib ganti saat login');
            $table->string('email')->nullable()->change();
        });

        // Akun yang sudah ada sebelum fitur approval tetap bisa login.
        DB::table('users')->update(['status' => 'active', 'approved_at' => now()]);

        Schema::table('damage_reports', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('tool_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropUnique(['username']);
            $table->dropUnique(['nik']);
            $table->dropIndex(['status']);
            $table->dropColumn(['username', 'nik', 'status', 'approved_at', 'rejection_reason', 'must_change_password']);
        });
    }
};
