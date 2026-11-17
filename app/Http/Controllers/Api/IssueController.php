<?php

namespace App\Http\Controllers\Api;

use App\Actions\Issues\CreateIssue;
use App\Actions\Issues\UpdateIssue;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateIssueRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Resources\IssueResource;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;

class IssueController extends Controller
{
    /**
     * Create new Issue
     * @param CreateIssueRequest $request
     * @param CreateIssue $createIssue
     * @return JsonResponse
     */
    public function store(CreateIssueRequest $request, CreateIssue $createIssue): JsonResponse
    {
        $issue = $createIssue->handle($request->validated(), $request->user());
        return IssueResource::make($issue)->response()->setStatusCode(201);
    }

    /**
     * Update issue information and logs
     * @param UpdateIssueRequest $request
     * @param Issue $issue
     * @param UpdateIssue $updateIssue
     * @return JsonResponse
     */
    public function update(UpdateIssueRequest $request, Issue $issue, UpdateIssue $updateIssue) : JsonResponse
    {
        $issue = $updateIssue->handle($issue, $request->validated(), $request->user());
        return IssueResource::make($issue)->response()->setStatusCode(200);
    }

    /**
     * Retireve single issue
     * @param Issue $issue
     * @return JsonResponse
     */
    public function show(Issue $issue) : JsonResponse
    {
        $issue = $issue->load([
            'comments' => fn ($query) => $query
                ->oldest('created_at')
                ->orderBy('id'),
            'logs' => fn ($query) => $query
                ->with('actor:id,name')
                ->latest('created_at')
                ->orderByDesc('id')
                ->limit(10)
        ]);

        return IssueResource::make($issue)->response()->setStatusCode(200);
    }
}
