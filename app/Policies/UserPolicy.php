<?php

namespace App\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function permissions(User $user, User $user_model)
    {
        if ($user->hasPermissionTo('admin users')) {
            return true;
        }

        return $user->id === $user_model->id;
    }
}
