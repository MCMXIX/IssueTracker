<?php

namespace App\Actions\Issues;

use App\Models\Comment;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddIssueComment
{
    /**
     * Create new comment for issue
     * @param Issue $issue
     * @param array $data
     * @param User $actor
     * @return Issue
     */
    public function handle(Issue $issue, array $data, User $actor)
    {
        return DB::transaction(function () use($issue, $data, $actor) : Comment {
            return $issue->comments()->create([
                'author_name' => $actor->name,
                'body'        => $data['body']
            ]);
        });
    }
}