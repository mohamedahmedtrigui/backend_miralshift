<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // A shift can now be shared across several companies (e.g. a
            // common "Matin" shift for both Miral Drive and ZigZag) — same
            // JSON-array convention as users.company_ids/Role::allowed_companies.
            // Replaces the single `company_id` FK, dropped in the next
            // migration once existing rows are carried over below.
            $table->json('company_ids')->nullable()->after('company_id');
        });

        DB::table('shifts')->whereNotNull('company_id')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('shifts')->where('id', $row->id)->update([
                    'company_ids' => json_encode([(string) $row->company_id]),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('company_ids');
        });
    }
};
