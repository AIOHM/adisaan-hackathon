@php
    // Only the about page renders the full hero; every other page gets
    // the compact header (wordmark + the menu row).
    $compactHeader = ($active ?? '') !== 'about';
    $followersTotal = isset($board['total_followers']) && $board['total_followers'] !== null ? number_format((int) $board['total_followers']) : null;
    $countedOn = !empty($board['last_synced_at']) ? \Illuminate\Support\Carbon::parse($board['last_synced_at'])->format($home['date_format'] ?? 'j M Y') : null;
    $countWords = ($home['count_words'] ?? null) ?: ['none' => 'No measured count yet', 'follow' => 'follow so far', 'counted' => 'counted'];
@endphp
<!doctype html>
<html lang="{{ $lang ?? 'en' }}">
<head>
    <!-- GA4 consent defaults stay denied. Returning visitors who opted in can be
         measured from the first page view; everyone else remains cookieless. -->
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('consent', 'default', {
        ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied',
        analytics_storage: 'denied', functionality_storage: 'denied', personalization_storage: 'denied',
        security_storage: 'granted'
      });
      try {
        if (JSON.parse(localStorage.getItem('adisaan_analytics_choice_v1') || 'null')?.analytics === true) {
          gtag('consent', 'update', { analytics_storage: 'granted' });
        }
      } catch (_) {}
      gtag('js', new Date());
      gtag('config', 'G-D1ZDJJ0EY6', {
        anonymize_ip: true,
        allow_google_signals: false,
        allow_ad_personalization_signals: false,
        send_page_view: true
      });
      // SEO audit 2026-10-20 ("reduce unused JavaScript"): gtag.js loads after the page has
      // painted, not in the critical path. The queued page view goes out as soon as it arrives.
      window.addEventListener('load', function () {
        var s = document.createElement('script'); s.async = true;
        s.src = 'https://www.googletagmanager.com/gtag/js?id=G-D1ZDJJ0EY6'; document.head.appendChild(s);
      });
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AdiSaan — Your online partner')</title>
    <meta name="description" content="@yield('description', 'A morning question. An evening perspective. Join AdiSaan on a real journey toward one million follows.')">
    <meta name="theme-color" content="#073209">
    <link rel="canonical" href="{{ $publicUrl }}{{ $canonicalPath }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', 'AdiSaan — Your online partner')">
    <meta property="og:description" content="A real story. A question for your imagination. A journey we shape together.">
    <meta property="og:url" content="{{ $publicUrl }}{{ $canonicalPath }}">
    <meta property="og:image" content="{{ $publicUrl }}/assets/adisaan/site/header-art-v1-share.jpg">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="AdiSaan, the animated avatar, in front of a green world map">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="/assets/adisaan/site/wordmark.svg" type="image/svg+xml">
    <link rel="preload" href="/assets/adisaan/site/montserrat-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/adisaan/site/montserrat-700.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/adisaan/site/site.css?v=20261031-atlas-once">
    @stack('styles')
    <noscript><style>.hero__plan{opacity:1;transform:rotate(-3deg) scale(1)}</style></noscript>
    @if($active === 'journey')
        <link rel="preload" as="image" href="/assets/adisaan/site/header-1600.webp" imagesrcset="/assets/adisaan/site/header-800.webp 800w, /assets/adisaan/site/header-1600.webp 1600w, /assets/adisaan/site/header-2000.webp 2000w" imagesizes="100vw">
    @endif
    @unless($compactHeader)
        <link rel="preload" as="image" href="/assets/adisaan/site/avatar-cutout-800.webp" imagesrcset="/assets/adisaan/site/avatar-cutout-600.webp 600w, /assets/adisaan/site/avatar-cutout-800.webp 800w, /assets/adisaan/site/avatar-cutout-1084.webp 1084w" imagesizes="(max-width: 700px) 100vw, 46vw">
    @endunless
    @php
        // schema.org (SEO audit 2026-10-20): the person behind the site and the site itself, on every page.
        $schemaPerson = [
            '@type' => 'Person',
            '@id' => $publicUrl.'/#adisaan',
            'name' => 'AdiSaan',
            'alternateName' => 'AdiSaan',
            'url' => $publicUrl.'/',
            'image' => $publicUrl.'/assets/adisaan/site/avatar-cutout-800.webp',
            'jobTitle' => 'Founder',
            'worksFor' => ['@type' => 'Organization', 'name' => 'AIOHM', 'url' => 'https://aiohm.org/'],
            'sameAs' => $socialProfiles ?? [],
        ];
        $schemaSite = [
            '@type' => 'WebSite',
            '@id' => $publicUrl.'/#website',
            'name' => 'AdiSaan',
            'alternateName' => 'AdiSaan is BACK',
            'url' => $publicUrl.'/',
            'inLanguage' => $lang ?? 'en',
            'author' => ['@id' => $publicUrl.'/#adisaan'],
        ];
        // The graph is assembled here, inside @php, on purpose (2026-10-26).
        // Blade has a `@context` directive, and it compiles that word wherever
        // it appears in the template text, including inside an echo. Written
        // in a `{!! !!}` echo, the literal '@context' key came out on the live
        // site as leaked PHP (the directive's compiled argument setup), which made the
        // JSON-LD invalid and every crawler discard it. A @php block is stored
        // raw before directives are compiled, so the key survives here.
        $schemaGraph = json_encode(
            ['@context' => 'https://schema.org', '@graph' => [$schemaPerson, $schemaSite]],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    @endphp
    <script type="application/ld+json">{!! $schemaGraph !!}</script>
    {{-- Where a machine finds the current question and how to answer it.
         One head link on every page, because an agent that lands anywhere on
         the site should not have to reach /answer to discover the endpoint. --}}
    <link rel="alternate" type="application/json" href="{{ $publicUrl }}/api/agent/answer" title="AdiSaan agent answer: the current question and how to submit a perspective">
    <link rel="llms-txt" type="text/plain" href="{{ $publicUrl }}/llms.txt">
    @stack('jsonld')
    @if($active === 'journey')
        <link rel="stylesheet" href="/css/public-story-carousel.css?v=20261014-2">
        <script src="/js/public-story-carousel.js?v=20261014-2" defer></script>
    @endif
    <script src="/assets/adisaan/site/site.js?v=20261026-chart-windows" defer></script>
    <script src="/assets/adisaan/site/analytics-consent.js?v=20261024-1" defer></script>
</head>
<body class="@if(in_array($active ?? '', ['about', 'manifesto'], true)) manifesto-page @endif @if(($active ?? '') === 'journey') journey-page @endif @if(($active ?? '') === 'question') question-template @endif @if(($active ?? '') === 'answer') answer-template @endif @if(isset($livingMap)) home-living-map @endif" data-public-url="{{ $publicUrl }}" data-public-path="{{ $canonicalPath }}" data-board-url="{{ route('adisaan.public.board') }}">
<a class="skip-link" href="#main">Skip to the story</a>
@if(isset($livingMap))
<div class="living-map" aria-hidden="true">
    <div class="living-map__atlas">
        @foreach($livingMap['visitors'] as $point)
            <span class="living-map__point living-map__point--activity" style="left:{{ $point['x'] }}%;top:{{ $point['y'] }}%;--point-size:{{ min(58, 18 + sqrt($point['count']) * 9) }}px"></span>
        @endforeach
        @foreach($livingMap['answers'] as $point)
            <span class="living-map__point living-map__point--answer" style="left:{{ $point['x'] }}%;top:{{ $point['y'] }}%;--point-size:{{ min(56, 18 + sqrt($point['count']) * 10) }}px"></span>
        @endforeach
    </div>
</div>
@endif
@if($compactHeader)
<div class="public-header--compact">
<header class="brand-header brand-header--slim">
    <div class="container brand-header__slim-row">
        <a href="{{ route('adisaan.public.home') }}" class="brand-header__wordmark" aria-label="AdiSaan home">
            <img src="/assets/adisaan/site/logo-en.svg" width="5107" height="1509" alt="AdiSaan — Your online partner">
        </a>
@else
<header class="brand-header brand-header--building">
        <div class="hero__scene" aria-hidden="true">
            <svg class="hero__gold-lines" viewBox="0 0 1600 700" preserveAspectRatio="none" focusable="false">
                <path d="M-80 442 C 220 480, 330 655, 610 602 S 1040 436, 1320 540 S 1540 580, 1680 484" />
                <path d="M-80 506 C 180 493, 338 673, 638 625 S 1030 482, 1290 568 S 1540 600, 1680 523" />
                <path d="M-80 560 C 220 526, 430 683, 716 633 S 1090 528, 1360 596 S 1540 630, 1680 565" />
            </svg>
            <img class="hero__avatar" src="/assets/adisaan/site/avatar-cutout-800.webp" srcset="/assets/adisaan/site/avatar-cutout-600.webp 600w, /assets/adisaan/site/avatar-cutout-800.webp 800w, /assets/adisaan/site/avatar-cutout-1084.webp 1084w" sizes="(max-width: 700px) 100vw, 46vw" width="1084" height="1451" alt="AdiSaan, the animated avatar" fetchpriority="high" decoding="async">
        </div>
    <div class="utility container">
        <span>{{ $home['tagline'] ?? 'A. Adrian’s avatar. His almost real story.' }}</span>
        <a class="utility__count" href="{{ route('adisaan.public.home') }}" title="See the progress so far">@if($followersTotal === null){{ $countWords['none'] }}@else<span>{{ $followersTotal }} {{ $countWords['follow'] }}</span>@if($countedOn)<small>{{ $countWords['counted'] }} {{ $countedOn }}</small>@endif @endif</a>
    </div>
    <div class="hero">
        <div class="container hero__inner">
            <div class="hero__copy">
                    <h1 class="hero__brandline" aria-label="AdiSaan is BACK with a plan">
                        <a href="{{ route('adisaan.public.home') }}" class="hero__logo" aria-label="AdiSaan home">
                            <img src="/assets/adisaan/site/logo-en.svg" width="5107" height="1509" alt="AdiSaan — Your online partner">
                        </a>
                        <span class="hero__return-line"><span class="hero__is">is</span><span class="hero__back">BACK<a href="{{ route('adisaan.public.manifesto') }}" class="hero__plan" aria-label="with a plan — read the manifesto">with a plan...</a></span></span>
                    </h1>
                <div class="hero__support">
                    {{-- 2026-10-20 (Adrian): one intro line, then the question of the day in a thought bubble, then the two actions.
                         No language link: the Romanian site gets its own domain later. The question follows the current
                         story (config home.episode_ref, or the newest story whose opens_at has passed). --}}
                    <p class="hero__intro">{{ $home['subtitle'] ?? '' }}</p>
                    <a class="hero__thought" href="{{ $homeEpisode['answer_url'] ?? route('adisaan.public.answer') }}">
                        <span class="hero__thought-label">{{ $home['thought_label'] ?? 'Today’s question' }}</span>
                        <span class="hero__thought-text">{{ $homeEpisode['lead'] ?? config('adisaan_public.story.question') }}</span>
                    </a>
                    <div class="hero__actions-row">
                        <a class="btn-primary" href="{{ $homeEpisode['answer_url'] ?? route('adisaan.public.answer') }}">{{ $home['answer_label'] ?? 'Answer this' }}</a>
                        <a class="hero__follow" href="#journey">{{ $home['follow_label'] ?? 'Follow AdiSaan' }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
@endif
<nav class="tabs" aria-label="The AdiSaan journey">
    <div class="@unless($compactHeader)container @endunless tabs__inner">
        <a href="{{ route('adisaan.public.question') }}" @if($active === 'question') aria-current="page" @endif>Question</a>
        <a href="{{ route('adisaan.public.answer') }}" @if($active === 'answer') aria-current="page" @endif>Answer</a>
        <a href="{{ route('adisaan.public.arena') }}" @if($active === 'arena') aria-current="page" @endif>Arena</a>
        <a href="{{ route('adisaan.public.about') }}" @if($active === 'about') aria-current="page" @endif>About</a>
        <a href="{{ route('adisaan.public.manifesto') }}" @if($active === 'manifesto') aria-current="page" @endif>Manifesto</a>
        <a href="{{ route('adisaan.public.news') }}" @if($active === 'news') aria-current="page" @endif>News</a>
        <button class="tabs__share" type="button" data-share aria-label="Share the AdiSaan journey">Share <span aria-hidden="true">↗</span></button>
    </div>
</nav>
@if($compactHeader)
        {{-- No follower count in the compact header (Adrian, 2026-10-26): the
             menu sits on the logo's row, aligned right. The measured count
             and its date live on the journey page. --}}
    </div>
</header>
</div>
@endif
<main id="main">@yield('content')</main>
<footer class="footer footer--compact">
    <div class="container footer__bottom">
        <span>© {{ now()->year }} AdiSaan</span>
        <span>Supported by <a href="https://ohm.events/">OHM Events</a> · Built with <a href="https://aiohm.org/">AIOHM</a></span>
        <span><a href="{{ route('adisaan.public.manifesto') }}">Manifesto</a> · <a href="{{ route('adisaan.public.million') }}#answers">Members&rsquo; answers</a> · <a href="{{ route('adisaan.public.privacy') }}">Privacy</a> · <button type="button" class="footer__consent-link" data-analytics-preferences>Analytics choice</button> · <a href="{{ $adminUrl }}">My home ↗</a></span>
    </div>
</footer>
<div class="toast" role="status" aria-live="polite" hidden></div>
@include('adisaan-site.partials.analytics-consent')
@include('adisaan-site.partials.ambassador-companion')
<dialog class="manifesto-exit" aria-labelledby="manifesto-exit-title" aria-describedby="manifesto-exit-description">
    <button class="manifesto-exit__close" type="button" aria-label="Close">×</button>
    <p class="manifesto-exit__label">A thought before you go</p>
    <h2 id="manifesto-exit-title">Every question opens a new way forward.</h2>
    <p id="manifesto-exit-description">Discover the idea behind AdiSaan and the journey we are building together.</p>
    <a href="{{ route('adisaan.public.manifesto') }}">Read the manifesto <span aria-hidden="true">↗</span></a>
    <button type="button" class="manifesto-exit__later">Maybe later</button>
</dialog>
<script src="/assets/adisaan/site/manifesto-exit.js?v=20261030" defer></script>
</body>
</html>
