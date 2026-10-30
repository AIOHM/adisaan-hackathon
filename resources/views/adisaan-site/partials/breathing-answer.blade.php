{{-- The breathing answer (website plan, section 16): what the voices on the current question add up to,
     in AdiSaan's words, version by version. Expects $breath (from AdiSaanPublicSiteController::breathState,
     or null) and $home['breath_words']. The arena heading carries one gold light pass;
     every number here is a count of rows, shown with the time of its version. --}}
@php
    $bw = ($home['breath_words'] ?? []) + [
        'title' => 'ADISAAN ARENA',
        'version' => 'Version :n · :voices voices · :date',
        'site' => 'answers on this site',
        'social' => 'comments on AdiSaan’s own posts',
        'public' => 'public tagged posts',
        'public_pending' => 'public tagged posts (not collected yet)',
        'themes' => 'Themes',
        'earlier' => 'Earlier versions (:n)',
    ];
    $bt = static fn (string $key, array $vars = []): string => strtr($bw[$key] ?? '', array_combine(array_map(static fn ($k) => ':'.$k, array_keys($vars)), array_map(static fn ($v) => (string) $v, $vars)));
    $latest = $breath['latest'] ?? null;
    $earlier = $breath['earlier'] ?? [];
@endphp
@php
    // The duel lives in this block (Adrian, 2026-10-25): what people imagine
    // so far, and beside it what the machines did with the same question.
    $breathAvailable = !empty($breath) && !empty($breath['available']);
    $q = $homeEpisode['lead'] ?? $homeEpisode['question'] ?? config('adisaan_public.story.question');
    $humans = collect($battleHuman ?? []);
    $machines = collect($battleMachines ?? []);
    $failedMachines = collect($rejectedAgents ?? []);
    $hasDuel = $humans->isNotEmpty() || $machines->isNotEmpty() || $failedMachines->isNotEmpty();
@endphp
@if($breathAvailable || $hasDuel)
<div class="qloop qloop--breath" id="breath" aria-labelledby="breath-heading">
    <h3 id="breath-heading" class="qloop__title qloop__title--arena">
        @if(!empty($arenaPicker))
            <span class="arena-brand"><img src="/assets/adisaan/site/wordmark.svg" alt="AdiSaan"><span>Arena</span></span>
        @else
            <span>{{ $bw['title'] }}</span><span class="qloop__title-light" aria-hidden="true">{{ $bw['title'] }}</span>
        @endif
    </h3>
    @if(empty($arenaPicker) && $breathAvailable && $latest)
    <p class="qloop__meta breath__version">{{ $bt('version', ['n' => $latest['version'], 'voices' => number_format((int) $latest['total']), 'date' => $latest['generated_at']]) }}</p>
    <p class="breath__body">{{ $latest['body'] }}</p>
    <ul class="breath__circles">
        <li><strong>{{ number_format((int) $latest['voices_site']) }}</strong><span>{{ $bw['site'] }}</span></li>
        <li><strong>{{ number_format((int) $latest['voices_social']) }}</strong><span>{{ $bw['social'] }}</span></li>
        <li><strong>{{ number_format((int) $latest['voices_public']) }}</strong><span>{{ (int) $latest['voices_public'] > 0 ? $bw['public'] : $bw['public_pending'] }}</span></li>
    </ul>
    @if(!empty($latest['themes']))
    <p class="qloop__meta breath__themes"><span>{{ $bw['themes'] }}:</span> {{ collect($latest['themes'])->map(static fn (array $t): string => $t['theme'].' ('.number_format((int) $t['voices']).')')->implode(' · ') }}</p>
    @endif
    @if(!empty($earlier))
    <details class="breath__history">
        <summary>{{ $bt('earlier', ['n' => count($earlier)]) }}</summary>
        <ol>
            @foreach($earlier as $v)
            <li>
                <p class="breath__when">{{ $bt('version', ['n' => $v['version'], 'voices' => number_format((int) $v['total']), 'date' => $v['generated_at']]) }}</p>
                <p>{{ $v['body'] }}</p>
            </li>
            @endforeach
        </ol>
    </details>
    @endif
    @endif

