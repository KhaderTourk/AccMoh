<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Monthly salary for workers (#7 §7): prefills the «مستحق» window; nothing is billed automatically.
        Schema::table('vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('vendors', 'salary_amount')) {
                $table->decimal('salary_amount', 14, 2)->nullable()->after('work_description');
                $table->date('salary_from')->nullable()->after('salary_amount');
                $table->date('salary_to')->nullable()->after('salary_from');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['salary_amount', 'salary_from', 'salary_to']);
        });
    }
};
