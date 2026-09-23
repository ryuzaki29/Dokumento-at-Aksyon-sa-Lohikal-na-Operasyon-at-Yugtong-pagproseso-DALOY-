<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Route;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RoutePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Route');
    }

    public function view(AuthUser $authUser, Route $route): bool
    {
        return $authUser->can('View:Route');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Route');
    }

    public function update(AuthUser $authUser, Route $route): bool
    {
        return $authUser->can('Update:Route');
    }

    public function delete(AuthUser $authUser, Route $route): bool
    {
        return $authUser->can('Delete:Route');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Route');
    }

    public function restore(AuthUser $authUser, Route $route): bool
    {
        return $authUser->can('Restore:Route');
    }

    public function forceDelete(AuthUser $authUser, Route $route): bool
    {
        return $authUser->can('ForceDelete:Route');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Route');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Route');
    }

    public function replicate(AuthUser $authUser, Route $route): bool
    {
        return $authUser->can('Replicate:Route');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Route');
    }
}