@if($hasDuel)
    {{-- The two stages were a roadmap; this is the thing itself. One
         question, both sides, and the answers underneath with the top
         three marked. Everything here is counted — an empty side says so
         rather than showing a placeholder. --}}
    <div class="duel" aria-label="The same question, answered by people and by machines">
        {{-- The question once, not once per side. It is the same
             question — printing it twice was duplication at every width
             and four wrapped lines at this one. --}}
        @if(!empty($arenaPicker))
            @include('adisaan-site.partials.arena-question-picker')
        @else
            <p class="duel__q">{{ $q }}</p>
        @endif

        <div class="duel__head">
            <div class="duel__side duel__side--human">
                @include('adisaan-site.partials.crowd', ['kind' => 'people', 'label' => 'People answering', 'showLabel' => false])
                <span class="duel__count">{{ $humans->count() }} public shown · {{ number_format($perspectiveSplit['people'] ?? $humans->count()) }} received</span>
            </div>

            <div class="duel__bolt" aria-hidden="true">
                <svg viewBox="0 0 48 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M28 4 10 62h14l-6 54 26-66H29l9-46z" fill="url(#duelBolt)" stroke="#f4f0e8" stroke-width="1.5" stroke-linejoin="round"/>
                    <defs>
                        <linearGradient id="duelBolt" x1="24" y1="4" x2="24" y2="116" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#9adc9f"/><stop offset="0.52" stop-color="#f4f0e8"/><stop offset="1" stop-color="#e9c775"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>

            <div class="duel__side duel__side--machine">
                @include('adisaan-site.partials.crowd', ['kind' => 'machines', 'label' => 'AI models answering', 'showLabel' => false, 'brandColors' => $arenaBrandColors ?? false])
                <span class="duel__count">{{ $machines->count() }} public shown · {{ number_format($perspectiveSplit['machines'] ?? $machines->count()) }} received</span>
            </div>
        </div>

        <div class="duel__lists">
            @foreach([['side' => 'human', 'rows' => $humans, 'empty' => 'No public human answers yet.'],
                      ['side' => 'machine', 'rows' => $machines, 'empty' => 'No machine answer has passed the guardrails yet.']] as $column)
                <ol class="duel__list duel__list--{{ $column['side'] }}">
                    @forelse($column['rows'] as $i => $row)
                        <li @class(['duel__answer', 'is-top' => $i < 3])>
                            @if($i < 3)<span class="duel__rank" aria-label="Rank {{ $i + 1 }}">{{ $i + 1 }}</span>@endif
                            <p class="duel__body">{{ \Illuminate\Support\Str::limit($row['body'], 190) }}</p>
                            <p class="duel__by">{{ $row['signature'] }}@if(($row['votes'] ?? 0) > 0) <span>· {{ number_format($row['votes']) }} {{ (int) $row['votes'] === 1 ? 'vote' : 'votes' }}</span>@endif</p>
                            @if(!empty($arenaPicker) && !empty($row['id']))
                                <a class="duel__more" href="{{ route('adisaan.public.million', ['story' => $homeEpisode['id'] ?? '']) }}#answer-{{ $row['id'] }}" aria-label="Read the full answer from {{ $row['signature'] }}">More ↗</a>
                            @endif
                        </li>
                    @empty
                        <li class="duel__empty">{{ $column['empty'] }}</li>
                    @endforelse
                    @if($column['side'] === 'machine')
                        @foreach($failedMachines as $failed)
                            <li class="duel__answer duel__answer--guardrail">
                                <p class="duel__body">The response from model {{ $failed['model'] }} did not pass the guardrails.</p>
                                <p class="duel__by">Muse guardrail · original response withheld</p>
                            </li>
                        @endforeach
                    @endif
                </ol>
            @endforeach
        </div>

        <p class="duel__note">Counted, never estimated. Public answers from people and AI agents are shown separately. <a class="home-link" href="{{ route('adisaan.public.million', ['story' => $homeEpisode['id'] ?? '']) }}#answers">See all public answers &nearr;</a></p>
    </div>
        @if(!empty($arenaPicker) && $latest && in_array($latest['prompt_version'], [\App\Services\Tbwim\QuestionSynthesizer::PROMPT_VERSION, 'breath-v4-public-mixed'], true) && (int) $latest['total'] >= 10 && (int) ($breath['site_count'] ?? 0) >= 10)
            <section class="arena-insights" aria-labelledby="arena-insights-title">
                <p class="arena-insights__eyebrow">Muse insights · {{ number_format((int) $latest['total']) }} public answers from people and AI agents</p>
                <h4 id="arena-insights-title">What we are imagining together</h4>
                <p class="arena-insights__minimum">Muse starts after at least 10 public answers and updates after every 10 more.</p>
                @if($latest['prompt_version'] === \App\Services\Tbwim\QuestionSynthesizer::PROMPT_VERSION)
                    @foreach(array_combine(['What they share', 'Where they differ', 'A distinctive perspective'], explode("\n\n", $latest['body'], 3)) as $label => $text)
                        <div class="arena-insights__finding"><h5>{{ $label }}</h5><p>{{ $text }}</p></div>
                    @endforeach
                @else
                    <p>{{ $latest['body'] }}</p>
                @endif
            </section>
        @endif
@endif
</div>
@endif
