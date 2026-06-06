<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 10); // BTC | ETH
            $table->enum('side', ['buy', 'sell']);
            $table->decimal('price', 18, 2);
            $table->decimal('amount', 18, 8);

            // Amounts reserved for this order (for safe cancel/release)
            $table->decimal('locked_usd', 18, 2)->default(0);   // only for BUY
            $table->decimal('locked_asset', 18, 8)->default(0); // only for SELL

            // Assessment-required status values: open=1, filled=2, cancelled=3.
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();

            $table->index(['symbol', 'side', 'status']);
            $table->index(['symbol', 'status', 'id']); // FIFO per symbol
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
