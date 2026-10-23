<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\ShowcaseUse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AgentConnectionService
{
    public function enabled(): bool
    {
        return config('agent_connections.enabled', false) && Schema::hasTable('agent_connections');
    }

    public function canUse(User $user, Agent $agent): bool
    {
        return $agent->user_id === $user->id || ($agent->is_shared && $agent->sharing_access !== 'copy');
    }

    /** @return Builder<Agent> */
    public function availableQuery(User $user): Builder
    {
        $savedSharedIds = ShowcaseUse::query()->where('user_id', $user->id)
            ->select('showcase_post_id');

        return Agent::query()->where(function (Builder $query) use ($user, $savedSharedIds): void {
            $query->where('user_id', $user->id)
                ->orWhere(function (Builder $shared) use ($savedSharedIds): void {
                    $shared->where('is_shared', true)->where('sharing_access', '!=', 'copy')
                        ->whereHas('showcasePosts', fn (Builder $posts) => $posts->whereIn('id', $savedSharedIds));
                });
        });
    }

    /** @return array<int, array{id: int, title: string, description: ?string}> */
    public function options(User $user): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return $this->availableQuery($user)->orderBy('title')->get(['id', 'title', 'description'])
            ->map(fn (Agent $agent): array => $agent->only(['id', 'title', 'description']))->all();
    }

    /** @return Collection<int, Agent> */
    public function forAgent(?Agent $agent, User $user): Collection
    {
        if ($agent === null || ! $this->enabled() || ! $this->canUse($user, $agent)) {
            return new Collection;
        }

        return $agent->connectedAgents()->where('agents.id', '!=', $agent->id)->get()
            ->filter(fn (Agent $child): bool => $this->canUse($user, $child))
            ->take((int) config('agent_connections.max_connections', 10))->values();
    }

    /** @return array<int, array{agent_id: int, role: ?string}> */
    public function setup(Agent $agent): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return $agent->connectedAgents()->get()->map(fn (Agent $child): array => [
            'agent_id' => $child->id,
            'role' => $child->getRelation('pivot')->getAttribute('role'),
        ])->all();
    }

    /** @param array<int, array{agent_id: int|string, role?: ?string}> $connections */
    public function validate(array $connections, User $user, ?Agent $agent = null): void
    {
        if ($connections !== [] && ! $this->enabled()) {
            throw ValidationException::withMessages(['connections' => 'Connected Agents are currently unavailable.']);
        }

        $ids = array_map(fn (array $connection): int => (int) $connection['agent_id'], $connections);
        if (($agent && in_array($agent->id, $ids, true))
            || $this->availableQuery($user)->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'connections' => 'Choose other Agents from your workspace that you still have permission to use.',
            ]);
        }
    }

    /** @param array<int, array{agent_id: int|string, role?: ?string}> $connections */
    public function sync(Agent $agent, array $connections): void
    {
        if (! $this->enabled()) {
            return;
        }

        $values = [];
        foreach ($connections as $connection) {
            $values[(int) $connection['agent_id']] = ['role' => $connection['role'] ?? null];
        }
        DB::transaction(fn () => $agent->connectedAgents()->sync($values));
    }
}
