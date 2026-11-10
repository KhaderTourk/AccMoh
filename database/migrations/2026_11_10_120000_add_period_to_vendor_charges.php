<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // شهر الراتب أو الاشتراك الشهري («Y-m») عشان ما ينضاف نفس الشهر مرتين (قرار 10/11/2026).
        Schema::table('vendor_charges', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_charges', 'period')) {
                $table->char('period', 7)->nullable()->after('charge_date');
                $table->index(['vendor_id', 'period']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_charges', function (Blueprint $table) {
            $table->dropIndex(['vendor_id', 'period']);
            $table->dropColumn('period');
        });
    }
};
