<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات بسيطة (مفتاح وقيمة) لكل مستأجر: سعر الدولار والدينار اليوم، وحد فرق مطابقة ميتا.
 * جدول جديد بس، بدون لمس أي جدول موجود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_settings')) {
            return;
        }
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        // لا شيء: ما بنحذف جداول عشان ما تضيع بيانات.
    }
};
