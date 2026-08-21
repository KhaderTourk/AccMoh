<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        // Align legacy audit log table with AccMa finance schema
        if (Schema::hasTable('financial_audit_logs')) {
            $cols = Schema::getColumnListing('financial_audit_logs');
            if (! in_array('entity_type', $cols, true)) {
                Schema::table('financial_audit_logs', function (Blueprint $table) use ($cols) {
                    if (! in_array('entity_type', $cols, true)) {
                        $table->string('entity_type')->nullable()->after('action');
                    }
                    if (! in_array('entity_id', $cols, true)) {
                        $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
                    }
                    if (! in_array('payload', $cols, true)) {
                        $table->json('payload')->nullable();
                    }
                });

                // Backfill from old column names if present
                if (in_array('model_type', $cols, true) && in_array('model_id', $cols, true)) {
                    \DB::statement('UPDATE financial_audit_logs SET entity_type = model_type, entity_id = model_id WHERE entity_type IS NULL');
                }
            }
        } else {
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
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('fund_transfers');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('family_loan_repayment_items');
    }
};
