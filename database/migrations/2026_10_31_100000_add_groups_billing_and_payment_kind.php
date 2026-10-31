<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Settings page (#7 §6): services live in named groups and bill once or monthly.
        if (! Schema::hasTable('service_groups')) {
            Schema::create('service_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->index();
                $table->string('name', 100);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('service_types', function (Blueprint $table) {
            if (! Schema::hasColumn('service_types', 'service_group_id')) {
                $table->foreignId('service_group_id')->nullable()->after('name')->constrained('service_groups')->nullOnDelete();
            }
            if (! Schema::hasColumn('service_types', 'billing')) {
                $table->string('billing', 10)->default('once')->after('service_group_id');
            }
        });

        // «استرداد»: money paid back to a client is an outgoing payment that raises what the client owes.
        Schema::table('cash_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_payments', 'kind')) {
                $table->string('kind', 20)->nullable()->after('direction');
            }
        });

        // Monthly subscriptions (#7 §3): each confirmed month becomes a client service tied to it.
        if (! Schema::hasTable('client_subscriptions')) {
            Schema::create('client_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->index();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete();
                $table->foreignId('service_type_id')->nullable()->constrained()->nullOnDelete();
                $table->string('title');
                $table->decimal('amount', 14, 2);
                $table->foreignId('currency_id')->constrained();
                $table->date('starts_on');
                $table->date('ends_on')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('client_services', function (Blueprint $table) {
            if (! Schema::hasColumn('client_services', 'subscription_id')) {
                $table->foreignId('subscription_id')->nullable()->after('service_type_id')
                    ->constrained('client_subscriptions')->nullOnDelete();
                $table->char('period', 7)->nullable()->after('subscription_id');
                $table->unique(['subscription_id', 'period']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_services', function (Blueprint $table) {
            $table->dropUnique(['subscription_id', 'period']);
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn('period');
        });
        Schema::dropIfExists('client_subscriptions');
        Schema::table('cash_payments', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
        Schema::table('service_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_group_id');
            $table->dropColumn('billing');
        });
        Schema::dropIfExists('service_groups');
    }
};
