<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'description',
    'priority',
    'category',
    'status',
    'summary',
    'suggested_next_action',
    'summary_status',
    'needs_attention'
])]
class Issue extends Model
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * Set relationship
     * @return HasMany
     */
    public function logs() : HasMany
    {
        return $this->hasMany(IssueLog::class);
    }
}