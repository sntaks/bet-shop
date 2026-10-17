<?php

namespace App\Policies;

use App\Models\User;

/**
 * Managers add and change their own shop's staff; admins anyone's. Nobody deletes them: they
 * are deactivated, so the activity log keeps their names.
 */
class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('users.manage');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo('users.manage') && $this->sharesShop($user, $model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('users.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasPermissionTo('users.manage') && $this->sharesShop($user, $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Admins act on anyone; everyone else only on their own shop's staff.
     */
    private function sharesShop(User $user, User $model): bool
    {
        return $user->isAdmin() || ($user->shop_id !== null && $user->shop_id === $model->shop_id);
    }
}
