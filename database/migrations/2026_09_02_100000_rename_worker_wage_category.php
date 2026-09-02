<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('expense_categories')
            ->where('name', 'أجور عمال')
            ->update(['name' => 'أجور أبناء الشركة']);
    }

    public function down(): void
    {
        DB::table('expense_categories')
            ->where('name', 'أجور أبناء الشركة')
            ->update(['name' => 'أجور عمال']);
    }
};
