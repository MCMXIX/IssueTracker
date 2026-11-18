<?php

use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
});

test('detail returns visible comments for the issue in chronological order', function () {
    $start = now()->startOfDay();
    $this->travelTo($start->copy()->addHour());
    $later = $this->issue->comments()->create([
        'author_name' => 'Support Agent',
        'body'        => 'The payment records are ready.',
    ]);

    $this->travelTo($start);
    $earlier = $this->issue->comments()->create([
        'author_name' => 'Support Agent',
        'body'        => 'I will review the payment records.',
    ]);
    $sameTime = $this->issue->comments()->create([
        'author_name' => 'Billing Agent',
        'body'        => 'I have shared the invoice.',
    ]);
    $this->travelBack();

    $deleted = $this->issue->comments()->create([
        'author_name' => 'Support Agent',
        'body'        => 'Removed comment.',
    ]);
    $deleted->delete();

    $otherIssue = $this->issue->replicate();
    $otherIssue->save();
    $otherIssue->comments()->create([
        'author_name' => 'Support Agent',
        'body'        => 'Comment for another issue.',
    ]);

    $this->actingAs(User::factory()->create(), 'web')
        ->getJson($this->issueUrl)
        ->assertOk()
        ->assertJsonPath('data.id', $this->issue->getKey())
        ->assertJsonPath('data.comments', [
            [
                'id'          => $earlier->getKey(),
                'author_name' => 'Support Agent',
                'body'        => 'I will review the payment records.',
                'created_at'  => $start->toISOString(),
            ],
            [
                'id'          => $sameTime->getKey(),
                'author_name' => 'Billing Agent',
                'body'        => 'I have shared the invoice.',
                'created_at'  => $start->toISOString(),
            ],
            [
                'id'          => $later->getKey(),
                'author_name' => 'Support Agent',
                'body'        => 'The payment records are ready.',
                'created_at'  => $start->copy()->addHour()->toISOString(),
            ],
        ]);

    $this->assertSoftDeleted($deleted);
});

test('detail limits audit history and eager-loads the actors in a bounded number of queries', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'web');
    $this->freezeSecond();
    $first = $this->issue->logs()->create([
        'user_id' => $user->getKey(),
        'action'  => 'created',
        'details' => ['message' => 'Issue created.'],
    ]);

    $getDetail = function (): array {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $response = $this->getJson($this->issueUrl)->assertOk();

            return [$response->json('data.logs'), count(DB::getQueryLog())];
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    };

    [$oneLog, $fewQueries] = $getDetail();
    expect(array_column($oneLog, 'id'))->toBe([$first->getKey()]);

    $logs = User::factory()->count(11)->create()->map(function (User $actor): array {
        $log = $this->issue->logs()->create([
            'user_id' => $actor->getKey(),
            'action'  => 'updated',
            'details' => ['changes' => ['status' => ['from' => 'open', 'to' => 'resolved']]],
        ]);

        return [
            'id'         => $log->getKey(),
            'action'     => 'updated',
            'details'    => ['changes' => ['status' => ['from' => 'open', 'to' => 'resolved']]],
            'created_at' => $log->created_at->toISOString(),
            'actor'      => ['id' => $actor->getKey(), 'name' => $actor->name],
        ];
    });
    $this->travelBack();

    $otherIssue = $this->issue->replicate();
    $otherIssue->save();
    $otherIssue->logs()->create(['user_id' => $user->getKey(), 'action' => 'created']);

    [$recentLogs, $manyQueries] = $getDetail();

    expect($recentLogs)->toBe($logs->reverse()->take(10)->values()->all())
        ->and($manyQueries)->toBe($fewQueries);
});

test('detail includes empty arrays when the issue has no comments or logs', function () {
    $this->actingAs(User::factory()->create(), 'web')
        ->getJson($this->issueUrl)
        ->assertOk()
        ->assertJsonPath('data.comments', [])
        ->assertJsonPath('data.logs', []);
});

test('guests cannot view issue detail', function () {
    $this->getJson($this->issueUrl)->assertUnauthorized();
});

test('a signed-in user gets not found for an unknown issue', function () {
    $this->actingAs(User::factory()->create(), 'web')
        ->getJson('/api/issues/'.($this->issue->getKey() + 1))
        ->assertNotFound();
});
