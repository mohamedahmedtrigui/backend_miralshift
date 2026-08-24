<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The employee's home agency. Kept separate from the existing
            // `zone` column (still used as-is by the calendar filter) —
            // this is new, additive data, not a replacement.
            $table->foreignId('agency_id')->nullable()->after('zone')->constrained('agencies')->nullOnDelete();

            // Zones this employee can dispatch/cover, can be several.
            // Stored as a JSON array of zone names, same convention as
            // Role::allowed_zones.
            $table->json('dispatch_zones')->nullable()->after('agency_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agency_id');
            $table->dropColumn('dispatch_zones');
        });
    }
};
