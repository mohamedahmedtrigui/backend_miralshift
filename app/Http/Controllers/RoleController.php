<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        // The Super Admin role itself is hidden from this list — it's the
        // platform's own built-in role, not one to be edited, reassigned, or
        // deleted from this screen. Other admin-level roles an operator
        // creates later still show up normally and stay manageable here.
        return response()->json(
            Role::withCount('users')
                ->where('name', '!=', 'Super Admin')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'access_level' => 'required|in:none,full,restricted',
            'allowed_zones' => 'nullable|array',
            'allowed_companies' => 'nullable|array',
            'permissions' => 'nullable|array',
        ]);

        $role = Role::create($validated);
        return response()->json($role, 201);
    }

    public function show(Role $role)
    {
        return response()->json($role);
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:191',
            'description' => 'nullable|string',
            'access_level' => 'sometimes|in:none,full,restricted',
            'allowed_zones' => 'nullable|array',
            'allowed_companies' => 'nullable|array',
            'permissions' => 'nullable|array',
        ]);

        $role->update($validated);
        return response()->json($role);
    }

    public function destroy(Role $role)
    {
        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'Ce rôle est encore assigné à des employés et ne peut pas être supprimé. Réaffectez ou supprimez d\'abord ces employés.',
            ], 422);
        }

        $role->delete();
        return response()->json(null, 204);
    }
}
