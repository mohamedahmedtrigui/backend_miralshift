<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Super Admin accounts are hidden from the employee list — they're
        // system/admin accounts, not staff to schedule or manage here.
        $query = User::with(['company', 'role', 'agency'])
            ->whereDoesntHave('role', fn ($q) => $q->where('access_level', 'full'));

        $this->applyScope($query, $request->user()?->role);

        return response()->json($query->get());
    }

    /**
     * A "restricted" role only sees employees within its allowed_companies
     * and allowed_zones (matched against dispatch_zones). Full access (or
     * no role restriction configured, i.e. empty lists) sees everyone.
     */
    private function applyScope($query, ?\App\Models\Role $role): void
    {
        if (!$role || $role->access_level !== 'restricted') {
            return;
        }

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

    /**
     * Whether the acting role is allowed to view/edit/delete this specific
     * employee — guards direct-ID access from bypassing the same
     * company/zone scope enforced on the list endpoint.
     */
    private function inScope(User $user, ?\App\Models\Role $role): bool
    {
        if (!$role || $role->access_level !== 'restricted') {
            return true;
        }

        if (!$role->allowsCompany($user->company_id)) {
            return false;
        }

        if (!empty($role->allowed_zones)) {
            $dispatchZones = $user->dispatch_zones ?? [];
            if (empty(array_intersect($role->allowed_zones, $dispatchZones))) {
                return false;
            }
        }

        return true;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:191',
            'last_name' => 'required|string|max:191',
            'phone' => 'nullable|string|max:191',
            'agency_id' => 'nullable|exists:agencies,id',
            'dispatch_zones' => 'nullable|array',
            'dispatch_zones.*' => 'string|max:191',
            'day_off' => 'nullable|string|max:191',
            'shift_start' => 'nullable|date_format:H:i',
            'shift_end' => 'nullable|date_format:H:i',
            'start_date' => 'nullable|date',
            'company_id' => 'nullable|exists:companies,id',
            'role_id' => 'nullable|exists:roles,id',
            'username' => 'nullable|string|max:191|unique:users',
            'password' => 'nullable|string|min:6',
        ]);

        $actingRole = $request->user()?->role;
        if ($actingRole && $actingRole->access_level === 'restricted') {
            if (!$actingRole->allowsCompany($validated['company_id'] ?? null)) {
                abort(403, 'Vous ne pouvez pas assigner un employé à une compagnie en dehors de vos compagnies autorisées.');
            }
            $zones = $validated['dispatch_zones'] ?? [];
            if (!empty($actingRole->allowed_zones) && empty(array_intersect($actingRole->allowed_zones, $zones))) {
                abort(403, 'Vous ne pouvez pas assigner des zones de dispatch en dehors de vos zones autorisées.');
            }
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user = User::create($validated);
        return response()->json($user->load(['company', 'role', 'agency']), 201);
    }

    public function show(Request $request, User $user)
    {
        if (!$this->inScope($user, $request->user()?->role)) {
            abort(403, 'Cet employé est en dehors des compagnies/zones autorisées pour votre rôle.');
        }

        return response()->json($user->load(['company', 'role', 'agency']));
    }

    public function update(Request $request, User $user)
    {
        if (!$this->inScope($user, $request->user()?->role)) {
            abort(403, 'Cet employé est en dehors des compagnies/zones autorisées pour votre rôle.');
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:191',
            'last_name' => 'sometimes|string|max:191',
            'phone' => 'nullable|string|max:191',
            'agency_id' => 'nullable|exists:agencies,id',
            'dispatch_zones' => 'nullable|array',
            'dispatch_zones.*' => 'string|max:191',
            'day_off' => 'nullable|string|max:191',
            'shift_start' => 'nullable|date_format:H:i',
            'shift_end' => 'nullable|date_format:H:i',
            'start_date' => 'nullable|date',
            'company_id' => 'nullable|exists:companies,id',
            'role_id' => 'nullable|exists:roles,id',
            'username' => 'nullable|string|max:191|unique:users,username,'.$user->id,
            'password' => 'nullable|string|min:6',
        ]);

        $actingRole = $request->user()?->role;
        if ($actingRole && $actingRole->access_level === 'restricted') {
            $newCompanyId = array_key_exists('company_id', $validated) ? $validated['company_id'] : $user->company_id;
            if (!$actingRole->allowsCompany($newCompanyId)) {
                abort(403, 'Vous ne pouvez pas déplacer un employé vers une compagnie en dehors de vos compagnies autorisées.');
            }
            $newZones = array_key_exists('dispatch_zones', $validated) ? $validated['dispatch_zones'] : ($user->dispatch_zones ?? []);
            if (!empty($actingRole->allowed_zones) && empty(array_intersect($actingRole->allowed_zones, $newZones ?? []))) {
                abort(403, 'Vous ne pouvez pas assigner des zones de dispatch en dehors de vos zones autorisées.');
            }
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        return response()->json($user->load(['company', 'role', 'agency']));
    }

    public function destroy(Request $request, User $user)
    {
        if (!$this->inScope($user, $request->user()?->role)) {
            abort(403, 'Cet employé est en dehors des compagnies/zones autorisées pour votre rôle.');
        }

        $user->delete();
        return response()->json(null, 204);
    }
}
