<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'description', 'access_level', 'allowed_zones', 'allowed_companies', 'permissions'])]
class Role extends Model
{
    protected function casts(): array
    {
        return [
            'allowed_zones' => 'array',
            'allowed_companies' => 'array',
            'permissions' => 'array',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Whether this role may perform $action (create|read|update|delete) on
     * $resource (users|roles|companies). Full access always can, no access
     * never can, restricted access follows the stored permissions matrix.
     */
    public function canDo(string $resource, string $action): bool
    {
        if ($this->access_level === 'full') {
            return true;
        }
        if ($this->access_level === 'none') {
            return false;
        }

        return in_array($action, $this->permissions[$resource] ?? [], true);
    }

    /**
     * Whether this role can see/act on the given zone. An empty
     * allowed_zones list means "all zones" (matches the existing "All"
     * label shown in the Roles table for an empty list).
     */
    public function allowsZone(?string $zone): bool
    {
        if ($this->access_level === 'full') {
            return true;
        }
        if (empty($this->allowed_zones)) {
            return true;
        }

        return $zone !== null && in_array($zone, $this->allowed_zones, true);
    }

    /**
     * Whether this role can see/act on the given company. An empty
     * allowed_companies list means "all companies".
     */
    public function allowsCompany($companyId): bool
    {
        if ($this->access_level === 'full') {
            return true;
        }
        if (empty($this->allowed_companies)) {
            return true;
        }
        if ($companyId === null) {
            return false;
        }

        return in_array((string) $companyId, array_map('strval', $this->allowed_companies), true);
    }
}
