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
            'allowed_agencies' => 'nullable|array',
            'interface_access' => 'nullable|array',
            'permissions' => 'nullable|array',
        ]);

        $actingRole = $request->user()?->role;
        if ($actingRole?->access_level !== 'full' && $validated['access_level'] === 'full') {
            abort(403, 'Vous ne pouvez pas créer un rôle à accès complet.');
        }

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
            'allowed_agencies' => 'nullable|array',
            'interface_access' => 'nullable|array',
            'permissions' => 'nullable|array',
        ]);

        $actingUser = $request->user();
        $actingRole = $actingUser?->role;

        // A restricted role editing its own role row (or granting anyone
        // full access) is the direct path to self-escalation — a
        // `permissions.roles: [update]` grant is meant for managing OTHER
        // roles, not widening one's own scope/permissions/access_level.
        if ($actingRole && $actingRole->access_level !== 'full') {
            if ($actingUser->role_id === $role->id) {
                abort(403, 'Vous ne pouvez pas modifier votre propre rôle.');
            }
            if (($validated['access_level'] ?? $role->access_level) === 'full') {
                abort(403, 'Vous ne pouvez pas accorder un accès complet.');
            }
        }

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
