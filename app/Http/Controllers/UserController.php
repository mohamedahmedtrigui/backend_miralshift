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
        // system/admin accounts, not staff to schedule or manage here. The
        // acting user's own row is hidden too — otherwise they could edit
        // their own company/role/shift from this screen, the same
        // self-escalation path already blocked on the Roles screen.
        $query = User::with(['roles', 'agency', 'shift'])
            ->whereDoesntHave('roles', fn ($q) => $q->where('access_level', 'full'))
            ->where('id', '!=', $request->user()?->id);

        $this->applyScope($query, $request->user());

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
    private function applyScope($query, ?User $actingUser): void
    {
        if (!$actingUser || $actingUser->effectiveAccessLevel() !== 'restricted') {
            return;
        }

        $companies = $actingUser->allowedCompaniesScope();
        if ($companies) {
            $query->where(function ($q) use ($companies) {
                foreach ($companies as $companyId) {
                    $q->orWhereJsonContains('company_ids', (string) $companyId);
                }
            });
        }

        $zones = $actingUser->allowedZonesScope();
        if ($zones) {
            $query->where(function ($q) use ($zones) {
                foreach ($zones as $zone) {
                    $q->orWhereJsonContains('dispatch_zones', $zone);
                }
            });
        }
    }

    /**
     * Whether the acting user is allowed to view/edit/delete this specific
     * employee — guards direct-ID access from bypassing the same
     * company/zone scope enforced on the list endpoint.
     */
    private function inScope(User $user, ?User $actingUser): bool
    {
        if (!$actingUser || $actingUser->effectiveAccessLevel() !== 'restricted') {
            return true;
        }

        if (!$actingUser->allowsAnyCompany($user->company_ids ?? [])) {
            return false;
        }

        $zones = $actingUser->allowedZonesScope();
        if ($zones && empty(array_intersect($zones, $user->dispatch_zones ?? []))) {
            return false;
        }

        return true;
    }

    /**
     * Only a full-access actor may grant another user a full-access role —
     * otherwise a restricted role with `users.update` permission on itself
     * could hand itself (or anyone else) Super Admin by setting role_ids.
     */
    private function assertRoleAssignmentAllowed(User $actingUser, array $newRoleIds): void
    {
        if (empty($newRoleIds) || $actingUser->hasFullAccess()) {
            return;
        }

        $hasFullTarget = Role::whereIn('id', $newRoleIds)->where('access_level', 'full')->exists();
        if ($hasFullTarget) {
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
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
            'username' => 'nullable|string|max:191|unique:users',
            'password' => 'nullable|string|min:6',
        ]);

        $actingUser = $request->user();
        if ($actingUser->effectiveAccessLevel() === 'restricted') {
            if (!$actingUser->allowsAllCompanies($validated['company_ids'] ?? [])) {
                abort(403, 'Vous ne pouvez pas assigner un employé à une compagnie en dehors de vos compagnies autorisées.');
            }
            $zones = $validated['dispatch_zones'] ?? [];
            $allowedZones = $actingUser->allowedZonesScope();
            if ($allowedZones && empty(array_intersect($allowedZones, $zones))) {
                abort(403, 'Vous ne pouvez pas assigner des zones de dispatch en dehors de vos zones autorisées.');
            }
        }
        $roleIds = $validated['role_ids'] ?? [];
        $this->assertRoleAssignmentAllowed($actingUser, $roleIds);
        $this->assertShiftMatchesAssignment($validated['shift_id'] ?? null, $validated['company_ids'] ?? [], $validated['agency_id'] ?? null);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }
        unset($validated['role_ids']);

        $user = User::create($validated);
        $user->roles()->sync($roleIds);
        return response()->json($this->attachCompaniesToOne($user->load(['roles', 'agency', 'shift'])), 201);
    }

    public function show(Request $request, User $user)
    {
        if (!$this->inScope($user, $request->user())) {
            abort(403, 'Cet employé est en dehors des compagnies/zones autorisées pour votre rôle.');
        }

        return response()->json($this->attachCompaniesToOne($user->load(['roles', 'agency', 'shift'])));
    }

    public function update(Request $request, User $user)
    {
        $actingUser = $request->user();

        if (!$this->inScope($user, $actingUser)) {
            abort(403, 'Cet employé est en dehors des compagnies/zones autorisées pour votre rôle.');
        }

        // A restricted role editing its own employee row is the same
        // self-escalation path already blocked on the Roles screen — it
        // could hand itself a different company/role/shift.
        if ($actingUser?->id === $user->id && !$actingUser->hasFullAccess()) {
            abort(403, 'Vous ne pouvez pas modifier votre propre compte.');
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
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
            'username' => 'nullable|string|max:191|unique:users,username,'.$user->id,
            'password' => 'nullable|string|min:6',
        ]);

        if ($actingUser->effectiveAccessLevel() === 'restricted') {
            $newCompanyIds = array_key_exists('company_ids', $validated) ? $validated['company_ids'] : ($user->company_ids ?? []);
            if (!$actingUser->allowsAllCompanies($newCompanyIds)) {
                abort(403, 'Vous ne pouvez pas déplacer un employé vers une compagnie en dehors de vos compagnies autorisées.');
            }
            $newZones = array_key_exists('dispatch_zones', $validated) ? $validated['dispatch_zones'] : ($user->dispatch_zones ?? []);
            $allowedZones = $actingUser->allowedZonesScope();
            if ($allowedZones && empty(array_intersect($allowedZones, $newZones ?? []))) {
                abort(403, 'Vous ne pouvez pas assigner des zones de dispatch en dehors de vos zones autorisées.');
            }
        }
        if (array_key_exists('role_ids', $validated)) {
            $this->assertRoleAssignmentAllowed($actingUser, $validated['role_ids'] ?? []);
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

        $roleIdsProvided = array_key_exists('role_ids', $validated);
        $roleIds = $validated['role_ids'] ?? [];
        unset($validated['role_ids']);

        $user->update($validated);
        if ($roleIdsProvided) {
            $user->roles()->sync($roleIds);
        }
        return response()->json($this->attachCompaniesToOne($user->load(['roles', 'agency', 'shift'])));
    }

    public function destroy(Request $request, User $user)
    {
        if (!$this->inScope($user, $request->user())) {
            abort(403, 'Cet employé est en dehors des compagnies/zones autorisées pour votre rôle.');
        }

        if ($request->user()?->id === $user->id) {
            abort(403, 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        $user->delete();
        return response()->json(null, 204);
    }
}
