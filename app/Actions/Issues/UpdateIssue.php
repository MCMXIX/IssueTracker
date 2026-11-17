<?php

namespace App\Actions\Issues;

use App\Enums\IssueLogAction;
use App\Enums\IssuePriority;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateIssue
{
    /**
     * Update issue details and log
     * @param Issue $issue
     * @param array $data
     * @param User $actor
     * @return Issue
     */
    public function handle(Issue $issue, array $data, User $actor): Issue
    {
        return DB::transaction(function () use ($issue, $data, $actor): Issue {
            $lockedIssue = Issue::query()
                ->whereKey($issue->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedIssue->fill(Arr::only($data, ['title', 'description', 'category', 'priority', 'status']));

            if ($lockedIssue->isDirty('priority')) {
                $lockedIssue->needs_attention = $lockedIssue->priority === IssuePriority::High->value;
            }

            $dirty = $lockedIssue->getDirty();
            if (!empty($dirty)) {
                $changes = [];
                foreach (array_keys($dirty) as $field) {
                    $changes[$field] = [
                        'from' => $lockedIssue->getOriginal($field),
                        'to'   => $lockedIssue->getAttribute($field),
                    ];
                }

                $lockedIssue->save();
                $lockedIssue->logs()->create([
                    'user_id' => $actor->getKey(),
                    'action'  => IssueLogAction::Updated->value,
                    'details' => ['changes' => $changes],
                ]);
            }
                
            return $lockedIssue;
        });
    }
}
