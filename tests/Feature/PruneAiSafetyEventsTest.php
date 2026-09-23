<?php

use App\Models\AiSafetyEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('feature');

test('work-use review events older than 180 days are deleted', function () {
    $user = User::factory()->create();
    $expired = AiSafetyEvent::create([
        'user_id' => $user->id,
        'feature' => 'chat',
        'classification' => 'ambiguous',
        'action' => 'allowed',
        'moderation_flagged' => false,
    ]);
    $expired->forceFill(['created_at' => now()->subDays(181), 'updated_at' => now()->subDays(181)])->save();

    $current = AiSafetyEvent::create([
        'user_id' => $user->id,
        'feature' => 'chat',
        'classification' => 'personal_or_unrelated',
        'action' => 'allowed',
        'moderation_flagged' => false,
    ]);

    $this->artisan('ai-plus:prune-work-use-events')
        ->expectsOutput('Deleted 1 Work-Use Monitoring event(s) older than 180 days.')
        ->assertSuccessful();

    expect(AiSafetyEvent::find($expired->id))->toBeNull()
        ->and(AiSafetyEvent::find($current->id))->not->toBeNull();
});
