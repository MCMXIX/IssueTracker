<?php

namespace App\Http\Controllers\Api;

use App\Actions\Issues\CreateIssue;
use App\Actions\Issues\UpdateIssue;
use App\Enums\IssueCategory;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateIssueRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Resources\IssueResource;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    /**
     * Retrieve list of issues
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status'   => ['sometimes', 'required', Rule::enum(IssueStatus::class)],
            'priority' => ['sometimes', 'required', Rule::enum(IssuePriority::class)],
            'category' => ['sometimes', 'required', Rule::enum(IssueCategory::class)],
        ]);

        $query = Issue::query();
        foreach ($filters as $field => $value) {
            $query->where($field, $value);
        }

        $issues = $query->latest('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->appends($filters);

        return IssueResource::collection($issues)->response();
    }
}
