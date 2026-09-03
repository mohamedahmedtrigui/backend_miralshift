<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class CalendarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user?->canAccessInterface('calendar')) {
            abort(403, 'Votre rôle n\'a pas accès au calendrier.');
        }

        // For v1, return all users with their company and role.
        // The frontend will filter them by day_off and shift times.
        // If we want the backend to format it per day:
        // Since the day_off is fixed, we just return the users list.
        // Super Admins are system/admin accounts, not dispatchers with real
        // shifts — exclude them from the schedule.
        $query = User::with('roles', 'agency', 'shift')
            ->whereDoesntHave('roles', fn ($q) => $q->where('access_level', 'full'));

        // A "restricted" role only sees the schedule for its allowed
        // companies/zones — same scope as the Employees list.
        if ($user->effectiveAccessLevel() === 'restricted') {
            $companies = $user->allowedCompaniesScope();
            if ($companies) {
                $query->where(function ($q) use ($companies) {
                    foreach ($companies as $companyId) {
                        $q->orWhereJsonContains('company_ids', (string) $companyId);
                    }
                });
            }
            $zones = $user->allowedZonesScope();
            if ($zones) {
                $query->where(function ($q) use ($zones) {
                    foreach ($zones as $zone) {
                        $q->orWhereJsonContains('dispatch_zones', $zone);
                    }
                });
            }
        }

        $users = $query->get();
        $companiesById = \App\Models\Company::all()->keyBy('id');
        $users->each(function ($user) use ($companiesById) {
            $user->companies = collect($user->company_ids ?? [])
                ->map(fn ($id) => $companiesById->get((int) $id))
                ->filter()
                ->values();
        });

        return response()->json($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
