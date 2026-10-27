<?php

declare(strict_types=1);

namespace App\Support;

final class IntakeDecision
{
    public const ACCEPTED = 'accepted';

    public const QUARANTINED = 'quarantined';

    public const REJECTED = 'rejected';

    public function __construct(
        public readonly string $outcome,
        public readonly ?string $reason,
        public readonly string $fingerprint,
        public readonly ?string $clientIpHash,
    ) {}

    public function rejected(): bool
    {
        return $this->outcome === self::REJECTED;
    }

    public function quarantined(): bool
    {
        return $this->outcome === self::QUARANTINED;
    }
}
