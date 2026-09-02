<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('expense_categories')
            ->whereIn('name', ['أجور أبناء الشركة', 'أجور عمال'])
            ->update(['name' => 'أجور الموظفين']);
    }

    public function down(): void
    {
        DB::table('expense_categories')
            ->where('name', 'أجور الموظفين')
            ->update(['name' => 'أجور أبناء الشركة']);
    }
};
