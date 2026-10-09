<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildSqlite();

            return;
        }

        Schema::table('client_goods_takes', function (Blueprint $table) {
            $table->string('party_type')->nullable()->after('tenant_id');
            $table->unsignedBigInteger('party_id')->nullable()->after('party_type');
            $table->index(['party_type', 'party_id']);
        });

        DB::table('client_goods_takes')->whereNull('party_id')->update([
            'party_type' => 'client',
            'party_id' => DB::raw('client_id'),
        ]);

        Schema::table('client_goods_takes', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        DB::statement('ALTER TABLE client_goods_takes MODIFY client_id BIGINT UNSIGNED NULL');

        Schema::table('client_goods_takes', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::table('client_goods_takes')->where('party_type', '!=', 'client')->delete();

        Schema::table('client_goods_takes', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        DB::statement('ALTER TABLE client_goods_takes MODIFY client_id BIGINT UNSIGNED NOT NULL');

        Schema::table('client_goods_takes', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->restrictOnDelete();
            $table->dropIndex(['party_type', 'party_id']);
            $table->dropColumn(['party_type', 'party_id']);
        });
    }

    protected function rebuildSqlite(): void
    {
        $rows = DB::table('client_goods_takes')->get();

        Schema::disableForeignKeyConstraints();
        Schema::drop('client_goods_takes');

        Schema::create('client_goods_takes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('party_type')->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('title');
            $table->decimal('amount', 14, 2);
            $table->decimal('source_amount', 14, 2)->nullable();
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->foreignId('fx_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->date('taken_on');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'client_id']);
            $table->index(['client_id', 'currency_id']);
            $table->index('taken_on');
            $table->index(['party_type', 'party_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->restrictOnDelete();
        });

        foreach ($rows as $row) {
            DB::table('client_goods_takes')->insert([
                'id' => $row->id,
                'tenant_id' => $row->tenant_id,
                'party_type' => 'client',
                'party_id' => $row->client_id,
                'client_id' => $row->client_id,
                'title' => $row->title,
                'amount' => $row->amount,
                'source_amount' => $row->source_amount,
                'exchange_rate' => $row->exchange_rate,
                'fx_currency_id' => $row->fx_currency_id,
                'currency_id' => $row->currency_id,
                'taken_on' => $row->taken_on,
                'notes' => $row->notes,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'deleted_at' => $row->deleted_at,
            ]);
        }

        Schema::enableForeignKeyConstraints();
    }
};
