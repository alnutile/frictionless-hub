<?php

namespace App\Policies;

use App\Models\Initiative;
use App\Models\User;

class InitiativePolicy
{
    /**
     * Every signed-in role can browse initiatives.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Initiative $initiative): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canFacilitate();
    }

    /**
     * Admins manage everything; facilitators manage the initiatives they own.
     * Child records (stages, tools, dimensions, friction) authorize through this.
     */
    public function update(User $user, Initiative $initiative): bool
    {
        return $user->isAdmin()
            || ($user->canFacilitate() && $initiative->owner_id === $user->id);
    }

    public function delete(User $user, Initiative $initiative): bool
    {
        return $this->update($user, $initiative);
    }
}
