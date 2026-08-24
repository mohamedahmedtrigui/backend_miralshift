<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shift;
use App\Models\Role;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $query = Shift::with(['company', 'agency'])->withCount('users')->orderBy('name');

        $this->applyScope($query, $request->user()?->role);

        return response()->json($query->get());
    }

    /**
     * Same scoping as UserController — a restricted role only sees shifts
     * within its allowed_companies/allowed_agencies.
     */
    private function applyScope($query, ?Role $role): void
    {
        if (!$role || $role->access_level !== 'restricted') {
            return;
        }

        if (!empty($role->allowed_companies)) {
            $query->whereIn('company_id', $role->allowed_companies);
        }

        if (!empty($role->allowed_agencies)) {
            $query->whereIn('agency_id', $role->allowed_agencies);
        }
    }

    private function inScope(Shift $shift, ?Role $role): bool
    {
        if (!$role || $role->access_level !== 'restricted') {
            return true;
        }

        return $role->allowsCompany($shift->company_id) && $role->allowsAgency($shift->agency_id);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'company_id' => 'required|exists:companies,id',
            'agency_id' => 'required|exists:agencies,id',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'color' => 'nullable|string|max:7',
        ]);

        $actingRole = $request->user()?->role;
        if ($actingRole && $actingRole->access_level === 'restricted') {
            if (!$actingRole->allowsCompany($validated['company_id']) || !$actingRole->allowsAgency($validated['agency_id'])) {
                abort(403, 'Vous ne pouvez pas créer un shift en dehors de vos compagnies/agences autorisées.');
            }
        }

        $shift = Shift::create($validated);
        return response()->json($shift->load(['company', 'agency']), 201);
    }

    public function show(Request $request, Shift $shift)
    {
        if (!$this->inScope($shift, $request->user()?->role)) {
            abort(403, 'Ce shift est en dehors des compagnies/agences autorisées pour votre rôle.');
        }

        return response()->json($shift->load(['company', 'agency']));
    }

    public function update(Request $request, Shift $shift)
    {
        if (!$this->inScope($shift, $request->user()?->role)) {
            abort(403, 'Ce shift est en dehors des compagnies/agences autorisées pour votre rôle.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:191',
            'company_id' => 'sometimes|exists:companies,id',
            'agency_id' => 'sometimes|exists:agencies,id',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'color' => 'nullable|string|max:7',
        ]);

        $actingRole = $request->user()?->role;
        if ($actingRole && $actingRole->access_level === 'restricted') {
            $newCompanyId = $validated['company_id'] ?? $shift->company_id;
            $newAgencyId = $validated['agency_id'] ?? $shift->agency_id;
            if (!$actingRole->allowsCompany($newCompanyId) || !$actingRole->allowsAgency($newAgencyId)) {
                abort(403, 'Vous ne pouvez pas déplacer ce shift vers une compagnie/agence en dehors de vos autorisations.');
            }
        }

        $shift->update($validated);
        return response()->json($shift->load(['company', 'agency']));
    }

    public function destroy(Request $request, Shift $shift)
    {
        if (!$this->inScope($shift, $request->user()?->role)) {
            abort(403, 'Ce shift est en dehors des compagnies/agences autorisées pour votre rôle.');
        }

        if ($shift->users()->exists()) {
            return response()->json([
                'message' => 'Ce shift a encore des employés rattachés et ne peut pas être supprimé. Réaffectez ou supprimez d\'abord ces employés.',
            ], 422);
        }

        $shift->delete();
        return response()->json(null, 204);
    }
}
