<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained('tools_inventory')->restrictOnDelete();
            $table->string('type', 30)->index()->comment('IN | OUT | OPNAME_ADJUST | DAMAGE_REPLACEMENT');
            $table->integer('qty_change')->comment('Positif = masuk, negatif = keluar');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->string('reference_no', 100)->nullable()->index();
            $table->string('pic_name', 100)->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('damage_report_id')->nullable()->constrained('damage_reports')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['tool_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
