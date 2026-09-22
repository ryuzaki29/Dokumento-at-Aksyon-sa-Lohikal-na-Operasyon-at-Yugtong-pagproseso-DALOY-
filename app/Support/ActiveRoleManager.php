<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Role;

/**
 * Narrows a multi-role user down to a single "acting as" role per request.
 *
 * spatie/laravel-permission's hasRole()/hasPermissionTo() checks all read
 * from the model's loaded `roles` relation (see HasRoles::hasRole() and
 * HasPermissions::hasPermissionViaRole()), so overriding that relation with
 * just the active role — before any permission check runs — is enough to
 * restrict every Gate/Policy check app-wide without touching each call site.
 */
class ActiveRoleManager
{
    private const SESSION_KEY = 'active_role_id';

    /**
     * The user's true, unscoped set of assigned roles, straight from the
     * database — never affected by apply()'s in-memory narrowing.
     */
    public static function assignedRoles(User $user): Collection
    {
        return $user->roles()->orderBy('name')->get();
    }

    public static function resolve(User $user): ?Role
    {
        $roles = self::assignedRoles($user);

        if ($roles->isEmpty()) {
            return null;
        }

        $sessionRoleId = Session::get(self::SESSION_KEY);

        return $roles->firstWhere('id', $sessionRoleId) ?? $roles->first();
    }

    /**
     * Narrow $user's loaded `roles` relation to just the active role, so
     * every hasRole()/can() check for the rest of this request sees only it.
     */
    public static function apply(User $user): void
    {
        $role = self::resolve($user);

        if ($role) {
            $user->setRelation('roles', collect([$role]));
        }
    }

    public static function switchTo(User $user, int $roleId): bool
    {
        if (! $user->roles()->whereKey($roleId)->exists()) {
            return false;
        }

        Session::put(self::SESSION_KEY, $roleId);

        return true;
    }
}
