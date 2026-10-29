<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One distilled founder thought: a question, an answer and why the answer is valid. Drafts until a person approves. */
class FounderThought extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'echo_submission_id', 'source_type', 'question', 'answer', 'why_valid',
        'status', 'model', 'prompt_version', 'prompt_tokens', 'completion_tokens',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function echo(): BelongsTo
    {
        return $this->belongsTo(EchoSubmission::class, 'echo_submission_id');
    }

    public function scopeDrafts(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT)->orderBy('id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED)->orderBy('id');
    }
}
