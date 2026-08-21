<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_audit_logs')) {
            return;
        }

        // Legacy MySQL-only repair; SQLite / fresh installs already have nullable columns
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $cols = Schema::getColumnListing('financial_audit_logs');

        if (in_array('model_type', $cols, true)) {
            DB::statement('ALTER TABLE financial_audit_logs MODIFY model_type VARCHAR(191) NULL');
        }
        if (in_array('model_id', $cols, true)) {
            DB::statement('ALTER TABLE financial_audit_logs MODIFY model_id BIGINT UNSIGNED NULL');
        }
        if (in_array('entity_type', $cols, true)) {
            DB::statement('ALTER TABLE financial_audit_logs MODIFY entity_type VARCHAR(191) NULL');
        }
        if (in_array('entity_id', $cols, true)) {
            DB::statement('ALTER TABLE financial_audit_logs MODIFY entity_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        //
    }
};
