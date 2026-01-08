<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buy_order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('sell_order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('symbol', 10);
            $table->decimal('price', 18, 2);
            $table->decimal('amount', 18, 8);
            $table->decimal('usd_value', 18, 2);
            $table->decimal('fee_usd', 18, 2);
            $table->timestamps();

            $table->index(['symbol', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
