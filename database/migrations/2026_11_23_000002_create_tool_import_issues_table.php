<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris Excel master yang belum lengkap pada upload TERAKHIR (isi tabel diganti setiap import).
     */
    public function up(): void
    {
        Schema::create('tool_import_issues', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 50)->index()->comment('Referensi import (IMP-...)');
            $table->unsignedInteger('excel_row');
            $table->string('tools_name_yes')->nullable()->index()->comment('Kolom H (boleh kosong)');
            $table->foreignId('tool_id')->nullable()->constrained('tools_inventory')->nullOnDelete()
                ->comment('Terisi bila tool sudah ada di tabel utama (update ditahan)');
            $table->string('status', 20)->index()->comment('NEW_HELD | UPDATE_HELD | DUPLICATE');
            $table->json('problems')->comment('[{field, column, label, reason}]');
            $table->json('row_data')->comment('Nilai baris Excel untuk ditampilkan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_import_issues');
    }
};
