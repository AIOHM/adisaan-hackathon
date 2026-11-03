<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EchoSubmission;
use App\Models\FounderThought;
use App\Models\SignalQuestion;
use App\Services\Tbwim\OwnerAlerts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

final class OwnerDraftController extends Controller
{
    public function inbox(OwnerAlerts $alerts)
    {
        return response()->view('tbwim.owner-pending', [
            'thoughts' => FounderThought::drafts()->get(),
            'signals' => SignalQuestion::drafts()->get(),
            'answers' => EchoSubmission::query()->humans()->whereNull('released_at')
                ->where('status', EchoSubmission::STATUS_RECEIVED)
                ->whereNull('metadata->decided_by')
                ->where(function ($q) {
                    $q->whereNull('metadata->founder_capture')->orWhere('metadata->founder_capture', false);
                })
                ->get(),
            'alerts' => $alerts,
        ])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function show(Request $request, string $kind, int $id, string $decision)
    {
        $model = $kind === 'thought' ? FounderThought::class : SignalQuestion::class;
        $message = null;
        $row = DB::transaction(function () use ($model, $id, $request, $kind, $decision, &$message) {
            $row = $model::query()->lockForUpdate()->findOrFail($id);
            if ($row->status !== 'draft') {
                $message = 'Already decided: '.$row->status.'. Nothing changed.';
            } elseif ($request->isMethod('post') && $decision === 'edit') {
                $rules = ['question' => ['required', 'string', 'max:280', 'regex:/^What is the best way (?:I|you) imagine.+\?$/u']];
                $rules += $kind === 'thought'
                    ? ['answer' => ['required', 'string', 'max:6000'], 'why_valid' => ['required', 'string', 'max:6000']]
                    : ['why' => ['required', 'string', 'max:6000'], 'brief_script' => ['required', 'string', 'max:12000']];
                $row->fill($request->validate($rules))->save();
                $message = 'Draft saved. It still needs your approval.';
            } elseif ($request->isMethod('post')) {
                $args = ['id' => $id];
                if ($decision === 'reject') {
                    $args['--reject'] = true;
                }
                $code = Artisan::call($kind === 'thought' ? 'tbwim:thought-approve' : 'tbwim:signal-approve', $args);
                abort_if($code !== 0, 409, 'The draft could not be decided.');
                $row->refresh();
                $message = $row->status === 'rejected' ? 'Rejected. It stays out of the public record.'
                    : ($kind === 'thought' ? 'Approved for the public dataset.' : 'Approved for '.$row->scheduled_for->toDateString().' (Europe/Bucharest).');
            }

            return $row;
        });

        return response()->view('tbwim.owner-draft', compact('row', 'kind', 'decision', 'message') + ['alerts' => app(OwnerAlerts::class)])
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
