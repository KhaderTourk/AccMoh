<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('source_amount', 14, 2)->nullable();
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->foreignId('fx_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->date('charge_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'vendor_id']);
            $table->index(['vendor_id', 'currency_id']);
            $table->index('charge_date');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_charges');
    }
};
