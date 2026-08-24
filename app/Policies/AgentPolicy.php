<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\User;

class AgentPolicy
{
    public function view(User $user, Agent $agent): bool
    {
        return $user->id === $agent->user_id || $agent->is_shared;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Agent $agent): bool
    {
        return $user->id === $agent->user_id;
    }

    public function delete(User $user, Agent $agent): bool
    {
        return $user->id === $agent->user_id;
    }
}