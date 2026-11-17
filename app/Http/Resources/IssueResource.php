<?php

namespace App\Http\Resources;

use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'title'                 => $this->title,
            'description'           => $this->description,
            'category'              => $this->category,
            'priority'              => $this->priority,
            'status'                => $this->status,
            'summary'               => $this->summary,
            'suggested_next_action' => $this->suggested_next_action,
            'summary_status'        => $this->summary_status,
            'needs_attention'       => (bool) $this->needs_attention,
            'created_at'            => $this->created_at->toISOString(),
            'updated_at'            => $this->updated_at->toISOString(),
        ];
    }
}
