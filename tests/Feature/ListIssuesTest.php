<?php

use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->issueFields = [
        'title'           => 'Duplicate charge',
        'description'     => 'Two charges appeared on the statement.',
        'category'        => 'billing',
        'priority'        => 'high',
        'status'          => 'open',
        'needs_attention' => true,
    ];
});

test('the issue list combines filters and omits detail relationships', function () {
    $actor = User::factory()->create();
    $matchingIssue = Issue::query()->create($this->issueFields);

    Issue::query()->create([...$this->issueFields, 'status' => 'resolved']);
    Issue::query()->create([...$this->issueFields, 'priority' => 'low', 'needs_attention' => false]);
    Issue::query()->create([...$this->issueFields, 'category' => 'access']);

    $matchingIssue->comments()->create(['author_name' => $actor->name, 'body' => 'Checking both charges.']);
    $matchingIssue->logs()->create(['user_id' => $actor->getKey(), 'action' => 'created']);

    $this->actingAs($actor, 'web');
    $this->expectsDatabaseQueryCount(2);

    $this->getJson('/api/issues?status=open&priority=high&category=billing')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingIssue->getKey())
        ->assertJsonPath('data.0.needs_attention', true)
        ->assertJsonMissingPaths(['data.0.comments', 'data.0.logs'])
        ->assertJsonPath('meta.total', 1);
});

test('issue pagination preserves filters and uses ids to break timestamp ties', function () {
    $now = now()->startOfDay();
    $this->travelTo($now);

    $newestIds = [];
    for ($index = 0; $index < 15; $index++) {
        $newestIds[] = Issue::query()->create($this->issueFields)->getKey();
    }
    Issue::query()->create([...$this->issueFields, 'category' => 'access']);

    $this->travelTo($now->copy()->subDay());
    $olderIssue = Issue::query()->create($this->issueFields);
    $this->travelBack();

    $this->actingAs(User::factory()->create(), 'web');
    $firstPage = $this->getJson('/api/issues?status=open&priority=high&category=billing')
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.total', 16);

    expect(array_column($firstPage->json('data'), 'id'))->toBe(array_reverse($newestIds));

    $next = $firstPage->json('links.next');
    parse_str(parse_url($next, PHP_URL_QUERY), $nextQuery);
    expect($nextQuery)->toMatchArray([
        'status'   => 'open',
        'priority' => 'high',
        'category' => 'billing',
        'page'     => '2',
    ]);

    $this->getJson($next)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $olderIssue->getKey())
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('links.next', null);
});

test('the list returns an empty page when no issues match', function () {
    Issue::query()->create($this->issueFields);

    $this->actingAs(User::factory()->create(), 'web')
        ->getJson('/api/issues?category=access')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total', 0);
});

test('invalid issue filters return validation errors', function (string $field, string $value) {
    $this->actingAs(User::factory()->create(), 'web')
        ->getJson('/api/issues?'.http_build_query([$field => $value]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'invalid status'   => ['status', 'closed'],
    'invalid priority' => ['priority', 'urgent'],
    'invalid category' => ['category', 'unknown'],
]);

test('guests cannot list issues', function () {
    $this->getJson('/api/issues')->assertUnauthorized();
});
