<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Same convention as allowed_companies: stored as a json array for
// consistency with the other allowed_* columns, but the UI restricts a role
// to at most one agency (a role belongs to one agency, same as one company).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->json('allowed_agencies')->nullable()->after('allowed_companies');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('allowed_agencies');
        });
    }
};
