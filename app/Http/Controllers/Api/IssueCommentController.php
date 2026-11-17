<?php

namespace App\Http\Controllers\Api;

use App\Actions\Issues\AddIssueComment;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IssueCommentController extends Controller
{
    /**
     * Create new comment for issue
     * @param Issue $issue
     * @param AddIssueComment $addIssueComment
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Issue $issue, AddIssueComment $addIssueComment, Request $request) : JsonResponse
    {
        $request->validate(['body' => 'required|string|max:600']);
        $comment = $addIssueComment->handle($issue, $request->only('body'), $request->user());
        return CommentResource::make($comment)->response()->setStatusCode(201);
    }
}