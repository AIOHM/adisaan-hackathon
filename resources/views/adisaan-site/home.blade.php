@extends('adisaan-site.layout')
@php
    // Title: brand, the current question, and the person's name while it still fits about 60 characters.
    $pageTitle = 'AdiSaan is BACK: ' . ($homeEpisode['lead'] ?? 'One honest question at a time');
    if (!empty($home['title_suffix']) && mb_strlen($pageTitle . ' · ' . $home['title_suffix']) <= 62) {
        $pageTitle .= ' · ' . $home['title_suffix'];
    }
@endphp
@section('title', $pageTitle)
@section('description', $home['meta_description'] ?? $home['subtitle'] ?? 'AdiSaan is an animated avatar. One honest question at a time. Real people answer.')
{{-- The dealt card's rules live with the deck on /question. Loaded rather
     than copied, so the two cards cannot drift apart. --}}
@push('styles')
<link rel="stylesheet" href="/assets/adisaan/site/question-answer-countdown.css?v=20261026-pillars">
@endpush
@push('styles')
<style>
.home-episode__preview{position:relative;width:min(100%,860px);margin:0 auto;aspect-ratio:16/9;overflow:hidden;border:1px solid #e9c77599;border-radius:18px;background:#021503;box-shadow:0 0 0 1px #e9c77544,0 0 36px #d6b25e5c,0 24px 62px #001b0655}
.home-episode__poster{display:block;width:100%;height:100%;object-fit:cover}
.home-episode__audio{display:none}
.home-episode__sound{position:absolute;top:clamp(12px,2vw,22px);right:clamp(12px,2vw,22px);display:grid;place-items:center;width:48px;height:48px;border:1px solid #e9c775;border-radius:50%;background:#073209ef;color:#f4f0e8;box-shadow:0 6px 20px #00140488;cursor:pointer;transition:background .18s ease,transform .18s ease}
.home-episode__sound:hover{background:#174b20;transform:scale(1.06)}
.home-episode__sound svg{width:23px;height:23px}
.home-episode__sound:focus-visible{outline:3px solid #f4f0e8;outline-offset:3px}
.home-episode__sound[aria-pressed="true"]{background:#d6b25e;color:#073209}
.home-episode__sound svg[hidden]{display:none}
@media(max-width:700px){.home-episode__preview{border-radius:12px}.home-episode__sound{width:44px;height:44px;top:10px;right:10px}}
@media(prefers-reduced-motion:reduce){.home-episode__sound{transition:none}.home-episode__sound:hover{transform:none}}
</style>
@endpush
@section('content')
@php
    /*
     * Phase 1 (2026-10-19): the five-screen homepage.
     * 1. the question (hero, in the layout) with the episode video
     * 2. real answers from echo_submissions
     * 3. who is asking
     * 4. the journey: the measured count, the ten accounts, the next milestone
     * 5. join: one email form with consent
     * Words come from config('adisaan_public.home'); the episode from `home.episode_ref`.
     * No levels, XP, quests, scores, sparks or emoji here. No price, no booking.
     */
    $who = $home['who'] ?? [];
    $journey = $home['journey'] ?? [];
    $join = $home['join'] ?? [];
    $accounts = array_values(array_filter($top10Virality ?? [], static fn (array $item): bool => !empty($item['profile_url'])));
    $platformOrder = ['facebook' => 0, 'instagram' => 1, 'youtube' => 2, 'tiktok' => 3, 'bluesky' => 4, 'linkedin' => 5];
    usort($accounts, static fn (array $a, array $b): int => ($platformOrder[strtolower((string) ($a['platform'] ?? ''))] ?? 99) <=> ($platformOrder[strtolower((string) ($b['platform'] ?? ''))] ?? 99));
    $accountColumns = array_chunk($accounts, max(1, (int) ceil(count($accounts) / 2)));
    $followersTotal = $board['total_followers'] ?? null;
    $dateFormat = $home['date_format'] ?? 'j M Y';
    $countedOn = !empty($board['last_synced_at']) ? \Illuminate\Support\Carbon::parse($board['last_synced_at'])->format($dateFormat) : null;
    $accountCount = count($board['accounts'] ?? []);
    $nextMilestone = (int) ($journey['next_milestone'] ?? 10000);
@endphp

{{-- 1 · the episode (the question itself is the hero lead line, in the layout) --}}
<section class="home-section home-section--dark home-episode" id="episode" aria-labelledby="episode-heading">
    <div class="container reveal">
        @include('adisaan-site.partials.living-map-key')
        <p class="home-episode__tag">{{ $home['episode']['hashtag'] ?? '#TBWIM' }}</p>
        <p class="home-episode__eyebrow">{{ $home['episode']['eyebrow'] ?? '· THE QUESTION BEHIND EVERY QUESTION ·' }}</p>
        <h2 id="episode-heading">{{ $home['episode']['title'] ?? 'Watch first. Then answer.' }}</h2>
        <figure class="home-episode__preview">
            <img class="home-episode__poster" src="{{ $home['episode']['intro_poster'] }}" alt="AdiSaan points toward the question: What is the best way you imagine?" width="1920" height="1080" fetchpriority="high" decoding="async">
            <audio class="home-episode__audio" data-home-narration preload="none" src="{{ $home['episode']['narration_audio'] }}">Your browser does not support audio playback.</audio>
            <button class="home-episode__sound" type="button" data-home-narration-toggle aria-label="Play narration" aria-pressed="false">
                <svg data-icon-muted viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M11 5 6 9H3v6h3l5 4V5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m16 9 5 6m0-6-5 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <svg data-icon-sound viewBox="0 0 24 24" aria-hidden="true" focusable="false" hidden><path d="M11 5 6 9H3v6h3l5 4V5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M15.5 8.5a5 5 0 0 1 0 7m3-10a9 9 0 0 1 0 13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </figure>
        <div class="home-episode__daily">
            <p>{{ $home['episode']['daily_label'] }}</p>
            @php
                // Where this question sits in the deck, so the card carries the
                // same numeral it has on /question.
                $deck = collect($stories ?? [])->pluck('id')->values();
                $place = $deck->search($homeEpisode['id'] ?? null);
                $numeral = ['I','II','III','IV','V','VI','VII','VIII','IX','X'][$place === false ? 0 : $place] ?? 'I';
            @endphp
            @include('adisaan-site.partials.question-card', [
                'story' => $homeEpisode,
                'numeral' => $numeral,
                'areas' => $lifeAreas ?? [],
                'count' => ($questionCounts[$homeEpisode['id'] ?? ''] ?? []),
                'siteTotal' => $answersTotal ?? 0,
                'goal' => $answerGoal ?? 1000000,
                'modifier' => 'qcard--wide',
            ])
            <div class="home-episode__act">
                <a class="btn-primary" href="{{ $homeEpisode['answer_url'] ?? route('adisaan.public.answer') }}">{{ $home['episode']['answer_label'] }} <span aria-hidden="true">↗</span></a>
                <a class="home-link" href="{{ route('adisaan.public.question') }}#building">{{ $home['episode']['all_label'] }}</a>
            </div>
        </div>
        @include('adisaan-site.partials.question-loop', ['loop' => $loop ?? null])
        @include('adisaan-site.partials.breathing-answer', ['breath' => $breath ?? null])
    </div>
</section>

{{-- 3 · who is asking --}}
<section class="home-section home-section--paper home-who" id="who" aria-labelledby="who-heading">
    <div class="container reveal">
        <div class="home-who__grid">
            {{-- Adrian, not the avatar. This section is the one place on the
                 site that answers "who is behind this", and answering it with
                 the animated character would dodge the question. --}}
            <img class="home-who__portrait"
                 src="/assets/adisaan/site/adrian-aparaschivei.png"
                 alt="Adrian Aparaschivei" width="376" height="376" loading="lazy" decoding="async">
            <div class="home-who__copy">
                <h2 id="who-heading">{{ $who['title'] ?? 'Who is asking' }}</h2>
                <p class="home-who__text">{{ $who['paragraph'] ?? '' }}</p>
            </div>
        </div>
    </div>
</section>

@php $why = $home['why'] ?? []; @endphp
<section class="home-section home-section--dark home-why" id="why" aria-labelledby="why-heading">
    <div class="container home-why__grid{{ empty($why['photo']) ? ' home-why__grid--text-only' : '' }} reveal">
        <div class="home-why__story">
            <h2 id="why-heading">{{ $why['title'] ?? 'What’s next?' }}</h2>
            <p class="home-why__lead">{{ $why['lead'] ?? '' }}</p>
            <p>{{ $why['body'] ?? '' }}</p>
        </div>
        @if(!empty($why['photo']))
            <figure class="home-why__photo">
                <img src="{{ $why['photo'] }}" alt="{{ $why['photo_alt'] ?? '' }}" loading="lazy" decoding="async" width="{{ $why['photo_width'] ?? 972 }}" height="{{ $why['photo_height'] ?? 1296 }}">
                @if(!empty($why['photo_credit']))<figcaption>{{ $why['photo_credit'] }}</figcaption>@endif
            </figure>
        @endif
    </div>
</section>

{{-- 4 · the journey --}}
<section class="home-section home-section--dark home-journey" id="journey" aria-labelledby="journey-heading" data-total-preview>
    <div class="container reveal">
        <h2 id="journey-heading">{{ $journey['title'] ?? 'The road to one million.' }}</h2>
        @if($followersTotal === null)
            <p class="home-journey__count">{{ $journey['count_missing'] ?? 'No measured count yet.' }}</p>
        @else
            <p class="home-journey__count"><strong data-followers>{{ number_format((int) $followersTotal) }}</strong> {{ str_replace([':accounts', ':date'], [$accountCount, $countedOn ?? 'a date AIOHM Social has not reported'], $journey['count_line'] ?? 'people follow AdiSaan across :accounts accounts, counted on :date.') }}</p>
        @endif
        <p class="home-journey__next">{{ str_replace(':milestone', number_format($nextMilestone), $journey['next_line'] ?? 'Next stop: :milestone.') }}</p>
        <div class="home-journey__columns">
            @foreach($accountColumns as $column)
            <ul class="home-journey__list">
                @foreach($column as $item)
                <li class="reveal">
                    <span class="home-journey__who"><span class="home-journey__platform">{{ $item['platform_label'] ?? $item['platform'] }}</span><span class="home-journey__handle">{{ stripos((string) ($item['handle'] ?? ''), 'adrian') !== false ? 'AdiSaan' : ($item['handle'] !== '' ? '@'.ltrim((string) $item['handle'], '@') : ($item['name'] ?? 'Channel')) }}</span></span>
                    <span class="home-journey__n">@if(!empty($item['measured'])){{ number_format((int) $item['followers']) }} follow @else not reporting @endif</span>
                    <a class="home-journey__follow" href="{{ $item['profile_url'] }}" target="_blank" rel="noopener noreferrer" data-follow-channel="{{ $item['platform'] }}:{{ $item['handle'] }}">{{ $journey['follow_label'] ?? 'Follow' }}</a>
                </li>
                @endforeach
            </ul>
            @endforeach
        </div>
        <p class="home-journey__note">{{ $journey['note'] ?? '' }} <a class="home-link" href="{{ route('adisaan.public.million') }}">{{ $journey['race_label'] ?? 'See the whole race' }}</a></p>
    </div>
</section>

{{-- 4a · partners. The same cream card as "who is asking", because this
     is the other half of that answer: who is asking, and who is helping. --}}
@php
    // The list the /journey road draws from. One source, two drawings.
    $partnerSeats = array_values(array_filter(
        (array) (config('adisaan_public.journey_path.steps') ?? []),
        static fn (array $step): bool => ($step['reached'] ?? false) === true,
    ));
@endphp
@if($partnerSeats !== [])
<section class="home-section home-section--paper home-who home-partners" id="partners" aria-labelledby="partners-heading">
    <div class="container reveal">
        <h2 id="partners-heading">Partners</h2>
        <p class="home-who__text">Two organisations took a seat before there was anything to show them. The study is built with their help, and the record should say so.</p>
        <ul class="partner-row">
            @foreach($partnerSeats as $partner)
                <li class="partner">
                    @if(!empty($partner['badge']))
                        {{-- The ShareAI for Startups badge. Their page requires
                             the link to the programme kept intact, the supplied
                             420x120 aspect ratio, and no recolouring, cropping
                             or animation. Adrian confirmed acceptance into the
                             programme on 2026-10-25. --}}
                        <a class="partner__badge-link" href="{{ $partner['badge_href'] }}" target="_blank" rel="noopener">
                            <img class="partner__badge" src="{{ $partner['badge'] }}" width="420" height="120" alt="{{ $partner['badge_alt'] ?? 'ShareAI for Startups participant' }}" loading="lazy" decoding="async">
                        </a>
                    @elseif(!empty($partner['logo']))
                        <img class="partner__logo" src="{{ $partner['logo'] }}" alt="{{ $partner['logo_alt'] ?? $partner['name'] }}" loading="lazy" decoding="async">
                        <span class="partner__role">{{ $partner['status'] ?? 'Partner' }}</span>
                    @else
                        {{-- No logo file for this one yet; the name in our own
                             type is better than an invented mark. --}}
                        <span class="partner__wordmark">{{ $partner['name'] }}</span>
                        <span class="partner__role">{{ $partner['status'] ?? 'Partner' }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

{{-- 4b · questions people ask (SEO audit 2026-10-20: FAQ content, deeper headings, FAQPage schema) --}}
@php $faq = $home['faq'] ?? []; @endphp
@if(!empty($faq['items']))
<section class="home-section home-section--dark home-faq" id="faq" aria-labelledby="faq-heading">
    <div class="container reveal">
        <h2 id="faq-heading">{{ $faq['title'] ?? 'Questions people ask.' }}</h2>
        @php $faqColumns = array_chunk($faq['items'], max(1, (int) ceil(count($faq['items']) / 2))); @endphp
        <div class="home-faq__columns">
            @foreach($faqColumns as $column)
            <div class="home-faq__list">
            @foreach($column as $item)
            <details class="home-faq__item">
                <summary>{{ $item['q'] }}</summary>
                <p>{{ $item['a'] }}</p>
            </details>
            @endforeach
            </div>
            @endforeach
        </div>
    </div>
</section>
@push('jsonld')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(static fn (array $item): array => [
        '@type' => 'Question',
        'name' => $item['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
    ], $faq['items']),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@if(!empty($homeEpisode['video']) && !empty($homeEpisode['published_on']))
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'VideoObject',
    'name' => $homeEpisode['title'] ?? $homeEpisode['lead'],
    'description' => $homeEpisode['question'] ?? $homeEpisode['lead'],
    'thumbnailUrl' => $publicUrl.($homeEpisode['poster'] ?? '/assets/adisaan/site/story-poster.webp'),
    'contentUrl' => $publicUrl.$homeEpisode['video'],
    'uploadDate' => $homeEpisode['published_on'],
    'inLanguage' => $lang ?? 'en',
    'author' => ['@id' => $publicUrl.'/#adisaan'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
@endpush
@endif

{{-- 5 · join --}}
<section class="home-section home-section--light home-join" id="join" aria-labelledby="join-heading">
    <div class="container home-join__grid reveal">
        <div class="home-join__content">
            <div class="home-join__heading">
                <span class="home-join__mail" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none" focusable="false"><rect x="5" y="11" width="38" height="27" rx="5" stroke="currentColor" stroke-width="2"/><path d="m7 14 17 13 17-13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <h2 id="join-heading">{{ $join['title'] ?? 'One email when a new episode is out.' }}</h2>
            </div>
            @if(!empty($join['line']))<p class="home-lead">{{ $join['line'] }}</p>@endif
            <form action="{{ route('adisaan.public.construction.lead') }}" method="post" class="home-join__form">
                @csrf
                <label for="join-email" class="home-join__label visually-hidden">{{ $join['label'] ?? 'Your email' }}</label>
                <div class="home-join__row">
                    <input id="join-email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@example.com">
                    <button type="submit" class="btn-primary">{{ $join['button'] ?? 'Subscribe' }}</button>
                </div>
                <label class="checkbox home-join__consent">
                    <input type="checkbox" name="consent_updates" value="1" required @checked(old('consent_updates'))>
                    <span>{{ $join['consent'] ?? '' }} <a href="{{ route('adisaan.public.privacy') }}">{{ $join['privacy_label'] ?? 'Privacy' }}</a>.</span>
                </label>
                @if(session('lead_success'))
                    <p class="home-status" role="status" data-analytics-event="email_joined">{{ session('lead_success') }}</p>
                @endif
                @if($errors->any())
                    <p class="home-error" role="alert">{{ $errors->first() }}</p>
                @endif
            </form>
        </div>
        <div class="home-join__conversation" data-companion-join-dock aria-label="Talk with AdiSaan"></div>
    </div>
</section>
@endsection
