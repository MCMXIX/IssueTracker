<?php

use App\Models\IssueLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->payload = [
        'title'       => 'Customer was charged twice',
        'description' => 'Two charges appeared for invoice INV-4837.',
        'category'    => 'billing',
        'priority'    => 'high',
    ];
});

test('a signed-in user can create an issue with defaults and an audit record', function () {
    $actor = User::factory()->create(['name' => 'Demo Agent']);

    $response = $this->actingAs($actor, 'web')
        ->postJson('/api/issues', [
            ...$this->payload,
            'title'                 => '  '.$this->payload['title'].'  ',
            'description'           => '  '.$this->payload['description'].'  ',
            'needs_attention'       => false,
            'summary'               => 'Client-supplied summary',
            'summary_status'        => 'success',
            'suggested_next_action' => 'Client-supplied action',
        ])
        ->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('data', fn (AssertableJson $issue) => $issue
                ->whereType('id', 'integer')
                ->whereAll([
                    ...$this->payload,
                    'status'                => 'open',
                    'needs_attention'       => true,
                    'summary'               => null,
                    'summary_status'        => 'pending',
                    'suggested_next_action' => null,
                ])
                ->whereType('created_at', 'string')
                ->whereType('updated_at', 'string')));

    $issueId = $response->json('data.id');

    $this->assertDatabaseHas('issues', [
        'id' => $issueId,
        ...$this->payload,
        'status'                => 'open',
        'needs_attention'       => true,
        'summary'               => null,
        'summary_status'        => 'pending',
        'suggested_next_action' => null,
    ]);
    $this->assertDatabaseHas('issue_logs', [
        'issue_id' => $issueId,
        'user_id'  => $actor->getKey(),
        'action'   => 'created',
    ]);
    expect(IssueLog::where('issue_id', $issueId)->sole()->details)
        ->toBe(['message' => 'Issue created by Demo Agent']);
});

test('an explicit status is saved and lower priorities do not need attention', function (string $priority) {
    $response = $this->actingAs(User::factory()->create(), 'web')
        ->postJson('/api/issues', [
            ...$this->payload,
            'priority'        => $priority,
            'status'          => 'resolved',
            'needs_attention' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.needs_attention', false);

    $this->assertDatabaseHas('issues', [
        'id'              => $response->json('data.id'),
        'priority'        => $priority,
        'status'          => 'resolved',
        'needs_attention' => false,
    ]);
})->with(['low', 'medium']);

test('creating an issue requires all four input fields', function () {
    $this->actingAs(User::factory()->create(), 'web')
        ->postJson('/api/issues', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'description', 'category', 'priority']);

    $this->assertDatabaseEmpty('issues');
    $this->assertDatabaseEmpty('issue_logs');
});

test('issue creation rejects invalid field values', function (string $field, ?string $value) {
    $this->actingAs(User::factory()->create(), 'web')
        ->postJson('/api/issues', [...$this->payload, $field => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    $this->assertDatabaseEmpty('issues');
    $this->assertDatabaseEmpty('issue_logs');
})->with([
    'blank title'          => ['title', '   '],
    'blank description'    => ['description', '   '],
    'invalid category'     => ['category', 'unknown'],
    'invalid priority'     => ['priority', 'urgent'],
    'invalid status'       => ['status', 'closed'],
    'explicit null status' => ['status', null],
]);

test('guests cannot create issues', function () {
    $this->postJson('/api/issues', [])->assertUnauthorized();

    $this->assertDatabaseEmpty('issues');
    $this->assertDatabaseEmpty('issue_logs');
});
