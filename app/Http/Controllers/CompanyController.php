<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function index()
    {
        return response()->json(Company::withCount('users')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'logo' => 'nullable|file|image|max:2048', // Allow file upload or string for initials
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
        ]);

        // To support both initials and file uploads, we can do manual check
        $data = $request->only(['name', 'description', 'color']);
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        } elseif ($request->has('logo') && is_string($request->logo)) {
            $data['logo'] = substr($request->logo, 0, 2);
        }

        $company = Company::create($data);
        
        // Build public URL for response if it's a file path
        $company->logo_url = $company->logo && str_contains($company->logo, 'logos/') 
            ? asset('storage/' . $company->logo) 
            : null;

        return response()->json($company, 201);
    }

    public function show(Company $company)
    {
        $company->logo_url = $company->logo && str_contains($company->logo, 'logos/') 
            ? asset('storage/' . $company->logo) 
            : null;
        return response()->json($company);
    }

    public function update(Request $request, Company $company)
    {
        $request->validate([
            'name' => 'sometimes|string|max:191',
            'logo' => 'nullable', // can be file or string
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
        ]);

        $data = $request->only(['name', 'description', 'color']);
        if ($request->hasFile('logo')) {
            // delete old logo
            if ($company->logo && str_contains($company->logo, 'logos/')) {
                Storage::disk('public')->delete($company->logo);
            }
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        } elseif ($request->has('logo') && is_string($request->logo)) {
            $data['logo'] = substr($request->logo, 0, 2);
        }

        $company->update($data);
        
        $company->logo_url = $company->logo && str_contains($company->logo, 'logos/') 
            ? asset('storage/' . $company->logo) 
            : null;
            
        return response()->json($company);
    }

    public function destroy(Company $company)
    {
        if ($company->users()->exists()) {
            return response()->json([
                'message' => 'Cette compagnie a encore des employés rattachés et ne peut pas être supprimée. Réaffectez ou supprimez d\'abord ces employés.',
            ], 422);
        }

        if ($company->logo && str_contains($company->logo, 'logos/')) {
            Storage::disk('public')->delete($company->logo);
        }
        $company->delete();
        return response()->json(null, 204);
    }
}
