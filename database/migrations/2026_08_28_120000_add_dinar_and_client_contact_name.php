<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clients') && ! Schema::hasColumn('clients', 'contact_name')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('contact_name')->nullable()->after('name');
            });
        }

        if (Schema::hasTable('currencies') && ! DB::table('currencies')->where('code', 'JOD')->exists()) {
            DB::table('currencies')->insert([
                'code' => 'JOD',
                'name' => 'دينار',
                'symbol' => 'د.أ',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'contact_name')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('contact_name');
            });
        }

        if (Schema::hasTable('currencies')) {
            DB::table('currencies')->where('code', 'JOD')->delete();
        }
    }
};
