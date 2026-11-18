<?php

use App\Interfaces\SummaryGenerator;
use App\Jobs\GenerateIssueSummary;
use App\Models\Issue;
use App\Models\User;
use App\Services\IssueSummary\GeneratedSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->ticket = [
        'title'       => 'Duplicate charge',
        'description' => 'The customer was charged twice. Both charges have posted.',
        'category'    => 'billing',
        'priority'    => 'high',
    ];
    config([
        'services.openai.key'   => null,
        'services.openai.model' => 'test-model',
    ]);
    Http::preventStrayRequests();
});

test('the generator sends the prompt and returns validated OpenAI output', function () {
    config(['services.openai.key' => 'test-key']);
    $url = config('services.openai.url');
    Http::fake([$url => Http::response([
        'status' => 'completed',
        'output' => [
            ['type' => 'reasoning'],
            ['type' => 'message', 'content' => [[
                'type' => 'output_text',
                'text' => '{"summary":"Two charges posted.","suggested_next_action":"Check both charge records."}',
            ]]],
        ],
    ])]);

    $result = app(SummaryGenerator::class)->generate(...$this->ticket);

    expect($result->summary)->toBe('Two charges posted.')
        ->and($result->suggestedNextAction)->toBe('Check both charge records.');

    Http::assertSent(fn (Request $request): bool => $request->url() === $url
        && $request['model'] === 'test-model'
        && str_contains($request['instructions'], '500 characters')
        && json_decode($request['input'], true, flags: JSON_THROW_ON_ERROR) === $this->ticket
        && $request['text']['format']['strict'] === true
        && $request['text']['format']['schema']['required'] === ['summary', 'suggested_next_action']);
});

test('without a key the rules generator runs without calling OpenAI', function () {
    Http::fake([]);

    $result = app(SummaryGenerator::class)->generate(...$this->ticket);

    expect($result->summary)->toBe('The customer was charged twice.')
        ->and($result->suggestedNextAction)->toBe('Compare both charge records against the invoice.');
    Http::assertNothingSent();
});

test('OpenAI provider problems fall back to the rules generator', function (string $failure) {
    config(['services.openai.key' => 'test-key']);
    $response = match ($failure) {
        'connection' => Http::failedConnection(),
        'http'       => Http::response([], 503),
        'incomplete' => Http::response(['status' => 'incomplete']),
        'refusal'    => Http::response(['status' => 'completed', 'output' => [['content' => [
            ['type' => 'refusal', 'refusal' => 'Test refusal'],
        ]]]]),
        'bad json' => Http::response(['status' => 'completed', 'output' => [['content' => [
            ['type' => 'output_text', 'text' => 'not JSON'],
        ]]]]),
        'long summary' => Http::response(['status' => 'completed', 'output' => [['content' => [
            ['type' => 'output_text', 'text' => json_encode([
                'summary'               => str_repeat('a', 501),
                'suggested_next_action' => 'Review the invoice.',
            ], JSON_THROW_ON_ERROR)],
        ]]]]),
    };
    Http::fake([config('services.openai.url') => $response]);

    $result = app(SummaryGenerator::class)->generate(...$this->ticket);

    expect($result->summary)->toBe('The customer was charged twice.')
        ->and($result->suggestedNextAction)->toBe('Compare both charge records against the invoice.');
})->with(['connection', 'http', 'incomplete', 'refusal', 'bad json', 'long summary']);

test('creating an issue queues generation after commit and returns pending', function () {
    Queue::fake([GenerateIssueSummary::class]);

    $created = $this->actingAs(User::factory()->create(), 'web')
        ->postJson('/api/issues', $this->ticket)
        ->assertCreated()
        ->assertJsonPath('data.summary_status', 'pending');

    Queue::assertPushed(GenerateIssueSummary::class, fn (GenerateIssueSummary $job): bool => $job->issueId === $created->json('data.id') && $job->revision === 1 && $job->afterCommit === true);
});

test('a summary input edit clears the result and queues the next revision', function (string $field, string $value) {
    Queue::fake([GenerateIssueSummary::class]);
    $issue = Issue::query()->create([
        ...$this->ticket,
        'summary'               => 'Previous summary.',
        'suggested_next_action' => 'Previous action.',
        'summary_status'        => 'success',
    ]);

    $this->actingAs(User::factory()->create(), 'web')
        ->patchJson('/api/issues/'.$issue->getKey(), [$field => $value])
        ->assertOk()
        ->assertJsonPath('data.summary_status', 'pending')
        ->assertJsonPath('data.summary', null)
        ->assertJsonPath('data.suggested_next_action', null);

    $this->assertDatabaseHas('issues', [
        'id'                    => $issue->getKey(),
        'summary_revision'      => 2,
        'summary_status'        => 'pending',
        'summary'               => null,
        'suggested_next_action' => null,
    ]);
    Queue::assertPushed(GenerateIssueSummary::class, fn (GenerateIssueSummary $job): bool => $job->issueId === $issue->getKey() && $job->revision === 2 && $job->afterCommit === true);
})->with([
    'title'       => ['title', 'Duplicate charge on invoice'],
    'description' => ['description', 'The charge has been reversed.'],
    'category'    => ['category', 'operations'],
    'priority'    => ['priority', 'low'],
]);

