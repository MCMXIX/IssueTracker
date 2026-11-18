<?php

namespace App\Jobs;

use App\Enums\IssueSummaryStatus;
use App\Interfaces\SummaryGenerator;
use App\Models\Issue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class GenerateIssueSummary implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 600;

    public array $backoff = [5, 15];

    public function __construct(public int $issueId, public int $revision) {}

    public function uniqueId(): string
    {
        return $this->issueId . ':' . $this->revision;
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->dontRelease()
                ->expireAfter(60),
        ];
    }

    public function handle(SummaryGenerator $generator): void
    {
        $issue = $this->pendingIssue()->first();
        if ($issue) {
            $result = $generator->generate($issue->title, $issue->description, $issue->category, $issue->priority);

            $this->pendingIssue()->update([
                'summary'               => $result->summary,
                'suggested_next_action' => $result->suggestedNextAction,
                'summary_status'        => IssueSummaryStatus::Success->value,
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->pendingIssue()->update(['summary_status' => IssueSummaryStatus::Failed->value]);
    }

    /** @return Builder<Issue> */
    private function pendingIssue(): Builder
    {
        return Issue::query()
            ->whereKey($this->issueId)
            ->where('summary_revision', $this->revision)
            ->where('summary_status', IssueSummaryStatus::Pending->value);
    }
}
