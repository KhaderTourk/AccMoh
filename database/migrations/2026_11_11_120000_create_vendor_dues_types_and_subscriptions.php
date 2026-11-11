<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مستحقات الموظفين والموردين (قرار م. محمد 11/11/2026، صورة نسخة 5):
 * - أنواع المستحقات من الإعدادات («مستحقات الموظفين» و«خدمات الموردين»).
 * - الشهري صار اشتراك على النوع، وأكثر من واحد لكل حساب، زي اشتراكات الزبون.
 * - «مبلغ أول شهر» قابل للتعديل للزبون والموظف والمورد.
 * - الرواتب الموجودة (vendors.salary_*) بتتحوّل لاشتراك «راتب» بنفس المبلغ والتاريخ، وما بتتغيّر ولا حركة.
 *
 * جداول وأعمدة جديدة بس. الأعمدة القديمة بتضل زي ما هي (ما بننحذف بيانات).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_charge_types')) {
            Schema::create('vendor_charge_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->index();
                $table->string('vendor_type', 20); // worker | supplier
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vendor_subscriptions')) {
            Schema::create('vendor_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->index();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('charge_type_id')->nullable()->constrained('vendor_charge_types')->nullOnDelete();
                $table->string('title');
                $table->decimal('amount', 14, 2);
                $table->decimal('first_amount', 14, 2)->nullable();
                $table->foreignId('currency_id')->constrained();
                $table->date('starts_on');
                $table->date('ends_on')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('vendor_charges', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_charges', 'charge_type_id')) {
                $table->foreignId('charge_type_id')->nullable()->after('vendor_id')
                    ->constrained('vendor_charge_types')->nullOnDelete();
            }
            if (! Schema::hasColumn('vendor_charges', 'subscription_id')) {
                $table->foreignId('subscription_id')->nullable()->after('charge_type_id')
                    ->constrained('vendor_subscriptions')->nullOnDelete();
            }
        });

        Schema::table('client_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('client_subscriptions', 'first_amount')) {
                $table->decimal('first_amount', 14, 2)->nullable()->after('amount');
            }
        });

        $this->seedAndConvert();
    }

    /** أنواع الموظف الافتراضية لكل مستأجر، وتحويل الرواتب الموجودة لاشتراكات. */
    protected function seedAndConvert(): void
    {
        $now = now();
        $tenants = DB::table('vendors')->select('tenant_id')->distinct()->pluck('tenant_id')
            ->merge(Schema::hasTable('tenants') ? DB::table('tenants')->pluck('id') : [])
            ->unique()->values();

        foreach ($tenants as $tenantId) {
            $exists = DB::table('vendor_charge_types')->where('tenant_id', $tenantId)->where('vendor_type', 'worker')->exists();
            if (! $exists) {
                foreach (['راتب', 'مكافأة', 'عمولة'] as $i => $name) {
                    DB::table('vendor_charge_types')->insert([
                        'tenant_id' => $tenantId, 'vendor_type' => 'worker', 'name' => $name,
                        'is_active' => true, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        $ils = DB::table('currencies')->where('code', 'ILS')->value('id');
        if (! $ils || ! Schema::hasColumn('vendors', 'salary_amount')) {
            return;
        }

        $vendors = DB::table('vendors')->whereNotNull('salary_amount')->where('salary_amount', '>', 0)->whereNotNull('salary_from')->get();
        foreach ($vendors as $v) {
            if (DB::table('vendor_subscriptions')->where('vendor_id', $v->id)->exists()) {
                continue;
            }
            $worker = $v->type === 'worker';
            $typeId = $worker
                ? DB::table('vendor_charge_types')->where('tenant_id', $v->tenant_id)->where('vendor_type', 'worker')->where('name', 'راتب')->value('id')
                : null;
            $subId = DB::table('vendor_subscriptions')->insertGetId([
                'tenant_id' => $v->tenant_id,
                'vendor_id' => $v->id,
                'charge_type_id' => $typeId,
                'title' => $worker ? 'راتب' : ($v->work_description ?: 'اشتراك شهري'),
                'amount' => $v->salary_amount,
                'currency_id' => $ils,
                'starts_on' => $v->salary_from,
                'ends_on' => $v->salary_to,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            // الشهور اللي انضافت قبل بتنربط بالاشتراك، بدون ما يتغيّر مبلغها أو تاريخها.
            DB::table('vendor_charges')->where('vendor_id', $v->id)->whereNotNull('period')->whereNull('subscription_id')
                ->update(['subscription_id' => $subId, 'charge_type_id' => $typeId]);
        }
    }

    public function down(): void
    {
        // لا شيء: ما بنحذف جداول أو أعمدة عشان ما تضيع بيانات.
    }
};
