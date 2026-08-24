<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shift;

class ShiftController extends Controller
{
    public function index()
    {
        return response()->json(
            Shift::with(['company', 'agency'])->withCount('users')->orderBy('name')->get()
        );
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

        $shift = Shift::create($validated);
        return response()->json($shift->load(['company', 'agency']), 201);
    }

    public function show(Shift $shift)
    {
        return response()->json($shift->load(['company', 'agency']));
    }

    public function update(Request $request, Shift $shift)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:191',
            'company_id' => 'sometimes|exists:companies,id',
            'agency_id' => 'sometimes|exists:agencies,id',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'color' => 'nullable|string|max:7',
        ]);

        $shift->update($validated);
        return response()->json($shift->load(['company', 'agency']));
    }

    public function destroy(Shift $shift)
    {
        if ($shift->users()->exists()) {
            return response()->json([
                'message' => 'Ce shift a encore des employés rattachés et ne peut pas être supprimé. Réaffectez ou supprimez d\'abord ces employés.',
            ], 422);
        }

        $shift->delete();
        return response()->json(null, 204);
    }
}
