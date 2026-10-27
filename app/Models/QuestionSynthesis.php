<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** One version of the breathing answer: what the voices on a question add up to, at one moment. Append-only. */
class QuestionSynthesis extends Model
{
    protected $table = 'question_syntheses';

    protected $fillable = [
        'question_ref', 'version', 'body', 'voices_site', 'voices_agents', 'themes', 'model',
        'prompt_version', 'prompt_tokens', 'completion_tokens', 'generated_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'voices_site' => 'integer',
        'voices_agents' => 'integer',
        'themes' => 'array',
        'generated_at' => 'datetime',
    ];

    public function scopeForQuestion(Builder $query, string $questionRef): Builder
    {
        return $query->where('question_ref', $questionRef);
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('version');
    }
}
