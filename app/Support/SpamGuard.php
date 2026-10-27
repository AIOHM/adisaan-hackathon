<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Abuse inspection for the public answer form: proof-of-form, a honeypot,
 * and two content shapes that separate a sentence about a life from an
 * advertisement. No raw IP is ever stored; only a keyed hash.
 */
final class SpamGuard
{
    public function __construct(
        private readonly ClientIpResolver $clientIpResolver,
        private readonly FormProofInspector $formProofInspector,
    ) {}

    public function issueToken(string $form): string
    {
        return $this->formProofInspector->issueToken($form);
    }

    public function inspect(Request $request, string $form, string $body): IntakeDecision
    {
        $clientIp = $this->clientIpResolver->resolve($request);
        $clientIpHash = $clientIp === null ? null : hash_hmac('sha256', $clientIp, (string) config('app.key'));
        $fingerprint = hash('sha256', implode('|', [
            strtolower($request->getHost()), $form, $clientIp ?? 'unknown', strtolower(trim((string) $request->userAgent())),
        ]));

        $honeypot = (string) config('tbwim.intake.form_proof.honeypot_field', '_website_url');
        if (filled($request->input($honeypot))) {
            return new IntakeDecision(IntakeDecision::REJECTED, 'honeypot_filled', $fingerprint, $clientIpHash);
        }
        $proofReason = $this->formProofInspector->invalidReason($request, $form);
        if ($proofReason !== null) {
            return new IntakeDecision(IntakeDecision::REJECTED, $proofReason, $fingerprint, $clientIpHash);
        }
        $maximumLinks = max(0, (int) config('tbwim.intake.maximum_links', 1));
        if ((preg_match_all('~https?://|www\.~i', $body) ?: 0) > $maximumLinks) {
            return new IntakeDecision(IntakeDecision::QUARANTINED, 'excess_links', $fingerprint, $clientIpHash);
        }
        if ($this->looksMachineGenerated($body)) {
            return new IntakeDecision(IntakeDecision::QUARANTINED, 'automated_content_pattern', $fingerprint, $clientIpHash);
        }

        return new IntakeDecision(IntakeDecision::ACCEPTED, null, $fingerprint, $clientIpHash);
    }

    private function looksMachineGenerated(string $body): bool
    {
        $normalized = strtolower(trim($body));
        if ($normalized === '') {
            return true;
        }
        if (strlen($normalized) >= 20 && preg_match('/^[a-z]+$/', $normalized) === 1) {
            return true;
        }
        $words = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($words) >= 6 && count(array_unique($words)) <= 2;
    }
}
