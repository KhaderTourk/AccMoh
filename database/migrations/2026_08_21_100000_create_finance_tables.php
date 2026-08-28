<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('name');
                $table->string('symbol', 10);
                $table->unsignedTinyInteger('decimal_places')->default(2);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug', 50)->unique();
                $table->string('icon', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('funds')) {
            Schema::create('funds', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug', 50)->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('clients')) {
            Schema::create('clients', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone', 50)->nullable();
                $table->string('email')->nullable();
                $table->string('company_name')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['name', 'is_active']);
            });
        }

        if (! Schema::hasTable('family_members')) {
            Schema::create('family_members', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('relationship')->nullable();
                $table->string('phone', 50)->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['name', 'is_active']);
            });
        }

        if (! Schema::hasTable('service_types')) {
            Schema::create('service_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('default_price', 14, 2)->nullable();
                $table->foreignId('default_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('expense_categories')) {
            Schema::create('expense_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('fund_slug', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_services')) {
            Schema::create('client_services', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained()->restrictOnDelete();
                $table->foreignId('service_type_id')->nullable()->constrained()->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->decimal('amount', 14, 2);
                $table->foreignId('currency_id')->constrained()->restrictOnDelete();
                $table->date('service_date');
                $table->string('status', 30)->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['client_id', 'currency_id', 'status']);
                $table->index('service_date');
            });
        }

        if (! Schema::hasTable('client_payments')) {
            Schema::create('client_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained()->restrictOnDelete();
                $table->foreignId('fund_id')->constrained()->restrictOnDelete();
                $table->decimal('amount', 14, 2);
                $table->foreignId('currency_id')->constrained()->restrictOnDelete();
                $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
                $table->string('payer_name')->nullable();
                $table->date('payment_date');
                $table->text('notes')->nullable();
                $table->uuid('ledger_group_id');
                $table->boolean('is_reversed')->default(false);
                $table->timestamp('reversed_at')->nullable();
                $table->timestamps();
                $table->index(['client_id', 'currency_id', 'is_reversed'], 'cp_client_currency_reversed_idx');
                $table->index('payment_date');
                $table->index('ledger_group_id');
            });
        }

        if (! Schema::hasTable('payment_allocations')) {
            Schema::create('payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_payment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_service_id')->constrained()->restrictOnDelete();
                $table->decimal('allocated_amount', 14, 2);
                $table->foreignId('currency_id')->constrained()->restrictOnDelete();
                $table->timestamps();
                $table->index(['client_service_id', 'client_payment_id'], 'pa_service_payment_idx');
            });
        }

        if (! Schema::hasTable('family_loans')) {
            Schema::create('family_loans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('family_member_id')->constrained()->restrictOnDelete();
                $table->foreignId('fund_id')->constrained()->restrictOnDelete();
                $table->string('direction', 20);
                $table->decimal('amount', 14, 2);
                $table->foreignId('currency_id')->constrained()->restrictOnDelete();
                $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
                $table->date('loan_date');
                $table->string('status', 20)->default('open');
                $table->text('notes')->nullable();
                $table->uuid('ledger_group_id');
                $table->boolean('is_reversed')->default(false);
                $table->timestamp('reversed_at')->nullable();
                $table->timestamps();
                $table->index(['family_member_id', 'direction', 'currency_id', 'status'], 'fl_member_dir_cur_status_idx');
                $table->index('loan_date');
            });
        }

        if (! Schema::hasTable('family_loan_repayments')) {
            Schema::create('family_loan_repayments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('family_member_id')->constrained()->restrictOnDelete();
                $table->foreignId('fund_id')->constrained()->restrictOnDelete();
                $table->string('direction', 20);
                $table->decimal('amount', 14, 2);
                $table->foreignId('currency_id')->constrained()->restrictOnDelete();
                $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
                $table->date('repayment_date');
                $table->text('notes')->nullable();
                $table->uuid('ledger_group_id');
                $table->boolean('is_reversed')->default(false);
                $table->timestamp('reversed_at')->nullable();
                $table->timestamps();
                $table->index(['family_member_id', 'currency_id', 'is_reversed'], 'flr_member_currency_reversed_idx');
            });
        }

        if (! Schema::hasTable('family_loan_repayment_items')) {
            Schema::create('family_loan_repayment_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('family_loan_repayment_id')->constrained('family_loan_repayments')->cascadeOnDelete();
                $table->foreignId('family_loan_id')->constrained()->restrictOnDelete();
                $table->decimal('allocated_amount', 14, 2);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fund_id')->constrained()->restrictOnDelete();
                $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
                $table->string('description');
                $table->decimal('amount', 14, 2);
                $table->foreignId('currency_id')->constrained()->restrictOnDelete();
                $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
                $table->date('expense_date');
                $table->string('payee')->nullable();
                $table->text('notes')->nullable();
                $table->uuid('ledger_group_id');
                $table->boolean('is_reversed')->default(false);
                $table->timestamp('reversed_at')->nullable();
                $table->timestamps();
                $table->index(['fund_id', 'currency_id', 'expense_date']);
            });
        }

        if (! Schema::hasTable('fund_transfers')) {
            Schema::create('fund_transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fund_id')->constrained()->restrictOnDelete();
                $table->foreignId('from_payment_method_id')->constrained('payment_methods')->restrictOnDelete();
                $table->foreignId('to_payment_method_id')->constrained('payment_methods')->restrictOnDelete();
                $table->decimal('amount', 14, 2);
                $table->foreignId('currency_id')->constrained()->restrictOnDelete();
                $table->decimal('fee_amount', 14, 2)->default(0);
                $table->date('transfer_date');
                $table->text('notes')->nullable();
                $table->uuid('ledger_group_id');
                $table->boolean('is_reversed')->default(false);
                $table->timestamp('reversed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ledger_entries')) {
            Schema::create('ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->uuid('group_id')->index();
                $table->string('transaction_type', 50);
                $table->foreignId('fund_id')->constrained()->restrictOnDelete();
                $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
                $table->foreignId('currency_id')->constrained()->restrictOnDelete();
                $table->decimal('amount', 14, 2);
                $table->date('occurred_on');
                $table->string('description');
                $table->text('notes')->nullable();
                $table->nullableMorphs('related');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_reversal')->default(false);
                $table->foreignId('reverses_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
                $table->timestamps();
                $table->index(['fund_id', 'payment_method_id', 'currency_id']);
                $table->index(['occurred_on', 'transaction_type']);
            });
        }

        if (! Schema::hasTable('financial_audit_logs')) {
            Schema::create('financial_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 50);
                $table->string('entity_type');
                $table->unsignedBigInteger('entity_id');
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->index(['entity_type', 'entity_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_audit_logs');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('fund_transfers');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('family_loan_repayment_items');
        Schema::dropIfExists('family_loan_repayments');
        Schema::dropIfExists('family_loans');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('client_payments');
        Schema::dropIfExists('client_services');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('service_types');
        Schema::dropIfExists('family_members');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('funds');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('currencies');
    }
};
