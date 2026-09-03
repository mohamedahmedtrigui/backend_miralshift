<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['first_name', 'last_name', 'phone', 'agency_id', 'dispatch_zones', 'day_off', 'shift_id', 'start_date', 'company_ids', 'username', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'dispatch_zones' => 'array',
            'company_ids' => 'array',
        ];
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function hasAnyRole(): bool
    {
        return $this->roles->isNotEmpty();
    }

    /**
     * The user's overall access level across all their roles: 'full' if any
     * role grants full access, else 'restricted' if any role is restricted,
     * else 'none' (including when the user has no role at all). Cumulating
     * roles only ever widens access, never narrows it.
     */
    public function effectiveAccessLevel(): string
    {
        if ($this->roles->contains('access_level', 'full')) {
            return 'full';
        }
        if ($this->roles->contains('access_level', 'restricted')) {
            return 'restricted';
        }

        return 'none';
    }

    public function hasFullAccess(): bool
    {
        return $this->effectiveAccessLevel() === 'full';
    }

    public function isBlocked(): bool
    {
        return $this->effectiveAccessLevel() === 'none';
    }

    /**
     * Whether ANY of the user's roles may perform $action on $resource —
     * roles only add permissions, they never take one away.
     */
    public function canDo(string $resource, string $action): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->canDo($resource, $action));
    }

    public function canAccessInterface(string $interface): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->canAccessInterface($interface));
    }

    /**
     * Union of $field across the user's restricted roles. Null means
     * unrestricted (full access, no restricted role, or one of the
     * restricted roles leaves this field empty — which per Role's own
     * convention means "all").
     */
    private function unionScope(string $field): ?array
    {
        if ($this->hasFullAccess()) {
            return null;
        }

        $restrictedRoles = $this->roles->where('access_level', 'restricted');
        if ($restrictedRoles->isEmpty()) {
            return null;
        }
        if ($restrictedRoles->contains(fn (Role $role) => empty($role->{$field}))) {
            return null;
        }

        return $restrictedRoles->flatMap(fn (Role $role) => $role->{$field} ?? [])->unique()->values()->all();
    }

    public function allowedCompaniesScope(): ?array
    {
        return $this->unionScope('allowed_companies');
    }

    public function allowedZonesScope(): ?array
    {
        return $this->unionScope('allowed_zones');
    }

    public function allowedAgenciesScope(): ?array
    {
        return $this->unionScope('allowed_agencies');
    }

    public function allowsZone(?string $zone): bool
    {
        $scope = $this->allowedZonesScope();
        if ($scope === null) {
            return true;
        }

        return $zone !== null && in_array($zone, $scope, true);
    }

    public function allowsAnyCompany(array $companyIds): bool
    {
        $scope = $this->allowedCompaniesScope();
        if ($scope === null) {
            return true;
        }

        return !empty(array_intersect(array_map('strval', $scope), array_map('strval', $companyIds)));
    }

    public function allowsAllCompanies(array $companyIds): bool
    {
        $scope = $this->allowedCompaniesScope();
        if ($scope === null) {
            return true;
        }

        return empty(array_diff(array_map('strval', $companyIds), array_map('strval', $scope)));
    }

    public function allowsAgency($agencyId): bool
    {
        $scope = $this->allowedAgenciesScope();
        if ($scope === null) {
            return true;
        }
        if ($agencyId === null) {
            return false;
        }

        return in_array((string) $agencyId, array_map('strval', $scope), true);
    }
}
