<?php

use App\Models\ShowcaseComment;
use App\Models\ShowcasePost;
use App\Models\ShowcaseView;
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

test('a showcase comment containing sensitive personal information is not published', function () {
    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $showcase = publishedShowcase($author);

    $this->actingAs($commenter)
        ->from(route('ai-plus.sharing-showcase.show', $showcase))
        ->post(route('ai-plus.sharing-showcase.comments.store', $showcase), [
            'content' => 'Please contact me at external.person@example.com',
        ])
        ->assertRedirect(route('ai-plus.sharing-showcase.show', $showcase).'#comments')
        ->assertSessionHasErrors('content');

    expect(ShowcaseComment::count())->toBe(0)
        ->and($showcase->fresh()->comments_count)->toBe(0);
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

test('showcase activity tabs use published dates and live counters instead of legacy badges', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    $old = ShowcasePost::create([
        'author_id' => $author->id,
        'department' => 'CIEC',
        'title' => 'Older shared agent',
        'description' => 'Published before the new window.',
        'status' => 'published',
        'published_at' => now()->subDays(8),
        'views_count' => 100,
        'uses_count' => 30,
        'badge' => 'New',
    ]);
    $new = ShowcasePost::create([
        'author_id' => $author->id,
        'department' => 'CIEC',
        'title' => 'Newly shared agent',
        'description' => 'Published within the new window.',
        'status' => 'published',
        'published_at' => now()->subDay(),
        'views_count' => 10,
        'uses_count' => 5,
    ]);
    $mostUsed = ShowcasePost::create([
        'author_id' => $author->id,
        'department' => 'CIEC',
        'title' => 'Most used agent',
        'description' => 'Uses are highest.',
        'status' => 'published',
        'published_at' => now()->subDays(2),
        'views_count' => 50,
        'uses_count' => 40,
    ]);

    $this->actingAs($viewer)
        ->get(route('ai-plus.sharing-showcase.index', ['view' => 'new']))
        ->assertOk()
        ->assertSee($new->title)
        ->assertSee($mostUsed->title)
        ->assertDontSee($old->title);

    $this->actingAs($viewer)
        ->get(route('ai-plus.sharing-showcase.index', ['view' => 'trending']))
        ->assertOk()
        ->assertSeeInOrder([$old->title, $mostUsed->title, $new->title]);

    $this->actingAs($viewer)
        ->get(route('ai-plus.sharing-showcase.index', ['view' => 'mostused']))
        ->assertOk()
        ->assertSeeInOrder([$mostUsed->title, $old->title, $new->title]);
});

test('a showcase records only one view per user per day', function () {
    $author = User::factory()->create();
    $firstViewer = User::factory()->create();
    $secondViewer = User::factory()->create();
    $showcase = publishedShowcase($author);

    $this->actingAs($firstViewer)
        ->get(route('ai-plus.sharing-showcase.show', $showcase))
        ->assertOk();
    $this->actingAs($firstViewer)
        ->get(route('ai-plus.sharing-showcase.show', $showcase))
        ->assertOk();

    expect($showcase->fresh()->views_count)->toBe(1)
        ->and(ShowcaseView::count())->toBe(1);

    $this->actingAs($secondViewer)
        ->get(route('ai-plus.sharing-showcase.show', $showcase))
        ->assertOk();

    expect($showcase->fresh()->views_count)->toBe(2)
        ->and(ShowcaseView::count())->toBe(2);
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
