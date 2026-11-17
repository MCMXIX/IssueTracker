<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'issue_id',
    'user_id',
    'action',
    'details'
])]
class IssueLog extends Model
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * Set relationship
     * @return BelongsTo
     */
    public function actor() : BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Set relationship
     * @return BelongsTo
     */
    public function issue() : BelongsTo
    {
        return $this->belongsTo(Issue::class, 'issue_id');
    }

    /**
     * Cast fields
     * @return array
     */
    protected function casts(): array
    {
        return [
            'details' => 'array'
        ];
    }
}