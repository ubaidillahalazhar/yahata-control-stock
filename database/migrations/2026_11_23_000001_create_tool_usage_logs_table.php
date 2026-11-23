<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tools_inventory', function (Blueprint $table) {
            $table->unsignedBigInteger('life_used')->default(0)->after('life_time')
                ->comment('Cache: total pemakaian (pcs) sejak reset terakhir, dihitung dari tool_usage_logs');
        });

        Schema::create('tool_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained('tools_inventory')->restrictOnDelete();
            $table->string('type', 20)->index()->comment('USAGE | RESET');
            $table->date('usage_date')->comment('Tanggal pemakaian (boleh acak / mundur)');
            $table->unsignedBigInteger('qty')->default(0)->comment('Pcs terpakai (USAGE) / pcs yang di-nol-kan (RESET)');
            $table->unsignedBigInteger('life_used_before');
            $table->unsignedBigInteger('life_used_after');
            $table->string('pic_name', 100)->index();
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->string('cancelled_by', 100)->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['tool_id', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_usage_logs');

        Schema::table('tools_inventory', function (Blueprint $table) {
            $table->dropColumn('life_used');
        });
    }
};
