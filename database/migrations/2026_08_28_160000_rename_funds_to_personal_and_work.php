<?php

use App\Enums\FundSlug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('funds')->where('slug', FundSlug::Family->value)->update(['name' => 'شخصي']);
        DB::table('funds')->where('slug', FundSlug::Business->value)->update(['name' => 'العمل']);
    }

    public function down(): void
    {
        DB::table('funds')->where('slug', FundSlug::Family->value)->update(['name' => 'صندوق العائلة']);
        DB::table('funds')->where('slug', FundSlug::Business->value)->update(['name' => 'صندوق العمل']);
    }
};
