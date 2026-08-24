<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Agency;

class AgencyController extends Controller
{
    public function index()
    {
        return response()->json(Agency::withCount('users')->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191|unique:agencies',
        ]);

        $agency = Agency::create($validated);
        return response()->json($agency, 201);
    }

    public function show(Agency $agency)
    {
        return response()->json($agency);
    }

    public function update(Request $request, Agency $agency)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191|unique:agencies,name,'.$agency->id,
        ]);

        $agency->update($validated);
        return response()->json($agency);
    }

    public function destroy(Agency $agency)
    {
        if ($agency->users()->exists()) {
            return response()->json([
                'message' => 'Cette agence a encore des employés rattachés et ne peut pas être supprimée. Réaffectez ou supprimez d\'abord ces employés.',
            ], 422);
        }

        $agency->delete();
        return response()->json(null, 204);
    }
}
