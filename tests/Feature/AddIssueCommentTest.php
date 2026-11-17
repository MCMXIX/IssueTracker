<?php

use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->issue = Issue::query()->create([
        'title'       => 'Duplicate charge',
        'description' => 'Two charges appeared on the statement.',
        'category'    => 'billing',
        'priority'    => 'medium',
    ]);
    $this->issueUrl = '/api/issues/'.$this->issue->getKey();
    $this->commentsUrl = $this->issueUrl.'/comments';
});

test('a signed-in user can add a comment that appears in issue detail', function () {
    $actor = User::factory()->create(['name' => 'Support Agent']);
    $otherIssue = $this->issue->replicate();
    $otherIssue->save();

    $response = $this->actingAs($actor, 'web')
        ->postJson($this->commentsUrl, [
            'body'        => '  I am checking both payment records.  ',
            'author_name' => 'Client-supplied author',
            'issue_id'    => $otherIssue->getKey(),
        ])
        ->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('data', fn (AssertableJson $comment) => $comment
                ->whereType('id', 'integer')
                ->where('author_name', 'Support Agent')
                ->where('body', 'I am checking both payment records.')
                ->whereType('created_at', 'string')));

    $this->assertDatabaseCount('comments', 1);
    $this->assertDatabaseHas('comments', [
        'id'          => $response->json('data.id'),
        'issue_id'    => $this->issue->getKey(),
        'author_name' => 'Support Agent',
        'body'        => 'I am checking both payment records.',
    ]);

    $this->getJson($this->issueUrl)
        ->assertOk()
        ->assertJsonPath('data.comments', [$response->json('data')]);
});

test('a comment can contain up to 600 characters', function () {
    $body = str_repeat('a', 600);

    $this->actingAs(User::factory()->create(), 'web')
        ->postJson($this->commentsUrl, ['body' => $body])
        ->assertCreated()
        ->assertJsonPath('data.body', $body);
});

test('invalid comment bodies are rejected without saving a comment', function (array $payload) {
    $this->actingAs(User::factory()->create(), 'web')
        ->postJson($this->commentsUrl, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');

    $this->assertDatabaseEmpty('comments');
})->with([
    'missing body'        => [[]],
    'whitespace only'     => [['body' => '   ']],
    'non-string body'     => [['body' => 42]],
    'over 600 characters' => [['body' => str_repeat('a', 601)]],
]);

test('guests cannot add comments', function () {
    $this->postJson($this->commentsUrl, ['body' => 'Guest comment.'])
        ->assertUnauthorized();

    $this->assertDatabaseEmpty('comments');
});

test('adding a comment to a missing issue returns not found', function () {
    $this->actingAs(User::factory()->create(), 'web')
        ->postJson('/api/issues/'.($this->issue->getKey() + 1).'/comments', ['body' => 'Valid comment.'])
        ->assertNotFound();

    $this->assertDatabaseEmpty('comments');
});
