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
        Schema::table('users', function (Blueprint $table) {
            // An employee can now belong to several companies (a role can
            // authorize more than one) — same JSON-array convention as
            // Role::allowed_companies/dispatch_zones. Replaces the single
            // `company_id` FK, dropped in the next migration once existing
            // rows are carried over below.
            $table->json('company_ids')->nullable()->after('company_id');
        });

        DB::table('users')->whereNotNull('company_id')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('users')->where('id', $row->id)->update([
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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('company_ids');
        });
    }
};
