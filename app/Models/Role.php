<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'description', 'access_level', 'allowed_zones', 'allowed_companies', 'allowed_agencies', 'interface_access', 'permissions'])]
class Role extends Model
{
    protected function casts(): array
    {
        return [
            'allowed_zones' => 'array',
            'allowed_companies' => 'array',
            'allowed_agencies' => 'array',
            'interface_access' => 'array',
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

    /**
     * Whether this role can see/act on the given agency. An empty
     * allowed_agencies list means "all agencies".
     */
    public function allowsAgency($agencyId): bool
    {
        if ($this->access_level === 'full') {
            return true;
        }
        if (empty($this->allowed_agencies)) {
            return true;
        }
        if ($agencyId === null) {
            return false;
        }

        return in_array((string) $agencyId, array_map('strval', $this->allowed_agencies), true);
    }

    /**
     * Whether this role can even navigate to the given screen (e.g.
     * 'calendar', 'users'). Independent of canDo()'s create/read/update/
     * delete matrix — a role could be allowed to read users but still have
     * the Employés screen hidden, or vice versa. An empty interface_access
     * list means "all interfaces", same convention as allowed_zones/
     * allowed_companies.
     */
    public function canAccessInterface(string $interface): bool
    {
        if ($this->access_level === 'full') {
            return true;
        }
        if ($this->access_level === 'none') {
            return false;
        }
        if (empty($this->interface_access)) {
            return true;
        }

        return in_array($interface, $this->interface_access, true);
    }
}
