<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One anonymous resonance vote on a public answer. No accounts. */
final class AnswerVote extends Model
{
    public $timestamps = false;

    protected $fillable = ['echo_submission_id', 'fingerprint', 'client_ip_hash', 'created_at'];
}
