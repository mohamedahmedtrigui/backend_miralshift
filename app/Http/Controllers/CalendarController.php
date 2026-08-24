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
        // For v1, return all users with their company and role.
        // The frontend will filter them by day_off and shift times.
        // If we want the backend to format it per day:
        // Since the day_off is fixed, we just return the users list.
        // Super Admins are system/admin accounts, not dispatchers with real
        // shifts — exclude them from the schedule.
        $query = User::with('company', 'role', 'agency')
            ->whereDoesntHave('role', fn ($q) => $q->where('access_level', 'full'));

        // A "restricted" role only sees the schedule for its allowed
        // companies/zones — same scope as the Employees list.
        $role = $request->user()?->role;
        if ($role && $role->access_level === 'restricted') {
            if (!empty($role->allowed_companies)) {
                $query->whereIn('company_id', $role->allowed_companies);
            }
            if (!empty($role->allowed_zones)) {
                $query->where(function ($q) use ($role) {
                    foreach ($role->allowed_zones as $zone) {
                        $q->orWhereJsonContains('dispatch_zones', $zone);
                    }
                });
            }
        }

        return response()->json($query->get());
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
