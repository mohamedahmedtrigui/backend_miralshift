<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Services\CompanyLogoStorage;

#[Fillable(['name', 'logo', 'color', 'description'])]
class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'logo', 'color', 'description'];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute()
    {
        return app(CompanyLogoStorage::class)->url($this->logo);
    }

    // Not a real Eloquent relation — an employee's companies are now a JSON
    // array (users.company_ids), not a single FK. Returns a plain query
    // builder so `$company->users()->exists()` still works as a delete
    // guard; `withCount('users')` no longer works since this isn't a
    // Relation instance (see CompanyController@index).
    public function users()
    {
        return User::whereJsonContains('company_ids', (string) $this->id);
    }
}
