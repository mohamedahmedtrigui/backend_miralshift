<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'logo', 'color', 'description'])]
class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'logo', 'color', 'description'];
    
    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute()
    {
        if ($this->logo && str_contains($this->logo, 'logos/')) {
            return asset('storage/' . $this->logo);
        }
        return null;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
