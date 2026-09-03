<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shift;
use App\Models\User;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $query = Shift::with(['agency'])->withCount('users')->orderBy('name');

        $this->applyScope($query, $request->user());

        return response()->json($this->attachCompanies($query->get()));
    }

    /**
     * shifts.company_ids is a JSON array now, not a single FK, so it can't
     * be eager-loaded via with('company') — resolve it once against all
     * companies (avoids an N+1 lookup per row) and attach it as a plain
     * `companies` list on each shift.
     */
    private function attachCompanies($shifts)
    {
        $companiesById = \App\Models\Company::all()->keyBy('id');
        $shifts->each(fn ($shift) => $shift->companies = $this->resolveCompanies($shift->company_ids ?? [], $companiesById));

        return $shifts;
    }

    private function attachCompaniesToOne(Shift $shift)
    {
        $shift->companies = $this->resolveCompanies($shift->company_ids ?? [], \App\Models\Company::all()->keyBy('id'));

        return $shift;
    }

    private function resolveCompanies(array $companyIds, $companiesById)
    {
        return collect($companyIds)
            ->map(fn ($id) => $companiesById->get((int) $id))
            ->filter()
            ->values();
    }

    /**
     * Same scoping as UserController — a restricted role only sees shifts
     * within its allowed_companies/allowed_agencies.
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

        $agencies = $actingUser->allowedAgenciesScope();
        if ($agencies) {
            $query->whereIn('agency_id', $agencies);
        }
    }

    private function inScope(Shift $shift, ?User $actingUser): bool
    {
        if (!$actingUser || $actingUser->effectiveAccessLevel() !== 'restricted') {
            return true;
        }

        return $actingUser->allowsAnyCompany($shift->company_ids ?? []) && $actingUser->allowsAgency($shift->agency_id);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'company_ids' => 'required|array|min:1',
            'company_ids.*' => 'exists:companies,id',
            'agency_id' => 'required|exists:agencies,id',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'color' => 'nullable|string|max:7',
        ]);

        $actingUser = $request->user();
        if ($actingUser->effectiveAccessLevel() === 'restricted') {
            if (!$actingUser->allowsAllCompanies($validated['company_ids']) || !$actingUser->allowsAgency($validated['agency_id'])) {
                abort(403, 'Vous ne pouvez pas créer un shift en dehors de vos compagnies/agences autorisées.');
            }
        }

        $shift = Shift::create($validated);
        return response()->json($this->attachCompaniesToOne($shift->load(['agency'])), 201);
    }

    public function show(Request $request, Shift $shift)
    {
        if (!$this->inScope($shift, $request->user())) {
            abort(403, 'Ce shift est en dehors des compagnies/agences autorisées pour votre rôle.');
        }

        return response()->json($this->attachCompaniesToOne($shift->load(['agency'])));
    }

    public function update(Request $request, Shift $shift)
    {
        if (!$this->inScope($shift, $request->user())) {
            abort(403, 'Ce shift est en dehors des compagnies/agences autorisées pour votre rôle.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:191',
            'company_ids' => 'sometimes|array|min:1',
            'company_ids.*' => 'exists:companies,id',
            'agency_id' => 'sometimes|exists:agencies,id',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'color' => 'nullable|string|max:7',
        ]);

        $actingUser = $request->user();
        if ($actingUser->effectiveAccessLevel() === 'restricted') {
            $newCompanyIds = $validated['company_ids'] ?? ($shift->company_ids ?? []);
            $newAgencyId = $validated['agency_id'] ?? $shift->agency_id;
            if (!$actingUser->allowsAllCompanies($newCompanyIds) || !$actingUser->allowsAgency($newAgencyId)) {
                abort(403, 'Vous ne pouvez pas déplacer ce shift vers une compagnie/agence en dehors de vos autorisations.');
            }
        }

        $shift->update($validated);
        return response()->json($this->attachCompaniesToOne($shift->load(['agency'])));
    }

    public function destroy(Request $request, Shift $shift)
    {
        if (!$this->inScope($shift, $request->user())) {
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
