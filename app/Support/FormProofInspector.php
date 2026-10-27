<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * An encrypted proof-of-form token binds a submission to the form that
 * rendered it and the time it was served: "too fast" and "too old" become
 * decidable without a session or a cookie.
 */
final class FormProofInspector
{
    public function issueToken(string $form): string
    {
        return Crypt::encryptString(json_encode([
            'form' => $form,
            'issued_at' => now()->timestamp,
            'nonce' => Str::random(24),
        ], JSON_THROW_ON_ERROR));
    }

    /** Null when valid, otherwise the rejection reason. */
    public function invalidReason(Request $request, string $form): ?string
    {
        $config = (array) config('tbwim.intake.form_proof', []);
        $token = trim((string) $request->input((string) ($config['token_field'] ?? '_answer_form_token'), ''));
        if ($token === '') {
            return 'missing_form_proof';
        }
        if (strlen($token) > 4096) {
            return 'invalid_form_proof';
        }
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return 'invalid_form_proof';
        }
        if (! is_array($payload) || ! hash_equals($form, (string) ($payload['form'] ?? ''))) {
            return 'invalid_form_proof';
        }
        $issuedAt = filter_var($payload['issued_at'] ?? null, FILTER_VALIDATE_INT);
        if ($issuedAt === false) {
            return 'invalid_form_proof';
        }
        $age = now()->timestamp - (int) $issuedAt;
        $minimum = max(0, (int) ($config['minimum_seconds'] ?? 3));
        $maximum = max($minimum + 1, (int) ($config['maximum_seconds'] ?? 7200));
        if ($age < $minimum) {
            return 'form_completed_too_quickly';
        }
        if ($age > $maximum) {
            return 'expired_form_proof';
        }

        return null;
    }
}
