<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_texts', function (Blueprint $table) {
            $table->id();
            $table->string('group', 60)->index();
            $table->string('key', 120)->unique();
            $table->string('label_ar', 200)->nullable();
            $table->string('label_en', 200)->nullable();
            $table->text('value_ar')->nullable();
            $table->text('value_en')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_texts');
    }
};
