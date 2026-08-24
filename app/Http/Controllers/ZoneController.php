<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Zone;
use App\Models\User;
use App\Models\Role;

class ZoneController extends Controller
{
    public function index()
    {
        return response()->json(Zone::orderBy('name')->get());
    }

    /**
     * Zones read as open reference data to any authenticated user (dropdowns,
     * calendar filters), but writing them reshapes every role's scoping and
     * every employee's dispatch assignment — restrict that to full-access
     * accounts only, same as agencies.
     */
    private function assertFullAccess(Request $request): void
    {
        if ($request->user()?->role?->access_level !== 'full') {
            abort(403, 'Seul un accès complet peut créer, modifier ou supprimer une zone.');
        }
    }

    public function store(Request $request)
    {
        $this->assertFullAccess($request);

        $validated = $request->validate([
            'name' => 'required|string|max:191|unique:zones',
        ]);

        $zone = Zone::create($validated);
        return response()->json($zone, 201);
    }

    public function show(Zone $zone)
    {
        return response()->json($zone);
    }

    public function update(Request $request, Zone $zone)
    {
        $this->assertFullAccess($request);

        $validated = $request->validate([
            'name' => 'required|string|max:191|unique:zones,name,'.$zone->id,
        ]);

        $zone->update($validated);
        return response()->json($zone);
    }

    public function destroy(Request $request, Zone $zone)
    {
        $this->assertFullAccess($request);

        // dispatch_zones/allowed_zones store zone *names* (not a FK), so a
        // dependent-row check has to search by name instead of a relation.
        if (User::whereJsonContains('dispatch_zones', $zone->name)->exists()) {
            return response()->json([
                'message' => 'Cette zone est encore assignée à des employés et ne peut pas être supprimée. Retirez-la d\'abord de leurs zones de dispatch.',
            ], 422);
        }

        if (Role::whereJsonContains('allowed_zones', $zone->name)->exists()) {
            return response()->json([
                'message' => 'Cette zone est encore utilisée par un rôle et ne peut pas être supprimée. Retirez-la d\'abord des zones autorisées de ce rôle.',
            ], 422);
        }

        $zone->delete();
        return response()->json(null, 204);
    }
}
