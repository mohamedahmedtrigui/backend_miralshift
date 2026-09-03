<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Company;
use App\Models\Agency;
use App\Models\Zone;
use App\Models\Shift;
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

        // 3. Create Shifts — each tied to one company + one agency, like a
        // real dispatch shift would be.
        $tunisAgency = Agency::where('name', 'Tunis')->first();
        $sousseAgency = Agency::where('name', 'Sousse')->first();
        $sfaxAgency = Agency::where('name', 'Sfax')->first();
        $arianaAgency = Agency::where('name', 'Ariana')->first();

        $morningShift = Shift::create([
            'name' => 'Matin',
            'company_ids' => [(string) $company1->id],
            'agency_id' => $tunisAgency->id,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'color' => '#22c55e',
        ]);

        $afternoonShift = Shift::create([
            'name' => 'Après-midi',
            'company_ids' => [(string) $company2->id],
            'agency_id' => $sousseAgency->id,
            'start_time' => '14:00:00',
            'end_time' => '22:00:00',
            'color' => '#f59e0b',
        ]);

        $nightShift = Shift::create([
            'name' => 'Nuit',
            'company_ids' => [(string) $company1->id],
            'agency_id' => $sfaxAgency->id,
            'start_time' => '20:00:00',
            'end_time' => '04:00:00',
            'color' => '#6366f1',
        ]);

        $morningArianaShift = Shift::create([
            'name' => 'Matin',
            'company_ids' => [(string) $company1->id],
            'agency_id' => $arianaAgency->id,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'color' => '#22c55e',
        ]);

        // 4. Create Users
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'System',
            'username' => 'admin',
            'password' => Hash::make('password'),
            'company_ids' => [(string) $company1->id],
            'agency_id' => $tunisAgency->id,
            'dispatch_zones' => ['Tunis'],
            'day_off' => 'Sunday',
            'shift_id' => $morningShift->id,
            'start_date' => '2023-01-01'
        ]);
        $admin->roles()->attach($adminRole->id);

        $sami = User::create([
            'first_name' => 'Sami',
            'last_name' => 'Trabelsi',
            'username' => 'sami',
            'password' => Hash::make('password'),
            'company_ids' => [(string) $company2->id],
            'agency_id' => $sousseAgency->id,
            'dispatch_zones' => ['Sousse'],
            'day_off' => 'Monday',
            'shift_id' => $afternoonShift->id,
            'start_date' => '2023-06-15'
        ]);
        $sami->roles()->attach($dispatcherRole->id);

        User::create([
            'first_name' => 'Fatma',
            'last_name' => 'Karray',
            'company_ids' => [(string) $company1->id],
            'agency_id' => $sfaxAgency->id,
            'dispatch_zones' => ['Sfax', 'Tunis'],
            'day_off' => 'Wednesday',
            'shift_id' => $nightShift->id,
            'start_date' => '2024-01-10'
        ]);

        User::create([
            'first_name' => 'Youssef',
            'last_name' => 'Gharbi',
            'company_ids' => [(string) $company1->id],
            'agency_id' => $arianaAgency->id,
            'dispatch_zones' => ['Ariana'],
            'day_off' => 'Sunday',
            'shift_id' => $morningArianaShift->id,
            'start_date' => '2024-02-01'
        ]);
    }
}
