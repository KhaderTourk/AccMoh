<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeOperationalData extends Command
{
    protected $signature = 'accma:purge-operational-data {--force : تأكيد التنفيذ بدون سؤال}';

    protected $description = 'إفراغ العملاء والأفراد وجميع الحركات المالية مع الإبقاء على الكتالوج والمستخدمين';

    /** @var list<string> */
    protected array $tables = [
        'payment_allocations',
        'family_loan_repayment_items',
        'client_payments',
        'client_services',
        'family_loan_repayments',
        'family_loans',
        'expenses',
        'fund_transfers',
        'ledger_entries',
        'financial_audit_logs',
        'sync_operations',
        'clients',
        'family_members',
        'personal_access_tokens',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('سيتم حذف كل العملاء والعائلة والحركات المالية. هل أنت متأكد؟')) {
            $this->warn('تم الإلغاء.');

            return self::FAILURE;
        }

        $existing = array_values(array_filter(
            $this->tables,
            fn (string $table) => Schema::hasTable($table)
        ));

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($existing as $table) {
                DB::table($table)->delete();
                if (Schema::getConnection()->getDriverName() === 'mysql') {
                    DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
                }
                $this->line("cleared: {$table}");
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info('تم إفراغ البيانات غير الأساسية. بقي: المستخدمون، الأدوار، العملات، طرق الدفع، الصناديق، أنواع الخدمات، فئات المصروف.');

        return self::SUCCESS;
    }
}
