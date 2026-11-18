<?php

namespace App\Http\Requests;

use App\Enums\IssueCategory;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title'       => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'required', 'string', 'max:10000'],
            'category'    => ['sometimes', 'required', Rule::enum(IssueCategory::class)],
            'priority'    => ['sometimes', 'required', Rule::enum(IssuePriority::class)],
            'status'      => ['sometimes', 'required', Rule::enum(IssueStatus::class)]
        ];
    }
}
