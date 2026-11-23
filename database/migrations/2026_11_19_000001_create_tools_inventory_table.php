<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools_inventory', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('no')->nullable()->comment('Col A');
            $table->string('customer')->nullable()->comment('Col B');
            $table->string('machine_name')->nullable()->index()->comment('Col C');
            $table->string('step', 50)->nullable()->comment('Col D (trimmed)');
            $table->string('tools_name')->comment('Col E');
            $table->string('yes_name', 50)->nullable()->comment('Col F');
            $table->string('part_name')->nullable()->comment('Col G');
            $table->string('tools_name_yes')->unique()->comment('Col H - unique identifier');
            $table->string('rack_location', 50)->nullable()->index()->comment('Col I');
            $table->string('part_no', 100)->nullable()->index()->comment('Col J');
            $table->integer('current_stock')->default(0)->comment('Col K - stok fisik');
            $table->unsignedBigInteger('life_time')->comment('Col L - Pcs');
            $table->json('forecasts')->comment('Col M..Q - 5 bulan');
            $table->json('forecast_months')->nullable()->comment('Baris 2 M..Q - tanggal bulan forecast');
            $table->unsignedInteger('lead_time_days')->default(30)->comment('Col AN');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tools_inventory');
    }
};
