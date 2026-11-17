<?php

namespace App\Actions\Issues;

use App\Enums\IssueLogAction;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateIssue
{
    /**
     * Create new issue 
     * @param array $data 
     * @return Issue
     */
    public function handle(array $data, User $actor): Issue
    {
        return DB::transaction(function () use ($data, $actor): Issue {
            $issue = Issue::create([
                'title'           => $data['title'],
                'description'     => $data['description'],
                'category'        => $data['category'],
                'priority'        => $data['priority'],
                'status'          => $data['status'] ?? IssueStatus::Open->value,
                'needs_attention' => $data['priority'] === IssuePriority::High->value,
            ]);

            $issue->logs()->create([
                'user_id' => $actor->getKey(),
                'action'  => IssueLogAction::Created,
                'details' => ['message' => 'Issue created by ' . $actor->name],
            ]);

            return $issue->refresh();
        });
    }
}
