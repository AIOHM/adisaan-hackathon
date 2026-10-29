@extends('adisaan-site.layout')
@section('title', 'The Question — AdiSaan')
@section('description', 'Watch AdiSaan ask one honest question, then share the answer you imagine in your own words.')
@push('styles')
<link rel="stylesheet" href="/assets/adisaan/site/question-answer-countdown.css?v=20261026-pillars">
@endpush
@section('content')
@php
    // Only released questions belong in the public archive. Future releases
    // opt in through tenant story settings; the filler follows grid parity.
    $publishedStories = array_values(array_filter($stories, static fn (array $story): bool => ($story['question_published'] ?? false) === true));
    $featuredStory = $publishedStories[0] ?? config('adisaan_public.story');
    // Released, not merely rendered. A film can be on disk for a week
    // before its morning; until then the frame counts down to it.
    $featuredHasVideo = !empty($featuredStory['film_released']);
    $featuredFilmOpensAt = $featuredStory['film_opens_at'] ?? null;
@endphp

<section class="question-page-v2" id="question-stage" aria-labelledby="question-heading">
    <div class="container question-page-v2__inner">
        <header class="question-page-v2__intro reveal">
            <p class="home-episode__tag">#TBWIM</p>
            <p class="home-episode__eyebrow">· THE QUESTION BEHIND EVERY QUESTION ·</p>
            {{-- The heading reserves the tallest question's box and sits at the
                 bottom of it, so turning the reel does not shove the video up
                 and down by 200px. The box is sized by a stack of every
                 question, all in the same grid cell and invisible: the real
                 one is measured, at whatever width the reader is on. A
                 longest-string guess does not work — question 5 is shorter
                 than question 8 and wraps one line taller. --}}
            <div class="stage-box">
                <div class="stage-box__ghosts" aria-hidden="true">
                    @foreach($publishedStories as $ghost)
                        <span>{{ $ghost['lead'] ?? $ghost['question'] }}</span>
                    @endforeach
                </div>
                <h1 id="question-heading" data-stage-title>{{ $featuredStory['lead'] ?? $featuredStory['question'] }}</h1>
            </div>
            <p>Take a moment with the question. Then tell us what you imagine, in your own words.</p>
        </header>

        {{-- Both states ship in the markup and the carousel toggles between
             them, because nine of the ten questions have no film yet. Building
             the frame on the fly in JS would leave a question looking broken
             for anyone with scripting off; this way the server decides the
             first state and the reel only switches a class. --}}
        <div @class(['question-page-v2__film', 'reveal', 'is-soon' => !$featuredHasVideo]) id="building" data-film>
            <video id="netflix-main-video"
                   data-episode="{{ $featuredStory['id'] ?? '' }}"
                   controls playsinline preload="metadata"
                   poster="{{ $featuredStory['poster'] ?? '/assets/adisaan/site/story-poster.jpg' }}"
                   @if($featuredHasVideo) src="{{ $featuredStory['video'] }}" @endif>@if(!empty($featuredStory['captions']))<track kind="captions" src="{{ $featuredStory['captions'] }}" srclang="en" label="English">@endif Your browser does not support video playback.</video>
            <div class="question-film-soon__frame" data-film-soon>
                <span class="question-film-soon__badge" data-stage-soon-badge>{{ $featuredStory['badge'] ?? 'QUESTION' }}</span>
                @if($featuredFilmOpensAt)
                    {{-- A dated film is a promise with a time on it, so the
                         frame says the time rather than "being made". --}}
                    <p class="question-film-soon__line">AdiSaan asks this question in</p>
                    <p class="film-countdown" data-film-countdown data-opens-at="{{ $featuredFilmOpensAt }}" data-server-now="{{ now()->toIso8601String() }}" role="timer" aria-live="polite">
                        <span><strong data-countdown-days>00</strong><small>days</small></span>
                        <span><strong data-countdown-hours>00</strong><small>hours</small></span>
                        <span><strong data-countdown-minutes>00</strong><small>minutes</small></span>
                        <span><strong data-countdown-seconds>00</strong><small>seconds</small></span>
                    </p>
                    <p class="question-film-soon__sub">You can answer it now. The film is not the permission.</p>
                @else
                    <p class="question-film-soon__line">The film for this question is being made.</p>
                    <p class="question-film-soon__sub">You do not have to wait for it — the question is open now.</p>
                @endif
            </div>
            <div class="question-page-v2__film-note">
                <span data-stage-eyebrow>{{ $featuredStory['eyebrow'] ?? 'ADISAAN · THE QUESTION' }}</span>
                <span data-stage-badge>{{ $featuredHasVideo ? ($featuredStory['duration_label'] ?? '') : 'Film coming' }}</span>
            </div>
        </div>

        {{-- The answer reel.

             Ten questions, one frame. The middle card is the question you are
             on; its neighbours sit behind it turned on their edge, so the set
             reads as a reel you can walk rather than a page you have to go
             back to. Turning the reel also turns the whole stage above it —
             heading, film and the answer link — through the same code path the
             archive cards use, so there is one idea of "which question am I
             on" and not two that can disagree.

             Only question one has a film and an answer today. The other nine
             render their real state, which is that the answer is still being
             made. The moment one is rendered, setting its story keys fills the
             card in; nothing here needs changing. --}}
        <div class="question-page-v2__answer reveal">
            <p class="question-page-v2__label" data-stage-label>{{ $featuredHasVideo ? 'THE QUESTION' : 'YOUR TURN' }}</p>
            <div class="stage-box stage-box--quote">
                <div class="stage-box__ghosts" aria-hidden="true">
                    @foreach($publishedStories as $ghost)
                        <span>“{{ $ghost['question'] }}”</span>
                    @endforeach
                </div>
                <blockquote data-stage-question>“{{ $featuredStory['question'] }}”</blockquote>
            </div>
            <p data-stage-invite>{{ $featuredHasVideo ? 'What comes to mind? Your own words are enough.' : 'What would you build, try, or change? Your own words are enough.' }}</p>

            <div class="qreel" data-qreel>
                <button type="button" class="qreel__nav qreel__nav--prev" data-qreel-prev aria-label="Previous question">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>

                <div class="qreel__viewport">
                    <div class="qreel__track" data-qreel-track>
                        @foreach($publishedStories as $i => $story)
                            @php
                                $slideRevealAt = !empty($story['answer_reveal_at'])
                                    ? \Illuminate\Support\Carbon::parse($story['answer_reveal_at'], 'Europe/Bucharest')
                                    : null;
                                // Released by the schedule AND actually on disk. Either
                                // alone would put a player in front of nothing.
                                $slideReady = !empty($story['answer_released'])
                                    && !empty($story['answer_video'])
                                    && \Illuminate\Support\Facades\Storage::disk('local')->exists('question-answer-media/'.config('adisaan_public.tenant').'/'.$story['id'].'.mp4');
                                $slideNo = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                            @endphp
                            <article class="qreel__slide" data-qreel-slide data-index="{{ $i }}" data-story-id="{{ $story['id'] }}"
                                     aria-label="Question {{ $slideNo }} of {{ count($publishedStories) }}">
                                @if($slideReady)
                                    <div class="question-answer-clock" data-answer-countdown data-story-id="{{ $story['id'] }}" data-deadline="{{ $slideRevealAt->toIso8601String() }}" data-server-now="{{ now()->toIso8601String() }}" data-answer-video="{{ $story['answer_video'] ?? '' }}">
                                        <div class="question-answer-clock__content" aria-live="polite">
                                            <div class="qreel__head">
                                                <p class="question-answer-clock__eyebrow" data-answer-eyebrow>ADISAAN’S ANSWER</p>
                                                <span class="qreel__head-no" aria-label="Question {{ $slideNo }}">{{ $slideNo }}</span>
                                            </div>
                                            <video class="question-answer-clock__video" data-answer-main controls playsinline preload="metadata" src="{{ $story['answer_video'] }}">Your browser does not support video playback.</video>
                                            @if(!empty($story['answer_why_video']))
                                                {{-- The bonus answer takes the same frame once the first
                                                     one ends: a short stand-by, then why. Hidden until
                                                     then, and the whole sequence is progressive — with no
                                                     JS the first answer still plays on its own. --}}
                                                {{-- Deliberately the block form. The inline one-line
                                                     variant mis-parses nested parentheses and leaves a
                                                     PHP tag open, which swallows the rest of the
                                                     template. A Blade comment is no place to show the
                                                     broken syntax either: directives and raw PHP are
                                                     lifted out before comments are stripped, so an
                                                     example written here is compiled for real. --}}
                                                @php
                                                    $whyWait = (int) ($story['answer_why_wait_seconds'] ?? 10);

                                                    // The line named the million on every card, which was
                                                    // true only of question one. Take the tail off this
                                                    // question's own stem instead.
                                                    $whyStem = trim(rtrim(trim((string) ($story['question'] ?? $story['lead'] ?? '')), '?'));
                                                    $whyTail = preg_match('/^what is the best way you imagine\s+(.+)$/i', $whyStem, $whyMatch) === 1
                                                        ? trim($whyMatch[1])
                                                        : '';
                                                @endphp
                                                <div class="answer-standby" data-answer-standby hidden data-wait="{{ $whyWait }}">
                                                    <p class="answer-standby__line">{{ $whyTail !== '' ? 'Why this is the best way I imagine '.$whyTail.'.' : 'Why that is the best way I imagine it.' }}</p>
                                                    <p class="answer-standby__clock" role="timer" aria-live="polite"><strong data-standby-seconds>{{ $whyWait }}</strong><span>seconds</span></p>
                                                    <button type="button" class="answer-standby__skip" data-standby-skip>Show it now</button>
                                                </div>
                                                <video class="question-answer-clock__video" data-answer-why controls playsinline preload="none" hidden
                                                       src="{{ $story['answer_why_video'] }}">Your browser does not support video playback.</video>
                                            @endif
                                        </div>
                                    </div>
                                {{-- A rolling ten seconds that ends in the
                                     freshest released answers, not a fixed
                                     embargo date that goes stale. The featured
                                     card always counts; later cards only while
                                     their scheduled date is still future. --}}
                                @elseif($i === 0 || ($slideRevealAt && $slideRevealAt->isFuture()))
                                    <div class="question-answer-clock" data-answer-countdown data-story-id="{{ $story['id'] }}" data-deadline="{{ now()->addSeconds(10)->toIso8601String() }}" data-server-now="{{ now()->toIso8601String() }}" data-answer-video="{{ $story['answer_video'] ?? '' }}">
                                        <div class="question-answer-clock__content" aria-live="polite">
                                            <div class="qreel__head">
                                                <p class="question-answer-clock__eyebrow">FRESH ANSWERS IN</p>
                                                <span class="qreel__head-no" aria-label="Question {{ $slideNo }}">{{ $slideNo }}</span>
                                            </div>
                                            <div class="question-answer-clock__digits" role="timer" aria-label="Time until AdiSaan's answer window">
                                                <span><strong data-countdown-days>00</strong><small>days</small></span>
                                                <span class="question-answer-clock__colon" aria-hidden="true">:</span>
                                                <span><strong data-countdown-hours>00</strong><small>hours</small></span>
                                                <span class="question-answer-clock__colon" aria-hidden="true">:</span>
                                                <span><strong data-countdown-minutes>00</strong><small>minutes</small></span>
                                                <span class="question-answer-clock__colon" aria-hidden="true">:</span>
                                                <span><strong data-countdown-seconds>00</strong><small>seconds</small></span>
                                            </div>
                                            <p class="question-answer-clock__note">Your answer can go first. Fresh answers land when the clock reaches zero.</p>
                                        </div>
                                    </div>
                                @elseif(!empty($story['film_released']))
                                    {{-- No answer yet, but the question has a film: play that
                                         rather than an empty frame. A card with nothing on it
                                         beside a question that does have a video reads as
                                         broken. The line says which video this is, so the card
                                         never promises an answer it does not have. --}}
                                    <div class="question-answer-clock">
                                        <div class="question-answer-clock__content">
                                            <div class="qreel__head">
                                                <p class="question-answer-clock__eyebrow">ADISAAN ASKS</p>
                                                <span class="qreel__head-no" aria-label="Question {{ $slideNo }}">{{ $slideNo }}</span>
                                            </div>
                                            <video class="question-answer-clock__video" controls playsinline preload="none"
                                                   @if(!empty($story['poster'])) poster="{{ $story['poster'] }}" @endif
                                                   src="{{ $story['video'] }}">Your browser does not support video playback.</video>
                                            <p class="question-answer-clock__note">My answer to this one is still being made. Yours can go first.</p>
                                        </div>
                                    </div>
                                @else
                                    <div class="question-answer-clock question-answer-clock--waiting">
                                        <div class="question-answer-clock__content">
                                            <div class="qreel__head">
                                                <p class="question-answer-clock__eyebrow">ADISAAN’S ANSWER IS BEING MADE</p>
                                                <span class="qreel__head-no" aria-label="Question {{ $slideNo }}">{{ $slideNo }}</span>
                                            </div>
                                            <div class="qreel__soon" aria-hidden="true">
                                                <img src="/assets/adisaan/site/ohm-mark.svg" alt="" width="54" height="56">
                                            </div>
                                            <p class="question-answer-clock__note">Yours can go first. I answer every question in the reel, in order.</p>
                                        </div>
                                    </div>
                                @endif

                            </article>
                        @endforeach
                    </div>
                </div>

                <button type="button" class="qreel__nav qreel__nav--next" data-qreel-next aria-label="Next question">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>

                <div class="qreel__rail">
                    <p class="qreel__count"><strong data-qreel-current>01</strong> / {{ str_pad((string) count($publishedStories), 2, '0', STR_PAD_LEFT) }}</p>
                    <ol class="qreel__dots" role="tablist" aria-label="Questions">
                        @foreach($publishedStories as $i => $story)
                            <li role="presentation"><button type="button" role="tab" class="qreel__dot" data-qreel-dot data-index="{{ $i }}"
                                aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
                                aria-label="Question {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}"></button></li>
                        @endforeach
                    </ol>
                </div>
            </div>

            {{-- One call to action for the whole reel, carrying the number of
                 the question it will answer. Ten buttons saying the same thing
                 in ten cards was the same ask repeated, and only one of them
                 was ever reachable. --}}
            <a class="btn-primary question-page-v2__cta" data-stage-answer-link href="{{ $featuredStory['answer_url'] ?? route('adisaan.public.answer') }}"><span data-stage-action>Share your answer</span> <span class="btn-no" data-stage-no>01</span> <span aria-hidden="true">↗</span></a>
            {{-- Most crawlers never reach /answer. One line here, next to the
                 human ask, is where an agent (or the person running one)
                 learns the machine door exists (2026-10-26). --}}
            <p class="question-page-v2__agent-note">Running an AI agent? It can answer too: <a href="{{ route('adisaan.public.agent.answer.info', ['story' => $featuredStory['id'] ?? null]) }}">machine instructions ↗</a></p>
        </div>

        <div class="netflix-episode-data" hidden>
            @foreach($publishedStories as $idx => $ep)
                <span data-ep-select
                      data-ep-index="{{ $idx }}"
                      data-ep-id="{{ $ep['id'] }}"
                      data-ep-title="{{ $ep['lead'] ?? $ep['question'] }}"
                      data-ep-eyebrow="{{ $ep['eyebrow'] }}"
                      data-ep-desc="{{ $ep['description'] }}"
                      data-ep-question="{{ $ep['question'] }}"
                      data-ep-invite="What comes to mind? Your own words are enough."
                      data-ep-action="Share your answer"
                      data-ep-duration="{{ $ep['duration_label'] ?? '' }}"
                      data-ep-badge="{{ $ep['badge'] ?? 'QUESTION' }}"
                      data-ep-no="{{ str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT) }}"
                      data-ep-video="{{ $ep['video'] ?? '' }}"
                      data-ep-poster="{{ $ep['poster'] ?? '' }}"
                      data-ep-answer-url="{{ $ep['answer_url'] }}"></span>
            @endforeach
        </div>

        <section class="question-page-v2__archive" aria-labelledby="question-archive-heading">
            <div class="question-page-v2__archive-head reveal">
                <p class="question-page-v2__label">THE CONVERSATION CONTINUES</p>
                <h2 id="question-archive-heading">Explore the questions</h2>
                <p>Start with whichever one stays with you.</p>
            </div>
            <div class="question-page-v2__archive-grid">
                @foreach($publishedStories as $idx => $story)
                    @php
                        $areaKey = $story['life_area'] ?? null;
                        $area = $areaKey ? ($lifeAreas[$areaKey] ?? null) : null;
                        $inArea = $areaKey ? collect($publishedStories)->where('life_area', $areaKey) : collect();
                        $doneInArea = $inArea->filter(fn (array $x): bool => in_array($x['id'] ?? '', $answeredRefs ?? [], true))->count();
                        $areaTotal = max(1, $inArea->count());
                        $answeredThis = in_array($story['id'] ?? '', $answeredRefs ?? [], true);

                        $count = $questionCounts[$story['id']] ?? ['people' => 0, 'machines' => 0, 'total' => 0];
                        $goal = max(1, (int) ($answerGoal ?? 1000000));
                        // One million answers together, not one million each. The
                        // bar is the whole site's climb; this card's own count is
                        // what it has put into it.
                        $siteTotal = (int) ($answersTotal ?? 0);
                        $pct = $siteTotal / $goal * 100;
                        // A true millionth of a percent draws nothing, which reads
                        // as broken rather than early. Floor the bar, keep the real
                        // numbers in the label.
                        $drawn = $pct > 0 ? max(1.2, $pct) : 0;
                        $roman = ['I','II','III','IV','V','VI','VII','VIII','IX','X'][$loop->index] ?? (string) ($loop->index + 1);
                    @endphp
                    <article @class(['qcard', 'reveal', 'is-answered' => $answeredThis]) data-archive-episode-id="{{ $story['id'] }}" data-qcard>
                        <div class="qcard__flip">
                            {{-- Face: the card you are dealt. --}}
                            <div class="qcard__face qcard__face--front">
                                <span class="qcard__numeral" aria-hidden="true">{{ $roman }}</span>
                                <h3 class="qcard__question">{{ $story['lead'] ?? $story['question'] }}</h3>
                                <span class="qcard__turn">{{ $area ? $area['label'] : 'Any area' }} · turn the card</span>
                                {{-- The OHM seal, where a tarot deck puts its maker's mark. --}}
                                <img class="qcard__seal" src="/assets/adisaan/site/ohm-mark.svg" alt="" width="38" height="40" loading="lazy" aria-hidden="true">
                            </div>

                            {{-- Back: what the clearing has heard so far. --}}
                            <div class="qcard__face qcard__face--back">
                                <p class="qcard__back-title">{{ $story['badge'] ?? 'Question' }}</p>

                                {{-- Score beside the pillars, one row, so the small card keeps its height. --}}
                                <div class="qcard__score-row">
                                    <div class="qcard__score">
                                        <span class="qcard__score-side"><strong>{{ number_format($count['people']) }}</strong><small>people</small></span>
                                        <span class="qcard__score-vs" aria-hidden="true">vs</span>
                                        <span class="qcard__score-side qcard__score-side--machine"><strong>{{ number_format($count['machines']) }}</strong><small>machines</small></span>
                                    </div>
                                    @include('adisaan-site.partials.answer-pillars', ['count' => $count, 'compact' => true])
                                </div>

                                <div class="qcard__climb">
                                    <span class="qcard__climb-bar" role="img" aria-label="{{ number_format($siteTotal) }} of {{ number_format($goal) }} answers across every question"><i style="width:{{ $drawn }}%"></i></span>
                                    <span class="qcard__climb-meta">{{ number_format($count['total']) }} received for this question &middot; {{ number_format($siteTotal) }} of {{ number_format($goal) }} together</span>
                                </div>

                                @if($area)
                                    <span class="qcard__area">{{ $area['label'] }} · you have answered {{ $doneInArea }} of {{ $areaTotal }}</span>
                                @endif

                                <div class="qcard__actions">
                                    @if(!empty($story['video']))<button type="button" data-play-episode-id="{{ $story['id'] }}" aria-label="Watch {{ $story['badge'] ?? 'this question' }}">Watch <span aria-hidden="true">↗</span></button>@endif
                                    <a href="{{ $story['answer_url'] ?? route('adisaan.public.answer', ['story' => $story['id']]) }}">{{ $answeredThis ? 'Answer again' : 'Answer' }} <span aria-hidden="true">↗</span></a>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
                @if(count($publishedStories) % 2 === 1)
                    <div class="question-page-v2__archive-card question-page-v2__archive-card--soon reveal">
                        <span class="question-page-v2__number">THE CONVERSATION CONTINUES</span>
                        <h3>More questions soon</h3>
                        <p>One question at a time. Come back for the next one.</p>
                    </div>
                @endif
            </div>
        </section>
    </div>
</section>
<script src="/assets/adisaan/site/question-answer-countdown.js?v=20261027-countdown1" defer></script>
{{-- After site.js, which publishes the stage selector the reel turns. --}}
<script src="/assets/adisaan/site/question-reel.js?v=20261024-reel" defer></script>
@endsection
