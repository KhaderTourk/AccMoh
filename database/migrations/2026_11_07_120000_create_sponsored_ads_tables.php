<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تاب «الإعلانات الممولة» (تسليم 1/11 §1): الحملات، فواتير ميتا، ومطابقة الشهر.
 * جداول جديدة بس، بدون لمس أي جدول موجود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ad_campaigns')) {
            Schema::create('ad_campaigns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->index();
                $table->foreignId('client_id')->constrained();
                $table->char('period', 7)->index();
                $table->boolean('on_facebook')->default(false);
                $table->boolean('on_instagram')->default(false);
                $table->decimal('spend_usd', 14, 2);
                $table->decimal('sell_rate', 10, 4);
                $table->text('notes')->nullable();
                $table->foreignId('client_service_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasTable('meta_invoices')) {
            Schema::create('meta_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->index();
                $table->date('invoice_date');
                $table->decimal('amount_usd', 14, 2);
                $table->string('meta_ref', 100)->nullable();
                $table->foreignId('fund_id')->nullable();
                $table->foreignId('payment_method_id')->nullable();
                $table->decimal('usd_rate', 10, 4);
                $table->foreignId('cash_payment_id')->nullable();
                $table->foreignId('fund_transfer_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasTable('ad_months')) {
            Schema::create('ad_months', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->index();
                $table->char('period', 7);
                $table->string('status', 20)->default('draft');
                $table->decimal('meta_balance_usd', 14, 2)->nullable();
                $table->decimal('expected_usd', 14, 2)->nullable();
                $table->decimal('settlement_usd', 14, 2)->nullable();
                $table->foreignId('vendor_charge_id')->nullable();
                $table->foreignId('settlement_ref_id')->nullable();
                $table->string('settlement_ref_type', 40)->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'period']);
            });
        }
    }

    public function down(): void
    {
        // لا شيء: ما بنحذف جداول عشان ما تضيع بيانات.
    }
};
