<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_no', 30)->nullable()->unique();
            $table->foreignId('tool_id')->constrained('tools_inventory')->restrictOnDelete();
            $table->string('operator_name', 100)->index();
            $table->unsignedTinyInteger('shift');
            $table->unsignedInteger('qty_replaced');
            $table->unsignedBigInteger('actual_shot_count')->default(0);
            $table->string('damage_reason', 30)->index();
            $table->string('action', 30)->index();
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->text('notes')->nullable();
            $table->timestamp('reported_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_reports');
    }
};
