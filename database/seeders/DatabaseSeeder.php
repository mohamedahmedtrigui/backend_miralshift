<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\Agency;
use App\Models\Zone;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 0. Create Zones & Agencies (same city list, kept as two separate
        // tables since an agency is a physical office while a zone is just
        // a coverage area — a dispatcher can cover zones outside their own
        // agency's city).
        foreach ([
            'Ariana', 'Ben Arous', 'Bizerte', 'El Aouina', 'El Menzah', 'Gabès',
            'Hammamet', 'Manouba', 'Monastir', 'Nabeul & Hammamet', 'Sfax', 'Sousse', 'Tunis',
        ] as $name) {
            Zone::firstOrCreate(['name' => $name]);
            Agency::firstOrCreate(['name' => $name]);
        }

        // 1. Create Companies
        $company1 = Company::create([
            'name' => 'Miral Transport',
            'logo' => 'MT',
            'description' => 'Main transport company'
        ]);

        $company2 = Company::create([
            'name' => 'LogisTN',
            'logo' => 'LT',
            'description' => 'Logistics partner'
        ]);

        // 2. Create Roles
        $adminRole = Role::create([
            'name' => 'Super Admin',
            'description' => 'Has full access to everything',
            'access_level' => 'full'
        ]);

        $dispatcherRole = Role::create([
            'name' => 'Dispatcher Regional',
            'description' => 'Can only see specific zones',
            'access_level' => 'restricted',
            'allowed_zones' => ['Tunis', 'Ariana'],
            'allowed_companies' => [$company1->id],
            'permissions' => ['users' => ['create', 'update', 'delete']]
        ]);

        // 3. Create Users
        User::create([
            'first_name' => 'Admin',
            'last_name' => 'System',
            'username' => 'admin',
            'password' => Hash::make('password'),
            'role_id' => $adminRole->id,
            'company_id' => $company1->id,
            'agency_id' => Agency::where('name', 'Tunis')->first()->id,
            'dispatch_zones' => ['Tunis'],
            'day_off' => 'Sunday',
            'shift_start' => '08:00:00',
            'shift_end' => '17:00:00',
            'start_date' => '2023-01-01'
        ]);

        User::create([
            'first_name' => 'Sami',
            'last_name' => 'Trabelsi',
            'username' => 'sami',
            'password' => Hash::make('password'),
            'role_id' => $dispatcherRole->id,
            'company_id' => $company2->id,
            'agency_id' => Agency::where('name', 'Sousse')->first()->id,
            'dispatch_zones' => ['Sousse'],
            'day_off' => 'Monday',
            'shift_start' => '14:00:00',
            'shift_end' => '22:00:00',
            'start_date' => '2023-06-15'
        ]);

        User::create([
            'first_name' => 'Fatma',
            'last_name' => 'Karray',
            'company_id' => $company1->id,
            'agency_id' => Agency::where('name', 'Sfax')->first()->id,
            'dispatch_zones' => ['Sfax', 'Tunis'],
            'day_off' => 'Wednesday',
            'shift_start' => '20:00:00',
            'shift_end' => '04:00:00',
            'start_date' => '2024-01-10'
        ]);

        User::create([
            'first_name' => 'Youssef',
            'last_name' => 'Gharbi',
            'company_id' => $company1->id,
            'agency_id' => Agency::where('name', 'Ariana')->first()->id,
            'dispatch_zones' => ['Ariana'],
            'day_off' => 'Sunday',
            'shift_start' => '09:00:00',
            'shift_end' => '17:00:00',
            'start_date' => '2024-02-01'
        ]);
    }
}
