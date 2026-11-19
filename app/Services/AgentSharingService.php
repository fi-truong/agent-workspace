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
                    $shared->where('is_shared', true)->whereIn('sharing_scope', ['school', 'department', 'users'])->where(function (Builder $audience) use ($user): void {
                        $audience->where('sharing_scope', 'school')
                            ->orWhere(function (Builder $department) use ($user): void {
                                $department->whereNotNull('sharing_department')
                                    ->where('sharing_department', '!=', '')
                                    ->whereRaw('LOWER(TRIM(sharing_department)) = ?', [mb_strtolower(trim($user->department ?? ''))]);
                            })
                            ->orWhere(function (Builder $people) use ($user): void {
                                $people->whereHas('sharedRecipients', fn (Builder $users) => $users->where('users.id', $user->id));
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

        if (! in_array($agent->sharing_scope, ['school', 'department', 'users'], true)) {
            return false;
        }
        if ($agent->sharing_scope === 'school'
            || (trim($user->department ?? '') !== ''
                && mb_strtolower(trim($user->department ?? '')) === mb_strtolower(trim($agent->sharing_department ?? '')))) {
            return true;
        }

        return $agent->relationLoaded('sharedRecipients')
            ? $agent->sharedRecipients->contains('id', $user->id)
            : $agent->sharedRecipients()->where('users.id', $user->id)->exists();
    }

    public function canUse(User $user, Agent $agent): bool
    {
        return $user->id === $agent->user_id || ($agent->sharing_access !== 'copy' && $this->canView($user, $agent));
    }

    /** @return array{attributes: array<string, mixed>, recipients: array<int, int>} */
    public function settings(Request $request, ?Agent $agent = null): array
    {
        $shared = $request->boolean('is_shared');
        $scopes = [];
        if ($shared) {
            if ($request->has('sharing_scopes') || $request->boolean('sharing_audience_configured')) {
                $scopes = $request->input('sharing_scopes', []);
            } elseif ($request->has('sharing_scope')) {
                // Older forms add a department or people without erasing existing limited grants.
                // A school-wide grant is retained only when explicitly selected.
                $scopes = [$request->input('sharing_scope')];
                foreach ($agent?->sharingAudiences() ?? [] as $existing) {
                    if ($existing !== 'school') {
                        $scopes[] = $existing;
                    }
                }
                $scopes = array_values(array_unique($scopes));
            } else {
                $scopes = $agent?->sharingAudiences() ?: ['school'];
            }
            if ($scopes === []) {
                throw ValidationException::withMessages(['sharing_scopes' => 'Choose at least one sharing audience.']);
            }
        }
        $department = null;
        $recipients = [];
        if (in_array('department', $scopes, true)) {
            $department = $agent?->sharing_department ?: trim($request->user()->department ?? '');
            if (! $department) {
                throw ValidationException::withMessages(['sharing_scopes' => 'Your account needs a department before you can share within it.']);
            }
        }
        if (in_array('users', $scopes, true)) {
            $recipients = array_map('intval', $request->input('sharing_user_ids',
                ! $request->boolean('sharing_recipients_configured') && $agent
                    ? $agent->sharedRecipients()->pluck('users.id')->all() : []));
            if ($recipients === [] || in_array($request->user()->id, $recipients, true)
                || User::query()->where('is_active', true)->whereIn('id', $recipients)->count() !== count($recipients)) {
                throw ValidationException::withMessages(['sharing_user_ids' => 'Choose at least one other active AI+ user from the search results.']);
            }
        }
        $scope = in_array('school', $scopes, true) ? 'school'
            : ($department !== null ? 'department' : ($shared ? 'users' : 'school'));

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
