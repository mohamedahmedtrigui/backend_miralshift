<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Models\Shift;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Super Admin accounts are hidden from the employee list — they're
        // system/admin accounts, not staff to schedule or manage here.
        $query = User::with(['role', 'agency', 'shift'])
            ->whereDoesntHave('role', fn ($q) => $q->where('access_level', 'full'));

        $this->applyScope($query, $request->user()?->role);

        return response()->json($this->attachCompanies($query->get()));
    }

    /**
     * users.company_ids is a JSON array now, not a single FK, so it can't be
     * eager-loaded via with('company') — resolve it once against all
     * companies (avoids an N+1 lookup per row) and attach it as a plain
     * `companies` list on each user.
     */
    private function attachCompanies($users)
    {
        $companiesById = \App\Models\Company::all()->keyBy('id');
        $users->each(fn ($user) => $user->companies = $this->resolveCompanies($user->company_ids ?? [], $companiesById));

        return $users;
    }

    private function attachCompaniesToOne(User $user)
    {
        $user->companies = $this->resolveCompanies($user->company_ids ?? [], \App\Models\Company::all()->keyBy('id'));

        return $user;
    }

    private function resolveCompanies(array $companyIds, $companiesById)
    {
        return collect($companyIds)
            ->map(fn ($id) => $companiesById->get((int) $id))
            ->filter()
            ->values();
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
            $query->where(function ($q) use ($role) {
                foreach ($role->allowed_companies as $companyId) {
                    $q->orWhereJsonContains('company_ids', (string) $companyId);
                }
            });
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

        if (!$role->allowsAnyCompany($user->company_ids ?? [])) {
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

    /**
     * Only a full-access actor may grant another user a full-access role —
     * otherwise a restricted role with `users.update` permission on itself
     * could hand itself (or anyone else) Super Admin by setting role_id.
     */
    private function assertRoleAssignmentAllowed(?Role $actingRole, ?int $newRoleId): void
    {
        if ($newRoleId === null || $actingRole?->access_level === 'full') {
            return;
        }

        $targetRole = Role::find($newRoleId);
        if ($targetRole && $targetRole->access_level === 'full') {
            abort(403, 'Vous ne pouvez pas assigner un rôle à accès complet.');
        }
    }

    /**
     * A shift can belong to several companies but always exactly one
     * agency — reject assigning a shift that doesn't share at least one
     * company with this user and match its agency exactly, so the calendar
     * never shows a shift's time/color against an unrelated employee.
     */
    private function assertShiftMatchesAssignment(?int $shiftId, array $companyIds, $agencyId): void
    {
        if ($shiftId === null) {
            return;
        }

        $shift = Shift::find($shiftId);
        if (!$shift) {
            return;
        }

        $sharesCompany = !empty(array_intersect(
            array_map('strval', $shift->company_ids ?? []),
            array_map('strval', $companyIds)
        ));
        if (!$sharesCompany || (string) $shift->agency_id !== (string) $agencyId) {
            abort(422, 'Le shift sélectionné n\'appartient pas à la compagnie/agence de cet employé.');
        }
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
            'shift_id' => 'nullable|exists:shifts,id',
            'start_date' => 'nullable|date',
            'company_ids' => 'nullable|array',
            'company_ids.*' => 'exists:companies,id',
            'role_id' => 'nullable|exists:roles,id',
            'username' => 'nullable|string|max:191|unique:users',
            'password' => 'nullable|string|min:6',
        ]);

        $actingRole = $request->user()?->role;
        if ($actingRole && $actingRole->access_level === 'restricted') {
            if (!$actingRole->allowsAllCompanies($validated['company_ids'] ?? [])) {
                abort(403, 'Vous ne pouvez pas assigner un employé à une compagnie en dehors de vos compagnies autorisées.');
            }
            $zones = $validated['dispatch_zones'] ?? [];
            if (!empty($actingRole->allowed_zones) && empty(array_intersect($actingRole->allowed_zones, $zones))) {
                abort(403, 'Vous ne pouvez pas assigner des zones de dispatch en dehors de vos zones autorisées.');
            }
        }
        $this->assertRoleAssignmentAllowed($actingRole, $validated['role_id'] ?? null);
        $this->assertShiftMatchesAssignment($validated['shift_id'] ?? null, $validated['company_ids'] ?? [], $validated['agency_id'] ?? null);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user = User::create($validated);
        return response()->json($this->attachCompaniesToOne($user->load(['role', 'agency', 'shift'])), 201);
    }

    public function show(Request $request, User $user)
    {
        if (!$this->inScope($user, $request->user()?->role)) {
            abort(403, 'Cet employé est en dehors des compagnies/zones autorisées pour votre rôle.');
        }

        return response()->json($this->attachCompaniesToOne($user->load(['role', 'agency', 'shift'])));
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
            'shift_id' => 'nullable|exists:shifts,id',
            'start_date' => 'nullable|date',
            'company_ids' => 'nullable|array',
            'company_ids.*' => 'exists:companies,id',
            'role_id' => 'nullable|exists:roles,id',
            'username' => 'nullable|string|max:191|unique:users,username,'.$user->id,
            'password' => 'nullable|string|min:6',
        ]);

        $actingRole = $request->user()?->role;
        if ($actingRole && $actingRole->access_level === 'restricted') {
            $newCompanyIds = array_key_exists('company_ids', $validated) ? $validated['company_ids'] : ($user->company_ids ?? []);
            if (!$actingRole->allowsAllCompanies($newCompanyIds)) {
                abort(403, 'Vous ne pouvez pas déplacer un employé vers une compagnie en dehors de vos compagnies autorisées.');
            }
            $newZones = array_key_exists('dispatch_zones', $validated) ? $validated['dispatch_zones'] : ($user->dispatch_zones ?? []);
            if (!empty($actingRole->allowed_zones) && empty(array_intersect($actingRole->allowed_zones, $newZones ?? []))) {
                abort(403, 'Vous ne pouvez pas assigner des zones de dispatch en dehors de vos zones autorisées.');
            }
        }
        if (array_key_exists('role_id', $validated)) {
            $this->assertRoleAssignmentAllowed($actingRole, $validated['role_id']);
        }
        if (array_key_exists('shift_id', $validated)) {
            $finalCompanyIds = array_key_exists('company_ids', $validated) ? $validated['company_ids'] : ($user->company_ids ?? []);
            $finalAgencyId = array_key_exists('agency_id', $validated) ? $validated['agency_id'] : $user->agency_id;
            $this->assertShiftMatchesAssignment($validated['shift_id'], $finalCompanyIds, $finalAgencyId);
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        return response()->json($this->attachCompaniesToOne($user->load(['role', 'agency', 'shift'])));
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
