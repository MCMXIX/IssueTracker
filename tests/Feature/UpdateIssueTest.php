<?php

use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->issue = Issue::query()->create([
        'title'           => 'Duplicate charge',
        'description'     => 'Two charges appeared on the statement.',
        'category'        => 'billing',
        'priority'        => 'medium',
        'status'          => 'in_progress',
        'needs_attention' => false,
    ]);
    $this->issueUrl = '/api/issues/'.$this->issue->getKey();
});

test('a status-only update preserves the other fields and logs the original status', function () {
    $actor = User::factory()->create();

    $this->actingAs($actor, 'web')
        ->patchJson($this->issueUrl, ['status' => 'resolved'])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved');

    $this->assertDatabaseHas('issues', [
        'id'              => $this->issue->getKey(),
        'title'           => 'Duplicate charge',
        'description'     => 'Two charges appeared on the statement.',
        'category'        => 'billing',
        'priority'        => 'medium',
        'status'          => 'resolved',
        'needs_attention' => false,
    ]);

    $log = $this->issue->logs()->sole();
    expect($log->user_id)->toBe($actor->getKey())
        ->and($log->action)->toBe('updated')
        ->and($log->details)->toBe([
            'changes' => ['status' => ['from' => 'in_progress', 'to' => 'resolved']],
        ]);
});

test('priority changes audit the derived attention flag and a repeated priority is a no-op', function () {
    $this->actingAs(User::factory()->create(), 'web');

    $this->patchJson($this->issueUrl, ['priority' => 'high', 'needs_attention' => false])
        ->assertOk()
        ->assertJsonPath('data.needs_attention', true);

    $log = $this->issue->logs()->sole();
    expect($log->details)->toBe([
        'changes' => [
            'priority'        => ['from' => 'medium', 'to' => 'high'],
            'needs_attention' => ['from' => false, 'to' => true],
        ],
    ]);

    $updatedAt = $this->issue->refresh()->getRawOriginal('updated_at');
    $this->travel(1)->minutes();
    $this->patchJson($this->issueUrl, ['priority' => 'high'])
        ->assertOk()
        ->assertJsonPath('data.needs_attention', true);
    $this->travelBack();

    $this->assertDatabaseCount('issue_logs', 1);
    expect($this->issue->refresh()->getRawOriginal('updated_at'))->toBe($updatedAt);

    $this->patchJson($this->issueUrl, ['priority' => 'low'])
        ->assertOk()
        ->assertJsonPath('data.needs_attention', false);

    $secondLog = $this->issue->logs()->where('id', '!=', $log->getKey())->sole();
    expect($secondLog->details)->toBe([
        'changes' => [
            'priority'        => ['from' => 'high', 'to' => 'low'],
            'needs_attention' => ['from' => true, 'to' => false],
        ],
    ]);
});

test('text edits log actual values and ignore client-supplied generated fields', function () {
    $this->actingAs(User::factory()->create(), 'web')
        ->patchJson($this->issueUrl, [
            'title'                 => '  Updated charge report  ',
            'description'           => 'One charge was refunded.',
            'summary'               => 'Client summary',
            'suggested_next_action' => 'Client action',
            'summary_status'        => 'success',
            'needs_attention'       => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated charge report')
        ->assertJsonPath('data.summary', null)
        ->assertJsonPath('data.suggested_next_action', null)
        ->assertJsonPath('data.summary_status', 'pending')
        ->assertJsonPath('data.needs_attention', false);

    expect($this->issue->logs()->sole()->details)->toBe([
        'changes' => [
            'title'       => ['from' => 'Duplicate charge', 'to' => 'Updated charge report'],
            'description' => ['from' => 'Two charges appeared on the statement.', 'to' => 'One charge was refunded.'],
        ],
    ]);
});

test('invalid updates leave the issue and audit history untouched', function (string $field, ?string $value) {
    $original = $this->issue->refresh()->getRawOriginal();

    $this->actingAs(User::factory()->create(), 'web')
        ->patchJson($this->issueUrl, [$field => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect($this->issue->refresh()->getRawOriginal())->toBe($original);
    $this->assertDatabaseEmpty('issue_logs');
})->with([
    'blank title'      => ['title', '   '],
    'null description' => ['description', null],
    'invalid category' => ['category', 'unknown'],
    'invalid priority' => ['priority', 'urgent'],
    'invalid status'   => ['status', 'closed'],
]);

test('guests cannot update issues', function () {
    $this->patchJson($this->issueUrl, ['status' => 'resolved'])
        ->assertUnauthorized();

    $this->assertDatabaseHas('issues', ['id' => $this->issue->getKey(), 'status' => 'in_progress']);
    $this->assertDatabaseEmpty('issue_logs');
});

test('updating a missing issue returns not found', function () {
    $this->actingAs(User::factory()->create(), 'web')
        ->patchJson('/api/issues/'.($this->issue->getKey() + 1), ['status' => 'resolved'])
        ->assertNotFound();

    $this->assertDatabaseEmpty('issue_logs');
});
