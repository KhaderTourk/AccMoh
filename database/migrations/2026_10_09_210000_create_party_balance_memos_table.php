<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_balance_memos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('party_type');
            $table->unsignedBigInteger('party_id');
            $table->string('direction');
            $table->string('reason');
            $table->string('leg')->default('single');
            $table->uuid('group_id')->nullable();
            $table->decimal('amount', 14, 2);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->date('memo_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'party_type', 'party_id']);
            $table->index(['party_type', 'party_id', 'currency_id']);
            $table->index('memo_date');
            $table->index('group_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_balance_memos');
    }
};
