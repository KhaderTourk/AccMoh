<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_loans', function (Blueprint $table) {
            $table->decimal('source_amount', 14, 2)->nullable()->after('amount');
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('source_amount');
            $table->foreignId('fx_currency_id')->nullable()->after('exchange_rate')->constrained('currencies')->nullOnDelete();
        });

        Schema::table('family_loan_repayments', function (Blueprint $table) {
            $table->decimal('source_amount', 14, 2)->nullable()->after('amount');
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('source_amount');
            $table->foreignId('fx_currency_id')->nullable()->after('exchange_rate')->constrained('currencies')->nullOnDelete();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('type'); // worker | supplier
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'type']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('expense_category_id')->constrained('vendors')->nullOnDelete();
        });

        if (Schema::hasTable('tenants') && Schema::hasTable('expense_categories')) {
            $now = now();
            $tenantIds = \Illuminate\Support\Facades\DB::table('tenants')->pluck('id');
            foreach ($tenantIds as $tenantId) {
                foreach ([
                    ['name' => 'أجور عمال', 'fund_slug' => 'business', 'sort_order' => 14],
                    ['name' => 'مشتريات موردين', 'fund_slug' => 'business', 'sort_order' => 15],
                ] as $category) {
                    $exists = \Illuminate\Support\Facades\DB::table('expense_categories')
                        ->where('tenant_id', $tenantId)
                        ->where('name', $category['name'])
                        ->where('fund_slug', $category['fund_slug'])
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                    \Illuminate\Support\Facades\DB::table('expense_categories')->insert([
                        'tenant_id' => $tenantId,
                        'name' => $category['name'],
                        'fund_slug' => $category['fund_slug'],
                        'sort_order' => $category['sort_order'],
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_id');
        });

        Schema::dropIfExists('vendors');

        Schema::table('family_loan_repayments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fx_currency_id');
            $table->dropColumn(['source_amount', 'exchange_rate']);
        });

        Schema::table('family_loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fx_currency_id');
            $table->dropColumn(['source_amount', 'exchange_rate']);
        });
    }
};
