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

        return view('tbwim.owner-decide', [
            'echo' => $echo, 'decision' => $decision, 'state' => 'confirm', 'message' => null,
        ]);
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
