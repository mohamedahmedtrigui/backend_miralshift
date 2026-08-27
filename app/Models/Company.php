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

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
