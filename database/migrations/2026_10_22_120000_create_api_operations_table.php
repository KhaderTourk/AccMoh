<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->nullable();
            $table->string('token_name')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->string('route_name')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->json('request')->nullable();
            // [{op: created|updated|deleted|pivot, model, table, key_name, key, before, after}]
            $table->longText('changes');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->foreignId('undone_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'undone_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_operations');
    }
};
