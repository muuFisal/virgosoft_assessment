<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // USD funds available for trading
            $table->decimal('balance', 18, 2)->default(0)->after('remember_token');
            // USD funds reserved for open BUY orders
            $table->decimal('locked_balance', 18, 2)->default(0)->after('balance');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['balance', 'locked_balance']);
        });
    }
};
