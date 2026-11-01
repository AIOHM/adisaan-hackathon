<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One question drafted from a news signal, with the avatar brief that
 * announces it. A draft until a person approves; on approval it receives
 * its episode reference and tomorrow's date and enters the rotation.
 */
class SignalQuestion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'signal_title', 'signal_url', 'signal_source', 'signal_category',
        'question', 'why', 'brief_script', 'brief_audio_path',
        'status', 'episode_ref', 'scheduled_for',
        'model', 'prompt_version', 'prompt_tokens', 'completion_tokens',
        'reviewed_at',
    ];

    protected $casts = [
        'scheduled_for' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function scopeDrafts(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT)->orderBy('id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED)->orderBy('scheduled_for');
    }
}
