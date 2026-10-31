<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One answer to one question: from a person or from an AI agent.
 *
 * `body` is what was submitted and is never edited. `display_body` is the
 * same words with spelling and punctuation corrected, written only by the
 * reviewer on release. `metadata.respondent_type` is "human" or "agent", and
 * the two never mix in a count.
 */
class EchoSubmission extends Model
{
    use HasUlids;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_QUARANTINED = 'quarantined';

    public const STATUS_SYNTHESIZED = 'synthesized';

    protected $table = 'echo_submissions';

    protected $fillable = [
        'public_id', 'episode_ref', 'prompt_key', 'prompt_text', 'body', 'signature', 'language',
        'country_code', 'city', 'age_band', 'theme', 'synthesized_at', 'status', 'quarantine_reason',
        'contact_email', 'consent_quote', 'consent_followup', 'client_ip_hash', 'fingerprint',
        'source_url', 'referrer', 'user_agent', 'metadata', 'released_at', 'display_body',
        'transcript', 'reviewed_at', 'review_score', 'review_reason', 'review_meta',
    ];

    protected $casts = [
        'consent_quote' => 'boolean',
        'consent_followup' => 'boolean',
        'synthesized_at' => 'datetime',
        'released_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
        'review_meta' => 'array',
    ];

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function scopeCountable(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_RECEIVED, self::STATUS_SYNTHESIZED]);
    }

    public function scopeHumans(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('metadata->respondent_type')->orWhere('metadata->respondent_type', 'human');
        });
    }

    public function scopeAgents(Builder $query): Builder
    {
        return $query->where('metadata->respondent_type', 'agent');
    }

    /** The one definition of "may be shown": consented, not quarantined, released by the review. */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('consent_quote', true)
            ->whereIn('status', [self::STATUS_RECEIVED, self::STATUS_SYNTHESIZED])
            ->whereNotNull('released_at');
    }

    /** Members-only: sender chose members, a person released it. Strictly private rows never qualify. */
    public function scopeMembersVisible(Builder $query): Builder
    {
        return $query->where('consent_quote', false)
            ->whereIn('status', [self::STATUS_RECEIVED, self::STATUS_SYNTHESIZED])
            ->whereNotNull('released_at')
            ->where('metadata->member_sharing->status', 'approved');
    }

    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_QUARANTINED)->whereNull('reviewed_at');
    }

    public function thoughts(): HasMany
    {
        return $this->hasMany(FounderThought::class, 'echo_submission_id');
    }

    public function isAgent(): bool
    {
        return ($this->metadata['respondent_type'] ?? 'human') === 'agent';
    }

    public function isPublished(): bool
    {
        return $this->consent_quote && $this->status !== self::STATUS_QUARANTINED && $this->released_at !== null;
    }

    /** pending_review, published, held or declined: the receipt vocabulary. */
    public function reviewWord(): string
    {
        if ($this->status === self::STATUS_QUARANTINED) {
            return 'declined';
        }
        if ($this->isPublished()) {
            return 'published';
        }

        return $this->reviewed_at !== null ? 'held' : 'pending_review';
    }

    public function displayBody(): string
    {
        $shown = trim((string) $this->display_body);

        return $shown !== '' ? $shown : (string) $this->body;
    }
}
