<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_transfers', function (Blueprint $table) {
            $table->foreignId('to_currency_id')->nullable()->after('currency_id')->constrained('currencies')->nullOnDelete();
            $table->decimal('to_amount', 14, 2)->nullable()->after('amount');
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('to_amount');
        });
    }

    public function down(): void
    {
        Schema::table('fund_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('to_currency_id');
            $table->dropColumn(['to_amount', 'exchange_rate']);
        });
    }
};
