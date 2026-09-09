<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients') || ! Schema::hasColumn('clients', 'contact_name')) {
            return;
        }

        $clients = DB::table('clients')->get();

        foreach ($clients as $client) {
            $contact = trim((string) ($client->contact_name ?? ''));
            $name = trim((string) ($client->name ?? ''));
            $company = trim((string) ($client->company_name ?? ''));

            if ($contact === '' || $contact === $name) {
                continue;
            }

            DB::table('clients')->where('id', $client->id)->update([
                'name' => $contact,
                'company_name' => $company !== '' ? $company : $name,
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible data alignment.
    }
};
