<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Company;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Identifiants invalides'], 401);
        }

        if ($user->isBlocked()) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load('roles');
        $companiesById = Company::all()->keyBy('id');
        $user->companies = collect($user->company_ids ?? [])
            ->map(fn ($id) => $companiesById->get((int) $id))
            ->filter()
            ->values();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnexion réussie']);
    }
}
