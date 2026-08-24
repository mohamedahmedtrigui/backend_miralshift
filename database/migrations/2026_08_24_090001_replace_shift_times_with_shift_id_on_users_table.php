<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Employees no longer type a raw start/end time on their own form — they pick
// an existing Shift (managed on the Config Shift screen), which carries the
// times and a company/agency of its own.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('agency_id')->constrained('shifts')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['shift_start', 'shift_end']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time('shift_start')->nullable();
            $table->time('shift_end')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });
    }
};
