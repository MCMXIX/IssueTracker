<?php

namespace App\Http\Controllers\Api;

use App\Actions\Issues\CreateIssue;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateIssueRequest;
use App\Http\Resources\IssueResource;
use Illuminate\Http\JsonResponse;

class IssueController extends Controller
{
    public function store(CreateIssueRequest $request, CreateIssue $createIssue): JsonResponse
    {
        $issue = $createIssue->handle($request->validated(), $request->user());
        return IssueResource::make($issue)->response()->setStatusCode(201);
    }
}
