<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 80);
            $table->string('status', 20)->default('completed');
            $table->json('request_payload');
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->nullableMorphs('related');
            $table->timestamp('client_timestamp')->nullable();
            $table->string('device_id', 100)->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};
