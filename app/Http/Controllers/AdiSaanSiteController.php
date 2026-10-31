<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\NotifyOwnerOfLead;
use App\Jobs\ReviewHumanAnswer;
use App\Jobs\TranscribeVoiceAnswer;
use App\Models\AnswerVote;
use App\Models\EchoSubmission;
use App\Models\QuestionSynthesis;
use App\Services\AdiSaan\GratitudeLedger;
use App\Services\AdiSaan\LaunchSchedule;
use App\Services\AdiSaan\VisitorIdentity;
use App\Services\Tbwim\NewsReader;
use App\Services\Tbwim\Questions;
use App\Support\ClientIpResolver;
use App\Support\IntakeDecision;
use App\Support\SpamGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The adisaan.com public site, rendered from the movement's own tables.
 *
 * The templates, stylesheet, scripts and copy under `adisaan-site` are the
 * AIOHM platform's, unchanged. This controller supplies the variables they
 * expect from this app's data. Where the platform draws on private services
 * (the social scoreboard, the members area, Muse, the GA4 living map,
 * Whisper transcription) this gives the templates the empty state they
 * already render, or sends the visitor to the brand site, which is where
 * those pages live.
 */
final class AdiSaanSiteController extends Controller
{
    private const FORM = 'tbwim_answer';

    private const TZ = 'Europe/Bucharest';

    /** The scoreboard without a social feed: the templates read this as "no measured count yet". */
    private const EMPTY_BOARD = ['total_followers' => null, 'last_synced_at' => null, 'accounts' => [], 'measured_count' => 0];

    private const BAROMETRU_CHANNELS = [
        'youtube:adisaan' => 'YouTube · @adisaan',
        'instagram:adrian.aparaschivei' => 'Instagram · AdiSaan',
        'tiktok:adrian.aparaschivei' => 'TikTok · AdiSaan',
        'facebook:Adisaan.partenerul.tau.online' => 'Facebook · AdiSaan Partenerul Tău',
        'facebook:marketingwithAdiSaan' => 'Facebook · Marketing with AdiSaan',
        'linkedin:Adrian' => 'LinkedIn · AdiSaan',
        'bluesky:aiohmplatform.bsky.social' => 'Bluesky · @aiohmplatform',
        'youtube:aiohm-platform' => 'YouTube · @aiohm-platform',
        'instagram:ohm.events.agency' => 'Instagram · @ohm.events.agency',
        'facebook:OHM.events.agency' => 'Facebook · OHM Events Agency',
        'reddit:adisaan' => 'Reddit · u/adisaan',
        'direct' => 'Direct / Sanctuary Search',
    ];

    private const SOCIAL_PROFILES = [
        'bluesky:aiohmplatform.bsky.social' => 'https://bsky.app/profile/aiohmplatform.bsky.social',
        'facebook:Adisaan.partenerul.tau.online' => 'https://www.facebook.com/Adisaan.partenerul.tau.online/',
        'facebook:marketingwithAdiSaan' => 'https://www.facebook.com/marketingwithAdiSaan/',
        'facebook:OHM.events.agency' => 'https://www.facebook.com/OHM.events.agency/',
        'instagram:adrian.aparaschivei' => 'https://www.instagram.com/adrian.aparaschivei/',
        'instagram:ohm.events.agency' => 'https://www.instagram.com/ohm.events.agency/',
        'tiktok:adrian.aparaschivei' => 'https://www.tiktok.com/@adrian.aparaschivei',
        'youtube:adisaan' => 'https://www.youtube.com/@adisaan',
        'youtube:aiohm-platform' => 'https://www.youtube.com/@aiohm-platform',
        'linkedin:Adrian' => 'https://www.linkedin.com/in/adisaan/',
        'reddit:adisaan' => 'https://www.reddit.com/user/adisaan',
    ];

    public function __construct(
        private readonly LaunchSchedule $schedule,
        private readonly GratitudeLedger $ledger,
        private readonly VisitorIdentity $visitors,
        private readonly Questions $questions,
    ) {}

    // ------------------------------------------------------------------ pages

    /** The classic homepage content, served at /about since 2026-10-31. */
    public function home(Request $request, SpamGuard $guard): View
    {
        $shared = $this->shared('about');
        $home = config('adisaan_public.home', []);
        unset($home['ro']);
        $home['faq'] = $this->localizeFaq($home['faq'] ?? null);
        $episode = $this->currentEpisode($shared['stories'], $home['episode_ref'] ?? null);
        $arena = $this->loadArena($shared['stories']);
        $questionCounts = collect($arena['questions'] ?? [])->keyBy('ref');
        $perspectives = $this->loadPublicPerspectives($episode['id'] ?? null);

        return view('adisaan-site.home', $shared + [
            'livingMap' => $this->loadLivingMap(),
            'canonicalPath' => '/about',
            'lang' => 'en',
            'home' => $home,
            'homeEpisode' => $episode,
            'loop' => $this->loopState($episode, $shared['board'], $home['date_format'] ?? 'j M Y'),
            'homeAnswers' => $perspectives->take(5)->values(),
            'lifeAreas' => (array) config('adisaan_public.life_areas', []),
            'questionCounts' => $questionCounts->all(),
            'answersTotal' => (int) ($arena['total'] ?? 0),
            'answerGoal' => (int) ($arena['goal'] ?? config('adisaan_public.answer_goal', 1000000)),
            'form' => $this->formData($guard, $episode),
        ]);
    }

    public function question(Request $request, SpamGuard $guard): View
    {
        $visitorId = $this->visitors->existing($request);
        $answered = $visitorId && $this->ledger->enabled() ? $this->ledger->answeredQuestions($visitorId) : [];

        $shared = $this->shared('question');
        $arena = $this->loadArena($shared['stories']);

        return view('adisaan-site.question', $shared + [
            'livingMap' => $this->loadLivingMap(),
            'lifeAreas' => (array) config('adisaan_public.life_areas', []),
            'answeredRefs' => $answered,
            'questionCounts' => collect($arena['questions'] ?? [])->keyBy('ref')->all(),
            'answersTotal' => (int) ($arena['total'] ?? 0),
            'answerGoal' => (int) config('adisaan_public.answer_goal', 1000000),
            'canonicalPath' => '/question',
            'form' => $this->formData($guard, null),
        ]);
    }

    public function answer(Request $request, SpamGuard $guard): View
    {
        $reference = $request->query('story') ?? $request->session()->getOldInput('episode_ref');
        $shared = $this->shared('answer');
        $stories = $shared['stories'];
        $selectedStory = is_string($reference) ? collect($stories)
            ->first(static fn (array $story): bool => ($story['id'] ?? null) === $reference && ($story['question_published'] ?? false) === true) : null;
        if (! $selectedStory) {
            $selectedStory = collect($stories)->firstWhere('id', $this->questions->current()['id'] ?? null) ?? ($stories[0] ?? null);
        }

        return view('adisaan-site.answer', $shared + [
            'livingMap' => $this->loadLivingMap(),
            'selectedStory' => $selectedStory,
            'question' => $selectedStory['question'] ?? config('adisaan_public.story.question'),
            'form' => $this->formData($guard, $selectedStory),
        ]);
    }

