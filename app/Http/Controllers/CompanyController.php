<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Services\CompanyLogoStorage;

class CompanyController extends Controller
{
    public function __construct(private CompanyLogoStorage $logoStorage)
    {
    }

    public function index()
    {
        return response()->json(Company::withCount('users')->get());
    }

    public function store(Request $request)
    {
        // 'logo' is either an uploaded image file or a 2-letter initials
        // string — validate against whichever was actually sent, since the
        // `file`/`image` rules reject a plain string outright.
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'logo' => $request->hasFile('logo') ? 'nullable|file|image|max:2048' : 'nullable|string|max:191',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
        ]);

        // To support both initials and file uploads, we can do manual check
        $data = $request->only(['name', 'description', 'color']);
        if ($request->hasFile('logo')) {
            $data['logo'] = $this->logoStorage->store($request->file('logo'));
        } elseif ($request->has('logo') && is_string($request->logo)) {
            $data['logo'] = substr($request->logo, 0, 2);
        }

        $company = Company::create($data);

        return response()->json($company, 201);
    }

    public function show(Company $company)
    {
        return response()->json($company);
    }

    public function update(Request $request, Company $company)
    {
        $request->validate([
            'name' => 'sometimes|string|max:191',
            'logo' => $request->hasFile('logo') ? 'nullable|file|image|max:2048' : 'nullable|string|max:191',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
        ]);

        $data = $request->only(['name', 'description', 'color']);
        if ($request->hasFile('logo')) {
            $this->logoStorage->delete($company->logo);
            $data['logo'] = $this->logoStorage->store($request->file('logo'));
        } elseif ($request->has('logo') && is_string($request->logo)) {
            $data['logo'] = substr($request->logo, 0, 2);
        }

        $company->update($data);

        return response()->json($company);
    }

    public function destroy(Company $company)
    {
        if ($company->users()->exists()) {
            return response()->json([
                'message' => 'Cette compagnie a encore des employés rattachés et ne peut pas être supprimée. Réaffectez ou supprimez d\'abord ces employés.',
            ], 422);
        }

        $this->logoStorage->delete($company->logo);
        $company->delete();
        return response()->json(null, 204);
    }
}
