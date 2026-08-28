<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_services', function (Blueprint $table) {
            $table->decimal('source_amount', 14, 2)->nullable()->after('amount');
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('source_amount');
            $table->foreignId('fx_currency_id')->nullable()->after('exchange_rate')->constrained('currencies')->nullOnDelete();
        });

        Schema::table('client_payments', function (Blueprint $table) {
            $table->decimal('source_amount', 14, 2)->nullable()->after('amount');
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('source_amount');
            $table->foreignId('fx_currency_id')->nullable()->after('exchange_rate')->constrained('currencies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fx_currency_id');
            $table->dropColumn(['source_amount', 'exchange_rate']);
        });

        Schema::table('client_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fx_currency_id');
            $table->dropColumn(['source_amount', 'exchange_rate']);
        });
    }
};