    public function storeAnswer(Request $request, SpamGuard $guard, ClientIpResolver $ips): RedirectResponse|JsonResponse
    {
        // Resonance votes are open to anonymous visitors: one per answer
        // per fingerprint, counted locally, no accounts anywhere.
        if ($request->filled('vote_echo_id')) {
            return $this->storeVote($request, $ips);
        }

        if ($request->input('respondent_type') === 'agent') {
            return response()->json(['ok' => false, 'error' => 'Agents answer through the machine endpoint.', 'endpoint' => route('adisaan.public.agent.answer.info')], 422);
        }

        $min = (int) config('echo_intake.body.minimum_length', 10);
        $max = (int) config('echo_intake.body.maximum_length', 2000);
        $hasMedia = $request->hasFile('answer_media');
        $validated = $request->validate([
            'body' => ['required_without:answer_media', 'nullable', 'string', 'min:'.($hasMedia ? 1 : $min), "max:{$max}"],
            'answer_media' => ['nullable', 'file', 'mimes:mp3,m4a,wav,ogg,oga,webm,mp4,mov', 'max:40960'],
            'signature' => ['nullable', 'string', 'max:60'],
            'contact_email' => ['nullable', 'required_if:consent_followup,1', 'email', 'max:255'],
            'consent_followup' => ['nullable'],
            'share_mode' => ['nullable', 'in:public,private,members'],
            'episode_ref' => ['nullable', 'string', 'max:64'],
            'prompt_key' => ['nullable', 'string', 'max:255'],
            'intent_type' => ['nullable', 'string', 'max:32'],
            'country_code' => ['nullable', 'string', 'max:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'age_band' => ['nullable', 'string', 'max:16'],
        ]);

        if (($validated['share_mode'] ?? '') === 'members' && strlen((string) config('tbwim.member_bridge_secret', '')) < 32) {
            abort(503, 'Members-only sharing is not available yet.');
        }

        $media = $request->file('answer_media');
        $body = trim((string) ($validated['body'] ?? ''));
        if ($media && $body === '') {
            $body = str_starts_with((string) $media->getMimeType(), 'audio/')
                ? '[Audio perspective awaiting review]'
                : '[Video perspective awaiting review]';
        }
        // A recording always waits for a person; a text answer is public only
        // when the visitor said so.
        $isPublic = ! $media && ($validated['share_mode'] ?? 'private') === 'public';

        $episodeRef = $this->slug($validated['episode_ref'] ?? null) ?? (string) config('adisaan_public.home.episode_ref');
        $story = $this->questions->findOpen($episodeRef);
        if ($story === null) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'error' => 'That question is not open.'], 422)
                : redirect()->route('adisaan.public.answer')->withErrors(['episode_ref' => 'That question is not open.']);
        }

        $decision = $guard->inspect($request, self::FORM, $body);
        if ($decision->rejected()) {
            Log::info('Answer rejected by the form guard', ['reason' => $decision->reason, 'fingerprint' => $decision->fingerprint]);
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => 'The form could not be verified. Reload the page and try again.'], 422);
            }

            return $this->receiptRedirect($request, $isPublic, $episodeRef, '');
        }
        if ($media === null && $this->recentDuplicate($decision->fingerprint, $decision->clientIpHash, $body)) {
            // The same sender posting the same words twice: a stuck retry or
            // a burst. Stored for the record, never announced.
            $decision = new IntakeDecision(IntakeDecision::QUARANTINED, 'duplicate_burst', $decision->fingerprint, $decision->clientIpHash);
        }
        $intentType = in_array((string) ($validated['intent_type'] ?? ''), ['make_a_wish', 'lets_try_this', 'best_way_imagine'], true)
            ? (string) $validated['intent_type'] : 'best_way_imagine';
        $sourceChannel = $this->resolveSourceChannel($request);
        $wantsFollowUp = $request->boolean('consent_followup');

        $mediaPath = null;
        if ($media) {
            $mediaPath = $media->store('echo-media', 'local') ?: null;
            if ($mediaPath === null) {
                throw new \RuntimeException('Could not store the recording.');
            }
        }

        $place = $this->cleanPlaceFields($request);
        $echo = new EchoSubmission;
        $echo->episode_ref = $episodeRef;
        $echo->prompt_key = Str::limit(Str::slug((string) ($validated['prompt_key'] ?? $story['question'] ?? '')), 255, '');
        $echo->prompt_text = (string) ($story['question'] ?? config('adisaan_public.story.question'));
        $echo->body = $body;
        $echo->signature = $this->cleanSignature($validated['signature'] ?? null);
        $echo->language = Str::limit((string) $request->input('language', 'en'), 12, '');
        $echo->country_code = $place['country_code'];
        $echo->city = $place['city'];
        $echo->age_band = $place['age_band'];
        $echo->status = $decision->quarantined() ? EchoSubmission::STATUS_QUARANTINED : EchoSubmission::STATUS_RECEIVED;
        $echo->quarantine_reason = $decision->quarantined() ? $decision->reason : null;
        $echo->contact_email = $wantsFollowUp ? ($validated['contact_email'] ?? null) : null;
        $echo->consent_quote = $isPublic;
        $echo->consent_followup = $wantsFollowUp;
        $echo->client_ip_hash = $decision->clientIpHash;
        $echo->fingerprint = $decision->fingerprint;
        $echo->source_url = Str::limit($request->fullUrl(), 2040, '');
        $echo->referrer = Str::limit((string) $request->headers->get('referer', ''), 2040, '') ?: null;
        $echo->user_agent = Str::limit((string) $request->userAgent(), 500, '') ?: null;
        $echo->metadata = [
            'intake_version' => 1,
            'decision' => $decision->outcome ?? ($decision->quarantined() ? 'quarantined' : 'accepted'),
            'respondent_type' => 'human',
            'review_status' => 'pending',
            'member_sharing' => (! $media && ($validated['share_mode'] ?? '') === 'members') ? ['status' => 'approved', 'basis' => 'sender_selected_members_only', 'confirmed_at' => now()->toIso8601String()] : null,
            'intent_type' => $intentType,
            'source_channel' => $sourceChannel,
            'source_channel_label' => self::BAROMETRU_CHANNELS[$sourceChannel] ?? 'Direct / Sanctuary Search',
            'consent_recorded_at' => now()->toIso8601String(),
            'media' => $media ? ['disk' => 'local', 'path' => $mediaPath, 'mime' => $media->getMimeType(), 'size' => $media->getSize(), 'review_status' => 'pending'] : null,
        ];
        try {
            $echo->save();
        } catch (\Throwable $e) {
            if ($mediaPath) {
                Storage::disk('local')->delete($mediaPath);
            }
            throw $e;
        }

        $this->awardForAnswer($request, $episodeRef, $echo, $media !== null);
        if ($mediaPath !== null) {
            TranscribeVoiceAnswer::dispatch($echo->public_id);
        }
        if ($echo->status !== EchoSubmission::STATUS_QUARANTINED) {
            // The doorkeeper reads it first; the owner hears only what
            // passes, or what the doorkeeper could not read at all.
            ReviewHumanAnswer::dispatch($echo->public_id);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'receipt' => $echo->public_id, 'public' => $isPublic, 'message' => $isPublic ? 'Received. A person reads every public answer before it is shown.' : 'Received privately.'], 201);
        }

        return $this->receiptRedirect($request, $isPublic, $episodeRef, (string) $echo->id, $echo->public_id);
    }

    private function storeVote(Request $request, ClientIpResolver $ips): RedirectResponse|JsonResponse
    {
        $echo = EchoSubmission::query()->whereKey((int) $request->input('vote_echo_id'))->first();
        $isAgent = $echo && (($echo->metadata['respondent_type'] ?? '') === 'agent');
        $visible = $echo && ($isAgent
            ? self::agentPublished($echo)
            : ($echo->consent_quote && in_array($echo->status, [EchoSubmission::STATUS_RECEIVED, EchoSubmission::STATUS_SYNTHESIZED], true) && $echo->released_at !== null));
        if (! $visible) {
            abort(404);
        }
        $ip = $ips->resolve($request);
        $fingerprint = hash('sha256', ($ip ?? 'unknown').'|'.substr((string) $request->userAgent(), 0, 200).'|resonance');
        AnswerVote::query()->firstOrCreate(
            ['echo_submission_id' => $echo->id, 'fingerprint' => $fingerprint],
            ['client_ip_hash' => $ip ? hash('sha256', $ip) : null, 'created_at' => now()]
        );
        $voted = array_unique(array_merge((array) $request->session()->get('adisaan_voted_echoes', []), [$echo->id]));
        $request->session()->put('adisaan_voted_echoes', $voted);
        $votes = (int) ($echo->metadata['votes'] ?? 0) + AnswerVote::query()->where('echo_submission_id', $echo->id)->count();
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'votes' => $votes]);
        }

        return redirect()->to($this->localRedirectTarget($request, $echo).'#answer-'.$echo->id);
    }

    /** The vote form's return address, but only when it stays on this host. */
    private function localRedirectTarget(Request $request, EchoSubmission $echo): string
    {
        $fallback = route('adisaan.public.million', ['story' => $echo->episode_ref]);
        $to = explode('#', (string) $request->input('redirect_to', ''))[0];
        if ($to === '') {
            return $fallback;
        }
        $parts = parse_url($to);
        if (! is_array($parts)) {
            return $fallback;
        }
        if (isset($parts['host']) && $parts['host'] !== $request->getHost()) {
            return $fallback;
        }
        $path = (string) ($parts['path'] ?? '');
        if (! str_starts_with($path, '/')) {
            return $fallback;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /** The same words from the same sender within the hour: a burst, not an answer. */
    private function recentDuplicate(string $fingerprint, ?string $clientIpHash, string $body): bool
    {
        if (trim($body) === '') {
            return false;
        }
        $window = now()->subMinutes(max(1, (int) config('tbwim.intake.duplicate_minutes', 60)));

        return EchoSubmission::query()
            ->where('body', $body)
            ->where('created_at', '>', $window)
            ->where(function ($query) use ($fingerprint, $clientIpHash): void {
                $query->where('fingerprint', $fingerprint);
                if ($clientIpHash !== null) {
                    $query->orWhere('client_ip_hash', $clientIpHash);
                }
            })
            ->exists();
    }

    public function received(Request $request): View
    {
        $request->session()->keep(['shared_mode', 'shared_question', 'shared_intent_type', 'shared_episode_ref', 'shared_submission_id', 'shared_receipt']);

        $ref = trim((string) session('shared_question', ''));
        $published = array_values(array_filter(
            (array) config('adisaan_public.stories', []),
            static fn (array $story): bool => ($story['question_published'] ?? false) === true
        ));
        $answered = null;
        $index = null;
        foreach ($published as $i => $story) {
            if (($story['id'] ?? null) === $ref && $ref !== '') {
                $answered = $story;
                $index = $i;
            }
        }
        $next = $published === [] ? null : ($index === null ? $published[0] : $published[($index + 1) % count($published)]);
        $perspectives = $ref !== '' ? $this->loadPublicPerspectives($ref) : collect();

        $visitorId = $this->visitors->existing($request);
        $submissionId = (string) session('shared_submission_id', '');
        $submission = $submissionId !== '' ? EchoSubmission::query()->find($submissionId) : null;
        $existingWhy = (string) data_get($submission?->metadata, 'why', '');
        $ledgerOn = $this->ledger->enabled();

        return view('adisaan-site.received', $this->shared('answer') + [
            'livingMap' => $this->loadLivingMap(),
            'gratitude' => [
                'enabled' => $ledgerOn,
                'balance' => $ledgerOn && $visitorId ? $this->ledger->balance($visitorId) : 0,
                'pending' => $ledgerOn && $visitorId ? $this->ledger->pendingBalance($visitorId) : 0,
                'spendable' => $ledgerOn && $visitorId ? $this->ledger->spendableBalance($visitorId) : 0,
                'history' => $ledgerOn && $visitorId ? $this->ledger->history($visitorId, 5) : [],
                'answer_reward' => (int) config('adisaan_public.gratitude.earn.answer', 0),
                'first_reward' => (int) config('adisaan_public.gratitude.earn.first_answer', 0),
                'media_reward' => (int) config('adisaan_public.gratitude.earn.media_bonus', 0),
                'minimum_length' => (int) config('adisaan_public.gratitude.minimum_answer_length', 40),
                'claimed' => $ledgerOn && $visitorId ? $this->ledger->isClaimed($visitorId) : false,
                'why_reward' => (int) config('adisaan_public.gratitude.earn.why', 0),
                'share_reward' => (int) config('adisaan_public.gratitude.earn.share', 0),
                'claim_reward' => (int) config('adisaan_public.gratitude.earn.claim', 0),
                'ask_cost' => (int) config('adisaan_public.gratitude.spend.ask_the_world', 0),
            ],
            'canAskWhy' => $submission !== null && $existingWhy === '',
            'hasSubmission' => $submission !== null,
            'existingWhy' => $existingWhy,
            'answerWasShort' => $submission !== null
                && empty(data_get($submission->metadata, 'media'))
                && mb_strlen(trim((string) $submission->body)) < (int) config('adisaan_public.gratitude.minimum_answer_length', 40),
            'answerAwaitingReview' => $submission !== null && (
                $submission->status === EchoSubmission::STATUS_QUARANTINED || ! empty(data_get($submission->metadata, 'media'))
            ),
            'answeredStory' => $answered,
            'answeredSplit' => $this->loadPerspectiveSplit($ref !== '' ? $ref : null),
            'machineAnswers' => $perspectives->where('is_agent', true)->take(4)->values(),
            'nextStory' => $next,
            'canonicalPath' => '/answer/received',
        ]);
    }

    /** The two small forms on the thank-you page: the reason, and the token claim. */
    public function storeReceived(Request $request): RedirectResponse
    {
        $request->session()->keep(['shared_mode', 'shared_question', 'shared_intent_type', 'shared_episode_ref', 'shared_submission_id', 'shared_receipt']);
        $submissionId = (string) session('shared_submission_id', '');
        $submission = $submissionId !== '' ? EchoSubmission::query()->find($submissionId) : null;
        $visitorId = $this->visitors->current($request);

        if ($request->input('form') === 'claim') {
            $name = trim((string) $request->input('name', ''));
            $email = mb_strtolower(trim((string) $request->input('email', '')));
            if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return redirect()->to(route('adisaan.public.received').'#claim')->with('claim_error', 'A first name and a working email, and they are yours.');
            }
            if ($submission === null || $visitorId === null || ! $this->ledger->enabled()) {
                return redirect()->to(route('adisaan.public.received').'#claim')->with('claim_error', 'Answer a question first, then you can claim its tokens.');
            }
            $bonus = $this->ledger->claim($visitorId, $email);

            return redirect()->to(route('adisaan.public.received').'#claim')->with('claim_saved', $bonus);
        }

        $why = trim((string) $request->input('why', ''));
        if (mb_strlen($why) < 10 || mb_strlen($why) > 2000) {
            return redirect()->to(route('adisaan.public.received').'#why')->with('why_error', 'A sentence is enough — tell us why you answered that way.');
        }
        if ($submission === null) {
            return redirect()->to(route('adisaan.public.received').'#why')->with('why_error', 'That answer could not be found any more.');
        }
        $meta = (array) $submission->metadata;
        $meta['why'] = Str::limit($why, 2000, '');
        $submission->metadata = $meta;
        $submission->save();

        $awarded = 0;
        if ($visitorId !== null && $this->ledger->enabled() && $submission->status !== EchoSubmission::STATUS_QUARANTINED) {
            $awarded = $this->ledger->award($visitorId, 'why', 'submission', (string) $submission->id, confirmed: empty($meta['media']), metadata: ['episode_ref' => $submission->episode_ref]);
        }

        return redirect()->to(route('adisaan.public.received').'#why')->with('why_saved', $awarded);
    }

    public function arena(Request $request): View
    {
        $shared = $this->shared('arena');
        $requested = (string) $request->query('story', '');
        $stories = collect($shared['stories']);
        $episode = $stories->firstWhere('id', $requested)
            ?? $stories->firstWhere('id', $this->questions->current()['id'])
            ?? $stories->first();
        $ref = $episode['id'] ?? null;
        $home = config('adisaan_public.home', []);
        $counts = collect($this->loadArena($shared['stories'])['questions'] ?? [])->keyBy('ref');
        $perspectives = $ref ? $this->loadPublicPerspectives($ref, true) : collect();

        return view('adisaan-site.arena', $shared + [
            'livingMap' => $this->loadLivingMap(),
            'home' => $home,
            'homeEpisode' => $episode,
            'breath' => $this->breathState($ref, $home['date_format'] ?? 'j M Y'),
            'battleHuman' => $perspectives->where('is_agent', false)->take(5)->values(),
            'battleMachines' => $perspectives->where('is_agent', true)->take(5)->values(),
            'rejectedAgents' => $this->loadRejectedAgentAnswers($ref, 5),
            'perspectiveSplit' => $counts->get($ref, ['people' => 0, 'machines' => 0]),
        ]);
    }

    public function million(Request $request): View
    {
        $shared = $this->shared('million');
        $episode = $this->currentEpisode($shared['stories'], config('adisaan_public.home.episode_ref'));
        $requested = (string) $request->query('story', '');
        foreach ($shared['stories'] as $story) {
            if (($story['id'] ?? null) === $requested) {
                $episode = $story;
                break;
            }
        }

        return view('adisaan-site.million', $shared + [
            'livingMap' => $this->loadLivingMap(),
            'homeEpisode' => $episode,
            'arena' => $this->loadArena($shared['stories']),
            'publicAnswers' => $this->loadPublicPerspectives($episode['id'] ?? null, true),
            'rejectedAgents' => $this->loadRejectedAgentAnswers($episode['id'] ?? null, 24),
        ]);
    }

    public function privacy(Request $request): View
    {
        return view('adisaan-site.privacy', $this->shared(''));
    }

    /** The Avatar Lab Dispatch: the movement's news desk, read from RSS. */
    public function news(Request $request, NewsReader $reader): View
    {
        $categories = (array) config('tbwim_news.categories', []);
        $category = (string) $request->query('category', '');
        if ($category !== '' && ! isset($categories[$category])) {
            abort(404);
        }
        $query = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, (int) config('tbwim_news.per_page', 12));

        $filtered = $reader->signals($category !== '' ? $category : null, $query !== '' ? $query : null);
        $lead = ($page === 1 && $query === '' && $filtered !== []) ? array_shift($filtered) : null;
        $totalPages = max(1, (int) ceil(count($filtered) / $perPage));
        $page = min($page, $totalPages);
        $grid = array_slice($filtered, ($page - 1) * $perPage, $perPage);
        $counts = $reader->counts();

        $pageUrl = static function (int $target) use ($request): string {
            return $request->fullUrlWithQuery(['page' => $target]);
        };

        return view('adisaan-site.news', $this->shared('news') + [
            'categories' => $categories,
            'activeCategory' => $category,
            'searchQuery' => $query,
            'counts' => $counts,
            'lead' => $lead,
            'videos' => ($page === 1 && ($category === '' || $category === 'adisaan-video-journey')) ? $reader->videos(4) : [],
            'grid' => $grid,
            'fastSignals' => array_slice($reader->signals($category !== '' ? $category : null, $query !== '' ? $query : null), 0, 5),
            'streams' => $reader->streams(),
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => count($filtered) + ($lead !== null ? 1 : 0),
            'prevUrl' => $page > 1 ? $pageUrl($page - 1) : null,
            'nextUrl' => $page < $totalPages ? $pageUrl($page + 1) : null,
        ]);
    }

    /** The follower board the header script polls: the brand site's measured count, read-only. */
    public function boardJson(): JsonResponse
    {
        return response()->json($this->board() + ['write' => false]);
    }

    /**
     * The measured follower board. AIOHM Social counts the accounts and the
     * brand site publishes the result at /api/journey/scoreboard; this reads
     * that public feed and keeps it for ten minutes. When the feed is not
     * reachable the templates show "no measured count yet", never a guess.
     */
    private function board(): array
    {
        $url = $this->brandUrl('/api/journey/scoreboard');

        return Cache::remember('adisaan.board', now()->addMinutes(10), function () use ($url): array {
            try {
                $response = Http::acceptJson()->timeout(6)->get($url);
                $data = $response->ok() ? $response->json() : null;
            } catch (\Throwable $e) {
                Log::info('Follower board unavailable', ['error' => $e->getMessage()]);
                $data = null;
            }
            if (! is_array($data) || ! array_key_exists('total_followers', $data)) {
                return self::EMPTY_BOARD;
            }

            return [
                'total_followers' => $data['total_followers'],
                'last_synced_at' => $data['last_synced_at'] ?? null,
                'accounts' => is_array($data['accounts'] ?? null) ? $data['accounts'] : [],
                'measured_count' => (int) ($data['measured_count'] ?? 0),
                'total_gain' => (int) ($data['total_gain'] ?? 0),
                'goal' => (int) ($data['goal'] ?? config('adisaan_public.answer_goal', 1000000)),
            ];
        });
    }

    private function buildTop10Virality(array $accounts, array $channelVoiceCounts = []): array
    {
        $scored = array_map(function (array $account) use ($channelVoiceCounts): array {
            $handleClean = ltrim((string) ($account['handle'] ?? ''), '@');
            $platform = strtolower((string) ($account['platform'] ?? ''));
            $profileKey = $platform.':'.$handleClean;
            $sanctuaryVoices = (int) ($channelVoiceCounts[$profileKey] ?? 0);
            $measured = (bool) ($account['measured'] ?? false);
            $followers = (int) ($account['followers'] ?? 0);
            $gain = is_int($account['gain'] ?? null) ? (int) $account['gain'] : 0;
            $series = is_array($account['series'] ?? null) ? $account['series'] : [];
            $voiceBoost = min(28, $sanctuaryVoices * 4);
            if ($measured) {
                $viralityScore = min(99, max(18, max(0, $gain) * 18 + min(20, count($series) * 2) + min(58, (int) round(sqrt(max(1, $followers)) * 2.15)) + $voiceBoost));
                $viralityBadge = $gain > 0 ? '🔥 +'.$gain.' VELOCITY · '.$viralityScore.' VIRALITY' : '⚡ '.$viralityScore.' VIRALITY INDEX';
            } else {
                $viralityScore = $voiceBoost;
                $viralityBadge = $sanctuaryVoices > 0 ? '✨ '.$sanctuaryVoices.' SANCTUARY '.($sanctuaryVoices === 1 ? 'VOICE' : 'VOICES') : '⏳ AWAITING API SIGNAL';
            }

            return $account + [
                'profile_key' => $profileKey,
                'profile_url' => self::SOCIAL_PROFILES[$profileKey] ?? null,
                'sanctuary_voices' => $sanctuaryVoices,
                'unlock_via_channel_url' => route('adisaan.public.members', ['mode' => 'make_a_wish', 'utm_source' => $profileKey]),
                'virality_score' => $viralityScore,
                'virality_badge' => $viralityBadge,
            ];
        }, $accounts);

        usort($scored, static function (array $a, array $b): int {
            if (($a['measured'] ?? false) !== ($b['measured'] ?? false)) {
                return ($b['measured'] ?? false) <=> ($a['measured'] ?? false);
            }
            if ($b['virality_score'] !== $a['virality_score']) {
                return $b['virality_score'] <=> $a['virality_score'];
            }
            if (($b['sanctuary_voices'] ?? 0) !== ($a['sanctuary_voices'] ?? 0)) {
                return ($b['sanctuary_voices'] ?? 0) <=> ($a['sanctuary_voices'] ?? 0);
            }
            $gainA = is_int($a['gain'] ?? null) ? $a['gain'] : -999999;
            $gainB = is_int($b['gain'] ?? null) ? $b['gain'] : -999999;
            if ($gainB !== $gainA) {
                return $gainB <=> $gainA;
            }

            return ((int) ($b['followers'] ?? 0)) <=> ((int) ($a['followers'] ?? 0));
        });

        $top10 = array_slice($scored, 0, 10);
        foreach ($top10 as $index => &$entry) {
            $entry['position'] = $index + 1;
        }

        return $top10;
    }

    /** The home page's "one email when a new episode is out" form. */
    public function lead(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'consent_updates' => ['accepted'],
        ]);
        $email = mb_strtolower(trim((string) $validated['email']));
        $isNew = ! DB::table('join_leads')->where('email', $email)->exists();
        DB::table('join_leads')->updateOrInsert(['email' => $email], [
            'consent_updates' => true,
            'source_channel' => $this->resolveSourceChannel($request),
            'referrer' => Str::limit((string) $request->headers->get('referer', ''), 2040, '') ?: null,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, '') ?: null,
            'consented_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ]);
        if ($isNew) {
            NotifyOwnerOfLead::dispatch($email);
        }

        return redirect()->to(route('adisaan.public.about').'#join')->with('lead_success', 'You’re in. I’ll write when the next episode is out.');
    }

    /** The analytics choice, logged as the privacy page says. */
    public function cookieConsent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'consent_id' => ['required', 'string', 'max:64'],
            'decision' => ['required', 'string', 'max:24'],
            'region' => ['nullable', 'string', 'max:16'],
            'preferences' => ['nullable', 'array'],
            'detected_cookies' => ['nullable', 'array'],
            'page' => ['nullable', 'string', 'max:2048'],
        ]);
        DB::table('cookie_consent_logs')->insert([
            'consent_id' => $validated['consent_id'],
            'decision' => $validated['decision'],
            'region' => $validated['region'] ?? null,
            'preferences' => isset($validated['preferences']) ? json_encode($validated['preferences']) : null,
            'detected_cookies' => isset($validated['detected_cookies']) ? json_encode($validated['detected_cookies']) : null,
            'page' => $validated['page'] ?? Str::limit((string) $request->headers->get('referer', ''), 2040, ''),
            'ip_hash' => $request->ip() ? hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')) : null,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, '') ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * "Ask AdiSaan": the companion in the corner talks to Muse, which lives on
     * the platform. The widget posts here; this passes the question to the
     * brand site and returns its answer unchanged.
     */
    public function askMuse(Request $request): JsonResponse
    {
        try {
            $upstream = Http::acceptJson()
                ->timeout(40)
                ->withHeaders(['Content-Type' => 'application/json', 'X-Forwarded-For' => (string) $request->ip()])
                ->withBody((string) $request->getContent(), 'application/json')
                ->post($this->brandUrl('/api/ask-muse'));
        } catch (\Throwable $e) {
            Log::warning('ask-muse proxy failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => 'AdiSaan is away from the keyboard. Try again in a moment.'], 503);
        }

        return response()->json($upstream->json() ?? ['ok' => false], $upstream->status());
    }

    public function askMuseFollowUp(): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => 'Follow-up by email is on adisaan.com.'], 404);
    }

    // --------------------------------------------------------------- shared

    private function shared(string $active): array
    {
        $req = request();
        $stories = $this->stories();
        $channelVoiceCounts = $this->loadChannelVoiceCounts();
        $board = $this->board();

        return [
            'active' => $active,
            'board' => $board,
            'memberUnlocked' => false,
            'memberPass' => null,
            'detectedChannel' => $this->resolveSourceChannel($req),
            'barometruChannels' => self::BAROMETRU_CHANNELS,
            'channelVoiceCounts' => $channelVoiceCounts,
            'totalChannelVoices' => array_sum($channelVoiceCounts),
            'thoughtJournal' => config('adisaan_public.thought_journal', []),
            'thoughtModes' => config('adisaan_public.thought_modes', []),
            'storyboardBanner' => config('adisaan_public.storyboard_partner_banner', []),
            'podcastsPreview' => array_slice((array) config('adisaan_public.podcasts', []), 0, 3),
            'originAnchor' => config('adisaan_public.origin_anchor', []),
            'top10Virality' => $this->buildTop10Virality($board['accounts'] ?? [], $channelVoiceCounts),
            'gameState' => [],
            'publicPerspectives' => $this->loadPublicPerspectives(),
            'totalEchoesCount' => EchoSubmission::query()->countable()->count(),
            'synthesis' => config('adisaan_public.synthesis', []),
            'story' => config('adisaan_public.story'),
            'stories' => $stories,
            'publicUrl' => config('adisaan_public.url'),
            'socialProfiles' => array_values(self::SOCIAL_PROFILES),
            'adminUrl' => config('adisaan_public.admin_url'),
            'canonicalPath' => parse_url((string) $req->getRequestUri(), PHP_URL_PATH) ?: '/',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function stories(): array
    {
        return array_map(function (array $story): array {
            if (is_string($story['id'] ?? null) && ($local = $this->questions->generatedPoster($story['id'])) !== null) {
                $story['poster'] = $local;
            }

            return $story + [
                'share_url' => config('adisaan_public.url').'/#story-'.$story['id'],
                'answer_url' => route('adisaan.public.answer', ['story' => $story['id']]),
                'film_released' => $this->schedule->filmReleased($story),
                'answer_released' => $this->schedule->answerReleased($story),
                'film_opens_at' => $this->schedule->filmOpensAt($story)?->toIso8601String(),
                'answer_opens_at' => $this->schedule->answerOpensAt($story)?->toIso8601String(),
            ];
        }, $this->schedule->visibleQuestions((array) config('adisaan_public.stories', [])));
    }

    private function currentEpisode(array $stories, ?string $ref, string $lang = 'en'): array
    {
        $episode = null;
        $scheduledAt = null;
        foreach ($stories as $story) {
            if (empty($story['opens_at'])) {
                continue;
            }
            try {
                $opensAt = Carbon::parse((string) $story['opens_at'], self::TZ);
            } catch (\Throwable) {
                continue;
            }
            if ($opensAt->isFuture() || ($scheduledAt !== null && $opensAt->lte($scheduledAt))) {
                continue;
            }
            $scheduledAt = $opensAt;
            $episode = $story;
        }
        if ($episode === null) {
            foreach ($stories as $story) {
                if ($ref !== null && ($story['id'] ?? null) === $ref) {
                    $episode = $story;
                    break;
                }
            }
        }
        $episode ??= $stories[0] ?? ((array) config('adisaan_public.story', []) + [
            'id' => config('adisaan_public.story.episode_ref'),
            'answer_url' => route('adisaan.public.answer'),
        ]);

        $lead = $episode['lead'] ?? $episode['question'] ?? '';
        if ($lang === 'ro' && ! empty($episode['lead_ro'])) {
            $lead = $episode['lead_ro'];
        }
        $episode['lead'] = $lead;

        return $episode;
    }

    /**
     * The atlas behind the page. The platform draws visitor activity from
     * GA4; there is no analytics feed here, so only answers with a place are
     * drawn, counted by country.
     */
    private function loadLivingMap(): array
    {
        $answers = [];
        $rows = EchoSubmission::query()->countable()->whereNotNull('country_code')
            ->selectRaw('country_code, COUNT(*) as n')->groupBy('country_code')->get();
        $coords = (array) config('adisaan_public.map_points', []);
        foreach ($rows as $row) {
            $code = strtoupper((string) $row->country_code);
            if (! isset($coords[$code])) {
                continue;
            }
            $answers[] = ['name' => (string) (config('adisaan_public.countries.'.$code) ?? $code), 'count' => (int) $row->n, 'x' => $coords[$code][0], 'y' => $coords[$code][1]];
        }

        return ['visitors' => [], 'answers' => $answers];
    }

    private function loadArena(array $stories): array
    {
        $arena = ['available' => true, 'goal' => (int) config('adisaan_public.answer_goal', 1000000), 'total' => 0, 'people' => 0, 'machines' => 0, 'models' => 0, 'questions' => []];
        $leads = [];
        foreach ($stories as $story) {
            if (! empty($story['question_published']) && ! empty($story['id'])) {
                $leads[(string) $story['id']] = (string) ($story['lead'] ?? $story['question'] ?? $story['id']);
            }
        }
        $tally = [];
        $models = [];
        foreach (EchoSubmission::query()->countable()->get(['episode_ref', 'metadata']) as $row) {
            $ref = (string) $row->episode_ref;
            $meta = is_array($row->metadata) ? $row->metadata : [];
            $isAgent = ($meta['respondent_type'] ?? '') === 'agent';
            $arena['total']++;
            $isAgent ? $arena['machines']++ : $arena['people']++;
            if ($isAgent && ($name = (string) ($meta['agent_model'] ?? '')) !== '') {
                $models[$name] = true;
            }
            if (! isset($leads[$ref])) {
                continue;
            }
            $tally[$ref] ??= ['ref' => $ref, 'lead' => $leads[$ref], 'people' => 0, 'machines' => 0, 'total' => 0];
            $tally[$ref][$isAgent ? 'machines' : 'people']++;
            $tally[$ref]['total']++;
        }
        $arena['models'] = count($models);
        usort($tally, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
        $arena['questions'] = array_values($tally);

        return $arena;
    }

    private function loadPublicPerspectives(?string $questionRef = null, bool $all = false): Collection
    {
        $votedIds = array_map('intval', (array) request()->session()->get('adisaan_voted_echoes', []));

        $rows = EchoSubmission::query()
            ->when($questionRef !== null, static fn ($query) => $query->where('episode_ref', $questionRef))
            ->where(static function ($query): void {
                $query->publiclyVisible()
                    ->orWhere(static function ($agents): void {
                        $agents->where('metadata->respondent_type', 'agent')
                            ->whereIn('status', [EchoSubmission::STATUS_RECEIVED, EchoSubmission::STATUS_SYNTHESIZED])
                            ->where(static function ($published): void {
                                $published->whereNotNull('released_at')->orWhere('metadata->review_status', 'approved');
                            });
                    });
            })
            ->whereNotNull('created_at')
            ->orderByDesc('created_at')
            ->when(! $all, static fn ($query) => $query->take(24))
            ->get()
            ->filter(static fn (EchoSubmission $echo): bool => (($echo->metadata['respondent_type'] ?? '') !== 'agent') || self::agentPublished($echo))
            ->map(function (EchoSubmission $echo) use ($votedIds): array {
                $meta = is_array($echo->metadata) ? $echo->metadata : [];
                $isAgent = ($meta['respondent_type'] ?? '') === 'agent';

                return [
                    'id' => $echo->id,
                    'body' => $echo->displayBody(),
                    'signature' => $echo->signature ?: ($isAgent ? ($meta['agent_model'] ?? 'AI Agent') : 'A fellow seeker'),
                    'place' => $isAgent ? ($meta['agent_developer'] ?? 'Model Arena') : $this->formatPlace($echo->city, $echo->country_code),
                    'episode_ref' => $echo->episode_ref ?: 'who-are-you',
                    'theme' => $echo->theme ?: ($meta['pillar'] ?? 'Living Mirror'),
                    'votes' => max(0, (int) ($meta['votes'] ?? 0)),
                    'voted' => in_array((int) $echo->id, $votedIds, true),
                    'created_human' => $echo->created_at ? $echo->created_at->diffForHumans() : 'recently',
                    'created_on' => $echo->created_at?->format('j M Y'),
                    'created_at' => $echo->created_at?->toIso8601String(),
                    'is_agent' => $isAgent,
                    'agent_model' => $meta['agent_model'] ?? null,
                    'agent_model_detail' => $meta['agent_model_detail'] ?? null,
                    'agent_developer' => $meta['agent_developer'] ?? null,
                    'agent_system_angle' => $meta['agent_system_angle'] ?? null,
                    'agent_source_url' => $meta['agent_source_url'] ?? null,
                ];
            })
            ->values();
        $counts = AnswerVote::query()->whereIn('echo_submission_id', $rows->pluck('id')->all())
            ->selectRaw('echo_submission_id, count(*) as c')->groupBy('echo_submission_id')->pluck('c', 'echo_submission_id');

        return $rows
            ->map(static function (array $row) use ($counts): array {
                $row['votes'] = $row['votes'] + (int) ($counts[$row['id']] ?? 0);

                return $row;
            })
            ->sortByDesc('votes')
            ->values()
            ->when(! $all, static fn (Collection $answers) => $answers->take(8));
    }

    private static function agentPublished(EchoSubmission $echo): bool
    {
        $meta = is_array($echo->metadata) ? $echo->metadata : [];
        if (($meta['respondent_type'] ?? '') !== 'agent'
            || $echo->status === EchoSubmission::STATUS_QUARANTINED
            || in_array($meta['review_status'] ?? '', ['rejected', 'held'], true)) {
            return false;
        }

        return $echo->released_at !== null || ($meta['review_status'] ?? '') === 'approved';
    }

    private function loadRejectedAgentAnswers(?string $questionRef, int $limit): Collection
    {
        return EchoSubmission::query()
            ->when($questionRef !== null, static fn ($query) => $query->where('episode_ref', $questionRef))
            ->where('metadata->respondent_type', 'agent')
            ->where('metadata->review_status', 'rejected')
            ->whereNotNull('metadata->guardrail_checked_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(static fn (EchoSubmission $echo): array => [
                'receipt' => $echo->public_id,
                'model' => trim((string) data_get($echo->metadata, 'agent_model')) ?: 'Unknown model',
                'question_id' => $echo->episode_ref,
            ]);
    }

    private function loadPerspectiveSplit(?string $episodeRef): array
    {
        $split = ['available' => false, 'people' => 0, 'machines' => 0, 'models' => 0];
        if ($episodeRef === null) {
            return $split;
        }
        $split['available'] = true;
        $models = [];
        foreach (EchoSubmission::query()->countable()->where('episode_ref', $episodeRef)->get(['metadata']) as $row) {
            $meta = is_array($row->metadata) ? $row->metadata : [];
            if (($meta['respondent_type'] ?? '') !== 'agent') {
                $split['people']++;

                continue;
            }
            if (($meta['review_status'] ?? '') !== 'approved') {
                continue;
            }
            $split['machines']++;
            $model = trim((string) ($meta['agent_model'] ?? ''));
            if ($model !== '') {
                $models[strtolower($model)] = true;
            }
        }
        $split['models'] = count($models);

        return $split;
    }

    private function breathState(?string $questionRef, string $dateFormat = 'j M Y'): array
    {
        $minVoices = max(1, (int) config('adisaan_public.home.breath_min_voices', 5));
        $state = ['available' => false, 'question_ref' => $questionRef, 'min_voices' => $minVoices, 'site_count' => 0, 'latest' => null, 'earlier' => [], 'versions' => 0];
        if ($questionRef === null || ! Schema::hasTable('question_syntheses')) {
            return $state;
        }
        $fmt = $dateFormat.' H:i';
        $shape = static function (QuestionSynthesis $row) use ($fmt): array {
            $themes = [];
            foreach (is_array($row->themes) ? $row->themes : [] as $t) {
                if (is_array($t) && isset($t['theme'])) {
                    $themes[] = ['theme' => (string) $t['theme'], 'voices' => (int) ($t['voices'] ?? 0)];
                }
            }

            return [
                'version' => (int) $row->version,
                'body' => (string) $row->body,
                'voices_site' => (int) $row->voices_site,
                'voices_social' => 0,
                'voices_public' => 0,
                'total' => (int) $row->voices_site + (int) ($row->voices_agents ?? 0),
                'themes' => $themes,
                'model' => $row->model,
                'prompt_version' => $row->prompt_version,
                'generated_at' => $row->generated_at?->copy()->setTimezone(self::TZ)->format($fmt),
            ];
        };
        $rows = QuestionSynthesis::query()->forQuestion($questionRef)->newestFirst()->limit(12)->get();
        $state['available'] = true;
        $state['versions'] = $rows->count();
        $state['latest'] = $rows->isNotEmpty() ? $shape($rows->first()) : null;
        $state['earlier'] = $rows->slice(1)->map($shape)->values()->all();
        $state['site_count'] = EchoSubmission::query()->publiclyVisible()->where('episode_ref', $questionRef)->count();

        return $state;
    }

    private function loopState(array $story, array $board, string $dateFormat = 'j M Y'): ?array
    {
        $loop = $story['loop'] ?? null;
        if (! is_array($loop) || empty($loop['enabled'])) {
            return null;
        }
        try {
            $opensAt = ! empty($loop['opens_at']) ? Carbon::parse($loop['opens_at'], self::TZ) : null;
            $unlockAt = ! empty($loop['unlock_at']) ? Carbon::parse($loop['unlock_at'], self::TZ) : null;
            $countedAt = ! empty($board['last_synced_at']) ? Carbon::parse($board['last_synced_at']) : null;
        } catch (\Throwable) {
            return null;
        }
        $target = isset($loop['unlock_followers']) ? (int) $loop['unlock_followers'] : null;
        $now = isset($board['total_followers']) && $board['total_followers'] !== null ? (int) $board['total_followers'] : null;
        $fmtDateTime = $dateFormat.' H:i';
        $byFollowers = $target !== null && $now !== null && $now >= $target;
        $byTime = $unlockAt !== null && $unlockAt->isPast();
        $reason = $byTime && (! $byFollowers || ($countedAt !== null && $unlockAt->lte($countedAt))) ? 'time' : ($byFollowers ? 'followers' : null);
        $releasedAt = match ($reason) {
            'time' => $unlockAt,
            'followers' => $countedAt,
            default => null,
        };

        return [
            'state' => $reason === null ? 'open' : 'released',
            'question' => $story['lead'] ?? $story['question'] ?? '',
            'opened_at' => $opensAt?->format($fmtDateTime),
            'followers_at_open' => isset($loop['followers_at_open']) ? (int) $loop['followers_at_open'] : null,
            'followers_now' => $now,
            'counted_on' => $countedAt?->format($dateFormat),
            'target' => $target,
            'to_go' => $target !== null && $now !== null ? max(0, $target - $now) : null,
            'deadline' => $unlockAt?->format($fmtDateTime),
            'unlock_reason' => $reason,
            'released_at' => $releasedAt?->format($fmtDateTime),
            'answer_video' => $loop['answer_video'] ?? null,
            'answer_sha256' => $loop['answer_sha256'] ?? null,
            'answer_url' => $story['answer_url'] ?? route('adisaan.public.answer'),
        ];
    }

    /**
     * The platform FAQ names the brand site's agent endpoint; this app
     * answers at its own host, so point agents here. The config extract
     * itself stays byte-identical to the platform's.
     */
    private function localizeFaq(mixed $faq): mixed
    {
        if (! is_array($faq) || ! is_array($faq['items'] ?? null)) {
            return $faq;
        }
        $host = (string) parse_url((string) config('tbwim.url'), PHP_URL_HOST);
        foreach ($faq['items'] as $i => $item) {
            if (is_array($item) && is_string($item['a'] ?? null)) {
                $faq['items'][$i]['a'] = str_replace('adisaan.com/api/agent/answer', $host.'/api/agent/answer', $item['a']);
            }
        }

        return $faq;
    }

    private function formData(SpamGuard $guard, ?array $story): array
    {
        return [
            'token' => $guard->issueToken(self::FORM),
            'tokenField' => config('echo_intake.form_proof.token_field'),
            'honeypotField' => config('echo_intake.form_proof.honeypot_field'),
            'minimumLength' => config('echo_intake.body.minimum_length'),
            'maximumLength' => config('echo_intake.body.maximum_length'),
            'episodeRef' => $story['id'] ?? config('adisaan_public.story.episode_ref'),
            'promptKey' => Str::limit(Str::slug($story['question'] ?? config('adisaan_public.story.question')), 255, ''),
            'agentEndpoint' => config('tbwim.url').'/api/agent/answer',
        ];
    }

    private function receiptRedirect(Request $request, bool $isPublic, string $episodeRef, string $submissionId, string $receipt = ''): RedirectResponse
    {
        $request->session()->flash('shared_mode', $isPublic ? 'public' : 'private');
        $request->session()->flash('shared_question', $episodeRef);
        $request->session()->flash('shared_intent_type', 'best_way_imagine');
        $request->session()->flash('shared_episode_ref', $episodeRef);
        $request->session()->flash('shared_submission_id', $submissionId);
        $request->session()->flash('shared_receipt', $receipt);
        $request->session()->flash('just_answered', $submissionId !== '');

        return redirect()->route('adisaan.public.received');
    }

    private function awardForAnswer(Request $request, string $episodeRef, EchoSubmission $echo, bool $hasMedia): void
    {
        if (! $this->ledger->enabled() || $echo->status === EchoSubmission::STATUS_QUARANTINED) {
            return;
        }
        $visitorId = $this->visitors->current($request);
        if ($visitorId === null) {
            return;
        }
        if (! $hasMedia && mb_strlen(trim((string) $echo->body)) < (int) config('adisaan_public.gratitude.minimum_answer_length', 40)) {
            return;
        }
        $confirmed = ! $hasMedia;
        $this->ledger->award($visitorId, 'answer', 'submission', (string) $echo->id, confirmed: $confirmed, metadata: ['episode_ref' => $episodeRef]);
        $humansOnQuestion = EchoSubmission::query()->countable()->humans()->where('episode_ref', $episodeRef)->count();
        if ($humansOnQuestion === 1) {
            $this->ledger->award($visitorId, 'first_answer', 'question', $episodeRef, confirmed: $confirmed);
        }
        if ($hasMedia) {
            $this->ledger->award($visitorId, 'media_bonus', 'submission', (string) $echo->id, confirmed: false);
        }
    }

    private function resolveSourceChannel(Request $request): string
    {
        $raw = trim((string) ($request->input('source_channel') ?? $request->query('utm_source') ?? $request->query('channel') ?? $request->query('ref') ?? ''));
        $normalized = $this->normalizeChannelKey($raw);
        if ($normalized === null) {
            $sessionChannel = (string) $request->session()->get('adisaan_source_channel', '');
            if ($sessionChannel !== '' && isset(self::BAROMETRU_CHANNELS[$sessionChannel])) {
                return $sessionChannel;
            }
            $referer = strtolower((string) $request->header('referer', ''));
            $normalized = match (true) {
                str_contains($referer, 'youtube.com') || str_contains($referer, 'youtu.be') => 'youtube:adisaan',
                str_contains($referer, 'instagram.com') => 'instagram:adrian.aparaschivei',
                str_contains($referer, 'tiktok.com') => 'tiktok:adrian.aparaschivei',
                str_contains($referer, 'facebook.com') || str_contains($referer, 'fb.com') => 'facebook:Adisaan.partenerul.tau.online',
                str_contains($referer, 'linkedin.com') => 'linkedin:Adrian',
                str_contains($referer, 'bsky.app') => 'bluesky:aiohmplatform.bsky.social',
                default => 'direct',
            };
        }
        $request->session()->put('adisaan_source_channel', $normalized);

        return $normalized;
    }

    private function normalizeChannelKey(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }
        if (isset(self::BAROMETRU_CHANNELS[$raw])) {
            return $raw;
        }
        $lower = strtolower($raw);
        foreach (array_keys(self::BAROMETRU_CHANNELS) as $key) {
            if (strtolower($key) === $lower) {
                return $key;
            }
        }

        return match (true) {
            str_contains($lower, 'aiohm-platform') || str_contains($lower, 'youtube_aiohm') => 'youtube:aiohm-platform',
            str_contains($lower, 'youtube') || $lower === 'yt' => 'youtube:adisaan',
            str_contains($lower, 'ohm.events') && str_contains($lower, 'insta') => 'instagram:ohm.events.agency',
            str_contains($lower, 'instagram') || $lower === 'ig' => 'instagram:adrian.aparaschivei',
            str_contains($lower, 'tiktok') || $lower === 'tt' => 'tiktok:adrian.aparaschivei',
            str_contains($lower, 'marketingwithadisaan') => 'facebook:marketingwithAdiSaan',
            str_contains($lower, 'ohm.events') && str_contains($lower, 'fb') => 'facebook:OHM.events.agency',
            str_contains($lower, 'facebook') || $lower === 'fb' => 'facebook:Adisaan.partenerul.tau.online',
            str_contains($lower, 'linkedin') || $lower === 'li' => 'linkedin:Adrian',
            str_contains($lower, 'bluesky') || str_contains($lower, 'bsky') => 'bluesky:aiohmplatform.bsky.social',
            $lower === 'direct' => 'direct',
            default => null,
        };
    }

    private function loadChannelVoiceCounts(): array
    {
        $counts = array_fill_keys(array_keys(self::BAROMETRU_CHANNELS), 0);
        foreach (EchoSubmission::query()->whereNotNull('metadata')->pluck('metadata') as $meta) {
            $arr = is_array($meta) ? $meta : json_decode((string) $meta, true);
            if (! is_array($arr)) {
                continue;
            }
            $ch = $this->normalizeChannelKey((string) ($arr['source_channel'] ?? ''));
            if ($ch !== null && isset($counts[$ch])) {
                $counts[$ch]++;
            }
        }

        return $counts;
    }

    private function formatPlace(?string $city, ?string $countryCode): ?string
    {
        $city = trim((string) $city);
        $code = strtoupper(trim((string) $countryCode));
        if ($city !== '' && $code !== '') {
            return $city.', '.$code;
        }
        if ($code !== '') {
            return (string) (config('adisaan_public.countries.'.$code) ?? $code);
        }

        return $city === '' ? null : $city;
    }

    private function cleanPlaceFields(Request $request): array
    {
        $country = strtoupper(trim((string) $request->input('country_code', '')));
        $countries = (array) config('adisaan_public.countries', []);
        $city = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $request->input('city', ''))) ?? '');
        $band = trim((string) $request->input('age_band', ''));
        $bands = (array) config('adisaan_public.age_bands', []);

        return [
            'country_code' => array_key_exists($country, $countries) ? $country : null,
            'city' => $city === '' ? null : Str::limit($city, 80, ''),
            'age_band' => array_key_exists($band, $bands) ? $band : null,
        ];
    }

    private function slug(mixed $value): ?string
    {
        $slug = Str::slug((string) ($value ?? ''));

        return $slug === '' ? null : Str::limit($slug, 64, '');
    }

    private function cleanSignature(?string $signature): ?string
    {
        $clean = trim(strip_tags((string) $signature));

        return $clean === '' ? null : Str::limit($clean, 60, '');
    }

    private function brandUrl(string $path): string
    {
        return rtrim((string) config('adisaan_public.brand_url', 'https://adisaan.com'), '/').$path;
    }
}
