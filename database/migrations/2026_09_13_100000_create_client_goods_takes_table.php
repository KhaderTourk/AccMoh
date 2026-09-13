<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_goods_takes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->decimal('amount', 14, 2);
            $table->decimal('source_amount', 14, 2)->nullable();
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->foreignId('fx_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->date('taken_on');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'client_id']);
            $table->index(['client_id', 'currency_id']);
            $table->index('taken_on');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_goods_takes');
    }
};
