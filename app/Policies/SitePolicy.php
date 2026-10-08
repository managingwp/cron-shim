<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    /**
     * v1 supports a single administrator, so any authenticated user may manage sites.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Site $site): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Site $site): bool
    {
        return true;
    }

    public function delete(User $user, Site $site): bool
    {
        return true;
    }

    public function rotate(User $user, Site $site): bool
    {
        return true;
    }
}
