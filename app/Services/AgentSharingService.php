<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgentSharingService
{
    /** @param Builder<Agent> $query */
    public function constrain(Builder $query, User $user): void
    {
        $query->where(function (Builder $visible) use ($user): void {
            $visible->where('agents.user_id', $user->id)
                ->orWhere(function (Builder $shared) use ($user): void {
                    $shared->where('is_shared', true)->where(function (Builder $audience) use ($user): void {
                        $audience->where('sharing_scope', 'school')
                            ->orWhere(function (Builder $department) use ($user): void {
                                $department->where('sharing_scope', 'department')
                                    ->whereNotNull('sharing_department')
                                    ->where('sharing_department', '!=', '')
                                    ->whereRaw('LOWER(TRIM(sharing_department)) = ?', [mb_strtolower(trim($user->department ?? ''))]);
                            })
                            ->orWhere(function (Builder $people) use ($user): void {
                                $people->where('sharing_scope', 'users')
                                    ->whereHas('sharedRecipients', fn (Builder $users) => $users->where('users.id', $user->id));
                            });
                    });
                });
        });
    }

    public function canView(User $user, Agent $agent): bool
    {
        if ($user->id === $agent->user_id) {
            return true;
        }
        if (! $user->is_active || ! $agent->is_shared) {
            return false;
        }

        return match ($agent->sharing_scope) {
            'school' => true,
            'department' => trim($user->department ?? '') !== ''
                && mb_strtolower(trim($user->department ?? '')) === mb_strtolower(trim($agent->sharing_department ?? '')),
            'users' => $agent->relationLoaded('sharedRecipients')
                ? $agent->sharedRecipients->contains('id', $user->id)
                : $agent->sharedRecipients()->where('users.id', $user->id)->exists(),
            default => false,
        };
    }

    public function canUse(User $user, Agent $agent): bool
    {
        return $user->id === $agent->user_id || ($agent->sharing_access !== 'copy' && $this->canView($user, $agent));
    }

    /** @return array{attributes: array<string, mixed>, recipients: array<int, int>} */
    public function settings(Request $request, ?Agent $agent = null): array
    {
        $shared = $request->boolean('is_shared');
        $scope = $shared ? $request->input('sharing_scope', $agent->sharing_scope ?? 'school') : 'school';
        $department = null;
        $recipients = [];
        if ($shared && $scope === 'department') {
            $department = $agent?->sharing_scope === 'department'
                ? $agent->sharing_department : trim($request->user()->department ?? '');
            if (! $department) {
                throw ValidationException::withMessages(['sharing_scope' => 'Your account needs a department before you can share within it.']);
            }
        }
        if ($shared && $scope === 'users') {
            $recipients = array_map('intval', $request->input('sharing_user_ids',
                ! $request->boolean('sharing_recipients_configured') && $agent?->sharing_scope === 'users' ? $agent->sharedRecipients()->pluck('users.id')->all() : []));
            if ($recipients === [] || in_array($request->user()->id, $recipients, true)
                || User::query()->where('is_active', true)->whereIn('id', $recipients)->count() !== count($recipients)) {
                throw ValidationException::withMessages(['sharing_user_ids' => 'Choose at least one other active AI+ user from the search results.']);
            }
        }

        return [
            'attributes' => [
                'is_shared' => $shared,
                'sharing_access' => $shared ? $request->input('sharing_access') : 'use_only',
                'sharing_scope' => $scope,
                'sharing_department' => $department,
                'shared_with_team_id' => null,
            ],
            'recipients' => $recipients,
        ];
    }

    /** @param array{attributes: array<string, mixed>, recipients: array<int, int>} $settings */
    public function save(Agent $agent, array $settings): void
    {
        DB::transaction(function () use ($agent, $settings): void {
            $agent->update($settings['attributes']);
            $agent->sharedRecipients()->sync($settings['recipients']);
        });
    }
}
