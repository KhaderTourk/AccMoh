<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «تثبيت» ★ بقوائم الزبائن والموظفين والموردين والأشخاص.
 * إضافة عمود بس، بدون أي تعديل أو حذف لبيانات موجودة.
 */
return new class extends Migration
{
    private array $tables = ['clients', 'vendors', 'family_members'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (Schema::hasTable($name) && ! Schema::hasColumn($name, 'is_pinned')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->boolean('is_pinned')->default(false);
                });
            }
        }
    }

    public function down(): void
    {
        // لا شيء: ما بنحذف أعمدة عشان ما تضيع بيانات.
    }
};