test('status edits and comments preserve a completed summary', function () {
    Queue::fake([GenerateIssueSummary::class]);
    $issue = Issue::query()->create([
        ...$this->ticket,
        'summary'        => 'Existing summary.',
        'summary_status' => 'success',
    ]);
    $this->actingAs(User::factory()->create(), 'web');

    $this->patchJson('/api/issues/'.$issue->getKey(), ['status' => 'resolved'])
        ->assertOk()
        ->assertJsonPath('data.summary', 'Existing summary.');
    $this->postJson('/api/issues/'.$issue->getKey().'/comments', ['body' => 'Checking the charge.'])
        ->assertCreated();

    Queue::assertNotPushed(GenerateIssueSummary::class);
    $this->assertDatabaseHas('issues', [
        'id'               => $issue->getKey(),
        'summary_revision' => 1,
        'summary_status'   => 'success',
        'summary'          => 'Existing summary.',
    ]);
});

test('the job persists a generated summary and next action', function () {
    $issue = Issue::query()->create($this->ticket);

    (new GenerateIssueSummary($issue->getKey(), 1))->handle(app(SummaryGenerator::class));

    $this->assertDatabaseHas('issues', [
        'id'                    => $issue->getKey(),
        'summary'               => 'The customer was charged twice.',
        'suggested_next_action' => 'Compare both charge records against the invoice.',
        'summary_status'        => 'success',
    ]);
});

test('the job marks the current revision failed after a terminal error', function () {
    $issue = Issue::query()->create($this->ticket);

    (new GenerateIssueSummary($issue->getKey(), 1))->failed(new RuntimeException('Test failure'));

    $this->assertDatabaseHas('issues', [
        'id'             => $issue->getKey(),
        'summary_status' => 'failed',
        'summary'        => null,
    ]);
});

test('a delayed job cannot save or fail a newer summary revision', function () {
    $issue = Issue::query()->create($this->ticket);
    $oldJob = new GenerateIssueSummary($issue->getKey(), 1);
    $generator = Mockery::mock(SummaryGenerator::class);
    $generator->shouldReceive('generate')->once()->andReturnUsing(function () use ($issue): GeneratedSummary {
        Issue::query()->whereKey($issue->getKey())->update([
            'description'      => 'A newer report.',
            'summary_revision' => 2,
        ]);

        return new GeneratedSummary('Stale summary.', 'Stale action.');
    });

    $oldJob->handle($generator);
    $oldJob->failed(new RuntimeException('Stale failure'));

    $this->assertDatabaseHas('issues', [
        'id'                    => $issue->getKey(),
        'summary_revision'      => 2,
        'summary_status'        => 'pending',
        'summary'               => null,
        'suggested_next_action' => null,
    ]);
});

test('dispatch deduplicates the same issue revision and accepts newer revisions', function () {
    Queue::fake([GenerateIssueSummary::class]);
    $issue = Issue::query()->create($this->ticket);

    GenerateIssueSummary::dispatch($issue->getKey(), 1);
    GenerateIssueSummary::dispatch($issue->getKey(), 1);
    GenerateIssueSummary::dispatch($issue->getKey(), 2);

    Queue::assertPushedTimes(GenerateIssueSummary::class, 2);
    Queue::assertPushed(GenerateIssueSummary::class, fn (GenerateIssueSummary $job): bool => $job->revision === 1);
    Queue::assertPushed(GenerateIssueSummary::class, fn (GenerateIssueSummary $job): bool => $job->revision === 2);
});

test('the worker skips overlapping copies of the same revision', function () {
    $issue = Issue::query()->create($this->ticket);
    $original = new GenerateIssueSummary($issue->getKey(), 1);
    $duplicate = new GenerateIssueSummary($issue->getKey(), 1);
    $newer = new GenerateIssueSummary($issue->getKey(), 2);
    $handled = [];

    $record = function (GenerateIssueSummary $job) use (&$handled): void {
        $handled[] = $job->revision;
    };

    $original->middleware()[0]->handle($original, function (GenerateIssueSummary $job) use ($record, $duplicate, $newer): void {
        $record($job);
        $duplicate->middleware()[0]->handle($duplicate, $record);
        $newer->middleware()[0]->handle($newer, $record);
    });

    expect($handled)->toBe([1, 2]);
});
