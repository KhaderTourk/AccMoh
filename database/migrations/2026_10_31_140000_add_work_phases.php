<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->boolean('has_phases')->default(false)->after('billing');
        });
        Schema::table('client_services', function (Blueprint $table) {
            $table->string('work_phase', 20)->nullable()->after('service_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('client_services', fn (Blueprint $table) => $table->dropColumn('work_phase'));
        Schema::table('service_types', fn (Blueprint $table) => $table->dropColumn('has_phases'));
    }
};
