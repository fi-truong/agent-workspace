<?php

use App\Models\ShowcaseComment;
use App\Models\ShowcasePost;
use App\Models\User;

function publishedShowcase(User $author): ShowcasePost
{
    return ShowcasePost::create([
        'author_id' => $author->id,
        'department' => 'CIEC',
        'title' => 'Helpful agent',
        'description' => 'A helpful agent that supports classroom planning.',
        'status' => 'published',
    ]);
}

test('a signed-in user can post a showcase comment', function () {
    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $showcase = publishedShowcase($author);

    $this->actingAs($commenter)
        ->post(route('ai-plus.sharing-showcase.comments.store', $showcase), ['content' => 'This is very useful, thank you!'])
        ->assertRedirect(route('ai-plus.sharing-showcase.show', $showcase).'#comments');

    expect(ShowcaseComment::count())->toBe(1)
        ->and($showcase->fresh()->comments_count)->toBe(1);

    $this->actingAs($commenter)
        ->get(route('ai-plus.sharing-showcase.show', $showcase))
        ->assertOk()
        ->assertSee('This is very useful, thank you!');
});

test('the showcase list uses real comment records instead of a stale cached count', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $showcase = publishedShowcase($author);
    $showcase->update(['comments_count' => 99]);
    ShowcaseComment::create([
        'showcase_post_id' => $showcase->id,
        'user_id' => $viewer->id,
        'content' => 'The only real comment.',
    ]);

    $this->actingAs($viewer)
        ->get(route('ai-plus.sharing-showcase.index'))
        ->assertOk()
        ->assertSee('1 comments')
        ->assertDontSee('99 comments');
});

test('a user can delete only their own comment while an admin can moderate any comment', function () {
    $author = User::factory()->create();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $showcase = publishedShowcase($author);
    $comment = ShowcaseComment::create([
        'showcase_post_id' => $showcase->id,
        'user_id' => $owner->id,
        'content' => 'Comment to moderate.',
    ]);
    $showcase->update(['comments_count' => 1]);

    $this->actingAs($otherUser)
        ->delete(route('ai-plus.sharing-showcase.comments.destroy', [$showcase, $comment]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('ai-plus.sharing-showcase.comments.destroy', [$showcase, $comment]))
        ->assertRedirect(route('ai-plus.sharing-showcase.show', $showcase).'#comments');

    expect(ShowcaseComment::count())->toBe(0)
        ->and($showcase->fresh()->comments_count)->toBe(0);
});
