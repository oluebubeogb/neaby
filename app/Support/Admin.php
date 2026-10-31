<?php

namespace App\Support;

use App\Models\User;

/** Admin = users.is_admin flag OR username listed in ADMIN_USERNAMES. */
class Admin
{
    public static function check(?User $user): bool
    {
        if (! $user || $user->isSuspended()) {
            return false;
        }
        if ($user->is_admin) {
            return true;
        }

        return in_array(strtolower((string) $user->username), config('neaby.admin_usernames', []), true);
    }
}
