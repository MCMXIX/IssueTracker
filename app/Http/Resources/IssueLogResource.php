<?php

namespace App\Http\Resources;

use App\Models\IssueLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssueLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'action'     => $this->action,
            'details'    => $this->details,
            'created_at' => $this->created_at->toISOString(),
            'actor'      => $this->whenLoaded('actor', fn (User $actor): array => [
                'id'   => $actor->id,
                'name' => $actor->name,
            ]),
        ];
    }
}
