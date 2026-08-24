<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Independent of the create/read/update/delete permissions matrix: which
// screens (Calendrier, Employés, Rôles, Compagnies, Shifts) a restricted
// role can even navigate to. Null/empty means unrestricted, matching the
// existing allowed_zones/allowed_companies convention on this table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->json('interface_access')->nullable()->after('allowed_companies');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('interface_access');
        });
    }
};
