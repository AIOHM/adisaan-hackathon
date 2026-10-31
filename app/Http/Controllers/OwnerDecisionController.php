<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EchoSubmission;
use App\Services\Tbwim\ReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The owner's decision behind a signed Telegram link. GET shows what
 * the tap would do (safe for link previews); only POST decides. The
 * signature is the authentication — there is no login here.
 */
final class OwnerDecisionController extends Controller
{
    public function show(Request $request, string $submission, string $decision): View
    {
        $echo = $this->answer($submission);
        if ($request->isMethod('post')) {
            return $this->decide($echo, $decision);
        }
        $meta = is_array($echo->metadata) ? $echo->metadata : [];
        if (($meta['decided_by'] ?? null) !== null) {
            return view('tbwim.owner-decide', [
                'echo' => $echo, 'decision' => $decision, 'state' => 'decided',
                'message' => 'This answer was already decided — nothing changed.',
            ]);
        }
        if ($decision === 'approve' && ($blocker = $this->approveBlocker($echo)) !== null) {
            return view('tbwim.owner-decide', [
                'echo' => $echo, 'decision' => $decision, 'state' => 'ineligible', 'message' => $blocker,
            ]);
        }

        return view('tbwim.owner-decide', [
            'echo' => $echo, 'decision' => $decision, 'state' => 'confirm', 'message' => null,
        ]);
    }

    /** Mirrors the release guard in ReviewService::decide; POST keeps the exception as backstop. */
    private function approveBlocker(EchoSubmission $echo): ?string
    {
        $memberConsent = data_get($echo->metadata, 'member_sharing.status') === 'approved'
            && data_get($echo->metadata, 'member_sharing.basis') === 'sender_selected_members_only';
        if ((! $echo->consent_quote && ! $memberConsent) || $echo->status === EchoSubmission::STATUS_QUARANTINED) {
            return 'This answer is not eligible for public release (no consent, or quarantined).';
        }

        return null;
    }

    private function decide(EchoSubmission $echo, string $decision): View
    {
        $meta = is_array($echo->metadata) ? $echo->metadata : [];
        if (($meta['decided_by'] ?? null) !== null) {
            return view('tbwim.owner-decide', [
                'echo' => $echo, 'decision' => $decision, 'state' => 'decided',
                'message' => 'This answer was already decided — nothing changed.',
            ]);
        }
        try {
            app(ReviewService::class)->decide($echo, $decision === 'approve');
        } catch (\LogicException $e) {
            return view('tbwim.owner-decide', [
                'echo' => $echo, 'decision' => $decision, 'state' => 'ineligible',
                'message' => $e->getMessage(),
            ]);
        }

        return view('tbwim.owner-decide', [
            'echo' => $echo->fresh() ?? $echo, 'decision' => $decision,
            'state' => $decision === 'approve' ? 'released' : 'held',
            'message' => null,
        ]);
    }

    private function answer(string $receipt): EchoSubmission
    {
        $echo = EchoSubmission::query()->where('public_id', $receipt)->first();
        abort_if(! $echo || $echo->isAgent(), 404);

        return $echo;
    }
}
