<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('business_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->boolean('is_platform_admin')->default(false)->after('is_super_admin');
        });

        $tenantTables = [
            'funds',
            'clients',
            'family_members',
            'service_types',
            'expense_categories',
            'client_services',
            'client_payments',
            'payment_allocations',
            'family_loans',
            'family_loan_repayments',
            'family_loan_repayment_items',
            'expenses',
            'fund_transfers',
            'ledger_entries',
            'financial_audit_logs',
            'sync_operations',
        ];

        foreach ($tenantTables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('tenant_id')->nullable()->after('id');
            });
        }

        $now = now();
        $tenantId = DB::table('tenants')->insertGetId([
            'name' => 'نسخة محمد',
            'slug' => 'mohmed',
            'business_enabled' => true,
            'is_active' => true,
            'owner_user_id' => null,
            'notes' => 'النسخة الافتراضية بعد الترقية متعددة المستأجرين',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($tenantTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            }
        }

        DB::table('users')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);

        $ownerId = DB::table('users')->where('email', 'admin@mohmed.brand')->value('id')
            ?? DB::table('users')->orderBy('id')->value('id');
        if ($ownerId) {
            DB::table('tenants')->where('id', $tenantId)->update(['owner_user_id' => $ownerId]);
            DB::table('users')->where('id', $ownerId)->update([
                'tenant_id' => $tenantId,
                'is_platform_admin' => false,
            ]);
        }

        foreach ($tenantTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('funds')) {
            Schema::table('funds', function (Blueprint $table) {
                try {
                    $table->dropUnique(['slug']);
                } catch (\Throwable $e) {
                    try {
                        $table->dropUnique('funds_slug_unique');
                    } catch (\Throwable $e2) {
                    }
                }
            });
            Schema::table('funds', function (Blueprint $table) {
                $table->unique(['tenant_id', 'slug']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('funds')) {
            Schema::table('funds', function (Blueprint $table) {
                try {
                    $table->dropUnique(['tenant_id', 'slug']);
                } catch (\Throwable $e) {
                }
            });
        }

        $tenantTables = [
            'sync_operations',
            'financial_audit_logs',
            'ledger_entries',
            'fund_transfers',
            'expenses',
            'family_loan_repayment_items',
            'family_loan_repayments',
            'family_loans',
            'payment_allocations',
            'client_payments',
            'client_services',
            'expense_categories',
            'service_types',
            'family_members',
            'clients',
            'funds',
        ];

        foreach ($tenantTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropForeign(['tenant_id']);
                    $blueprint->dropColumn('tenant_id');
                });
            }
        }

        if (Schema::hasTable('funds')) {
            Schema::table('funds', function (Blueprint $table) {
                $table->unique('slug');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'is_platform_admin']);
        });

        Schema::dropIfExists('tenants');
    }
};
