@extends('adisaan-site.layout')
@section('title', __('site.ui.news_title'))
@section('description', __('site.ui.news_description'))
@push('styles')
<link rel="stylesheet" href="/assets/adisaan/site/news-dispatch.css?v=20261031-news">
@endpush
@section('content')
<div class="news-portal">
    <!-- 1. EDITORIAL MASTHEAD -->
    <header class="news-masthead">
        <div class="container">
            <div class="news-masthead__meta">
                <span class="news-live-tag">{{ __('site.ui.live_desk') }}</span>
                <span>{{ now()->translatedFormat('l, d F Y') }}</span>
                <span>{{ number_format($counts['total']) }} {{ __('site.ui.signals_tracked') }}</span>
            </div>

            <div class="news-masthead__hero">
                <div class="news-masthead__title-group">
                    <h1>{{ __('site.ui.dispatch_name') }}</h1>
                    <p>{{ __('site.ui.news_description') }}</p>
                </div>

                <form class="news-search-form" method="GET" action="{{ route('adisaan.public.news') }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="q" value="{{ $searchQuery }}" placeholder="{{ __('site.ui.news_search_placeholder') }}" aria-label="{{ __('site.ui.search_news') }}">
                    @if($searchQuery !== '')
                        <a class="news-search-clear" href="{{ route('adisaan.public.news', $activeCategory !== '' ? ['category' => $activeCategory] : []) }}" aria-label="{{ __('site.ui.clear_search') }}">×</a>
                    @endif
                    <button type="submit" aria-label="{{ __('site.ui.submit_search') }}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- 2. CATEGORY FILTER RIBBON -->
    <nav class="news-categories-nav" aria-label="{{ __('site.ui.filter_aria') }}">
        <div class="container">
            <div class="news-categories-scroll">
                <a href="{{ route('adisaan.public.news') }}" class="news-pill {{ $activeCategory === '' ? 'is-active' : '' }}">
                   {{ __('site.ui.all_signals') }} <span class="news-pill__count">{{ number_format($counts['total']) }}</span>
                </a>
                @foreach($categories as $slug => $category)
                <a href="{{ route('adisaan.public.news', ['category' => $slug]) }}" class="news-pill {{ $activeCategory === $slug ? 'is-active' : '' }}">
                   {{ $category['icon'] ?? '' }} {{ $category['label'] }} <span class="news-pill__count">{{ number_format($counts['per'][$slug] ?? 0) }}</span>
                </a>
                @endforeach
            </div>
        </div>
    </nav>

    <div class="container">
        @if($signalBrief)
        <!-- 2b. THE AVATAR'S BRIEF — tomorrow's question, suggested by today's news -->
        <section class="signal-brief" aria-labelledby="signal-brief-title">
            <div class="signal-brief__stage">
                <img class="signal-brief__avatar" data-brief-avatar src="/assets/adisaan/site/avatar-cutout-800.webp" srcset="/assets/adisaan/site/avatar-cutout-600.webp 600w, /assets/adisaan/site/avatar-cutout-800.webp 800w, /assets/adisaan/site/avatar-cutout-1084.webp 1084w" sizes="(max-width: 700px) 40vw, 220px" width="1084" height="1451" alt="AdiSaan, the animated avatar" loading="lazy" decoding="async">
            </div>
            <div class="signal-brief__body">
                <p class="signal-brief__eyebrow">{!! __('site.ui.brief_eyebrow') !!}</p>
                <h2 id="signal-brief-title">{{ $signalBrief->question }}</h2>
                <p class="signal-brief__meta">
                    {{ __('site.ui.suggested_by') }} <a href="{{ $signalBrief->signal_url }}" rel="noopener">{{ $signalBrief->signal_title }}</a>
                    @if($signalBrief->signal_source) · {{ $signalBrief->signal_source }} @endif
                    · {{ $signalBrief->scheduled_for?->isTomorrow() ? __('site.ui.opens_tomorrow') : ($signalBrief->scheduled_for?->isToday() ? __('site.ui.open_now') : __('site.ui.opens_on', ['date' => $signalBrief->scheduled_for?->translatedFormat('j F')])) }}
                </p>
                <p class="signal-brief__caption" data-brief-caption aria-live="polite">{{ __('site.ui.brief_caption') }}</p>
                <p class="signal-brief__script" data-brief-script>{{ $signalBrief->brief_script }}</p>
                @if($signalBrief->brief_audio_path)
                <audio data-brief-audio src="{{ $signalBrief->brief_audio_path }}" preload="metadata"></audio>
                @endif
                <div class="signal-brief__controls">
                    <button type="button" class="signal-brief__play" data-brief-play>{{ $signalBrief->brief_audio_path ? __('site.ui.play_brief') : __('site.ui.read_brief') }}</button>
                    <button type="button" class="signal-brief__stop" data-brief-stop hidden>{{ __('site.ui.stop') }}</button>
                    <span class="signal-brief__progress" data-brief-progress aria-hidden="true"></span>
                </div>
                @unless($signalBrief->brief_audio_path)
                <p class="signal-brief__meta">{{ __('site.ui.brief_voice_note') }}</p>
                @endunless
            </div>
        </section>
        @push('styles')
        <style>
        .signal-brief{display:grid;grid-template-columns:220px 1fr;gap:clamp(20px,4vw,44px);align-items:center;background:#0b3811;border:1px solid #d6b25e88;border-radius:22px;padding:clamp(22px,3.5vw,38px);margin:28px 0 8px;color:#f4f0e8}
        .signal-brief__stage{display:grid;place-items:center}
        .signal-brief__avatar{width:min(100%,220px);height:auto;filter:drop-shadow(0 18px 34px rgba(0,20,4,.5))}
        .signal-brief__avatar.is-speaking{animation:brief-bob 1.6s ease-in-out infinite}
        @keyframes brief-bob{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-6px) scale(1.015)}}
        .signal-brief__eyebrow{color:#e9c775;font:700 11px/1.5 Montserrat,Arial,sans-serif;letter-spacing:.14em;text-transform:uppercase;margin:0 0 10px}
        .signal-brief h2{font-size:clamp(24px,3vw,38px);line-height:1.15;margin:0 0 12px;color:#fff}
        .signal-brief__meta{color:#b8d1b0;font-size:13px;margin:0 0 16px}
        .signal-brief__meta a{color:#e9c775}
        .signal-brief__caption{font-size:17px;line-height:1.6;color:#eef4e6;margin:0 0 18px;min-height:3.2em}
        .signal-brief__script{display:none}
        .signal-brief__controls{display:flex;align-items:center;gap:14px}
        .signal-brief__play,.signal-brief__stop{border:0;border-radius:999px;padding:12px 22px;font:700 14px Montserrat,Arial,sans-serif;cursor:pointer}
        .signal-brief__play{background:#d6b25e;color:#073209}
        .signal-brief__play:hover{background:#e9c775}
        .signal-brief__stop{background:transparent;color:#e9c775;border:1px solid #d6b25e88}
        .signal-brief__progress{color:#b8d1b0;font-size:12px}
        @media(max-width:700px){.signal-brief{grid-template-columns:1fr;text-align:left}.signal-brief__avatar{width:150px}}
        @media(prefers-reduced-motion:reduce){.signal-brief__avatar.is-speaking{animation:none}}
        </style>
        @endpush
        <script>
        (function () {
            var stage = document.currentScript.previousElementSibling;
            var root = stage && stage.classList.contains('signal-brief') ? stage : document.querySelector('.signal-brief');
            if (!root) return;
            var script = root.querySelector('[data-brief-script]').textContent.trim();
            var caption = root.querySelector('[data-brief-caption]');
            var avatar = root.querySelector('[data-brief-avatar]');
            var play = root.querySelector('[data-brief-play]');
            var stop = root.querySelector('[data-brief-stop]');
            var progress = root.querySelector('[data-brief-progress]');
            var sentences = (script.match(/[^.!?]+[.!?]+["']?|\S[^.!?]*$/g) || [script]).map(function (s) { return s.trim(); }).filter(Boolean);
            var audio = root.querySelector('[data-brief-audio]');
            var timer = null, idx = 0, playing = false;
            // Sentence boundaries by share of the whole text, used to caption
            // the recorded audio (no timestamps are stored with the file).
            var bounds = [], acc = 0, totalWords = sentences.join(' ').split(/\s+/).length;
            sentences.forEach(function (s) { acc += s.split(/\s+/).length / totalWords; bounds.push(acc); });
            function show(i) {
                caption.textContent = sentences[i];
                progress.textContent = (i + 1) + ' / ' + sentences.length;
            }
            function end() {
                playing = false; idx = 0;
                if (timer) { clearTimeout(timer); timer = null; }
                if (audio) { audio.pause(); audio.currentTime = 0; }
                avatar.classList.remove('is-speaking');
                play.hidden = false; stop.hidden = true; progress.textContent = '';
                caption.textContent = audio
                    ? @json(__('site.ui.brief_done_audio'))
                    : @json(__('site.ui.brief_done_text'));
            }
            function next() {
                idx++;
                if (idx >= sentences.length) { end(); return; }
                show(idx);
                timer = setTimeout(next, 3000);
            }
            play.addEventListener('click', function () {
                if (playing) return;
                playing = true; idx = 0;
                avatar.classList.add('is-speaking');
                play.hidden = true; stop.hidden = false;
                if (audio) {
                    show(0);
                    audio.currentTime = 0;
                    audio.play();
                } else {
                    show(0);
                    timer = setTimeout(next, 3000);
                }
            });
            if (audio) {
                audio.addEventListener('timeupdate', function () {
                    if (!audio.duration) return;
                    var share = audio.currentTime / audio.duration;
                    var i = bounds.findIndex(function (b) { return share <= b; });
                    if (i === -1) i = sentences.length - 1;
                    if (i !== idx) { idx = i; show(i); }
                });
                audio.addEventListener('ended', end);
            }
            stop.addEventListener('click', end);
        })();
        </script>
        @endif
        <!-- 3. COVER STORY (EDITORIAL LEAD) — Page 1 when not searching -->
        @if($lead !== null)
            <section class="news-lead-section">
                <article class="news-lead-card">
                    <div class="news-lead-card__media">
                        @if(!empty($lead['image']))
                        <img src="{{ $lead['image'] }}" alt="{{ $lead['title'] }}" fetchpriority="high" onerror="this.closest('.news-lead-card__media')?.remove()">
                        @else
                        <div class="news-fallback-media">
                            <span class="news-fallback-brand">{{ $lead['source'] }}</span>
                            <span class="news-fallback-symbol">{{ $categories[$lead['category']]['icon'] ?? '◈' }}</span>
                            <span class="news-fallback-meta">{{ $lead['category_label'] }}</span>
                        </div>
                        @endif
                        <span class="news-badge">{{ __('site.ui.lead_story') }}</span>
                    </div>

                    <div class="news-lead-card__content">
                        <div class="news-lead-card__meta">
                            <span>{{ $lead['source'] }}</span> •
                            @if($lead['published_at'])<time datetime="{{ $lead['published_at']->toIso8601String() }}">{{ $lead['published_at']->format('d M Y') }}</time>@endif
                            • <span>{{ $lead['category_label'] }}</span>
                        </div>

                        <h2 class="news-lead-card__title">
                            <a href="{{ $lead['link'] }}" target="_blank" rel="noopener noreferrer">{{ $lead['title'] }}</a>
                        </h2>

                        @if($lead['excerpt'] !== '')<p class="news-lead-card__excerpt">{{ $lead['excerpt'] }}</p>@endif

                        <div class="news-lead-card__actions">
                            <a href="{{ $lead['link'] }}" target="_blank" rel="noopener noreferrer" class="btn-primary-news">
                                {{ __('site.ui.read_full_story') }}
                            </a>
                            <button type="button" class="news-page-btn" data-share data-share-title="{{ $lead['title'] }}" data-share-url="{{ $lead['link'] }}">
                                {{ __('site.ui.share_signal') }}
                            </button>
                        </div>
                    </div>
                </article>
            </section>
        @endif

        <!-- 4. VIDEO CHRONICLES SHOWCASE — Page 1 on 'all' or the video category -->
        @if($videos !== [])
            <section class="news-video-strip">
                <div class="news-video-strip__header">
                    <div>
                        <h2>{{ __('site.ui.video_chronicles') }}</h2>
                        <p>{{ __('site.ui.video_chronicles_text') }}</p>
                    </div>
                    <a href="https://www.youtube.com/channel/{{ config('tbwim_news.youtube_channel_id') }}" target="_blank" rel="noopener noreferrer" class="btn-primary-news" style="background: rgba(255,255,255,0.15); border: 1px solid var(--news-gold); font-size: 11px;">
                        {{ __('site.ui.subscribe_yt') }}
                    </a>
                </div>

                <div class="news-video-grid">
                    @foreach($videos as $video)
                        <a href="{{ $video['link'] }}" target="_blank" rel="noopener noreferrer" class="news-video-card">
                            <div class="news-video-card__thumb">
                                <img src="{{ $video['thumb'] }}" alt="{{ $video['title'] }}" loading="lazy" onerror="this.closest('.news-video-card')?.remove()">
                                <div class="news-video-play-btn" aria-hidden="true">▶</div>
                            </div>
                            <div class="news-video-card__body">
                                <div class="news-video-card__meta">
                                    <span>{{ __('site.ui.youtube_episode') }}</span>
                                    @if($video['published'])<span>{{ $video['published']->format('d M') }}</span>@endif
                                </div>
                                <h3 class="news-video-card__title">{{ $video['title'] }}</h3>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- 5. MAIN MAGAZINE LAYOUT (DISPATCH FEED + EDITORIAL SIDEBAR) -->
        <div class="news-magazine-layout">
            <!-- Left Main Column: Story Cards -->
            <div class="news-main-column">
                <div class="news-section-title">
                    <span>
                        @if($searchQuery !== '')
                            {{ __('site.ui.results_for', ['q' => $searchQuery]) }}
                        @elseif($activeCategory !== '' && isset($categories[$activeCategory]))
                            {{ $categories[$activeCategory]['label'] }}
                        @else
                            {{ __('site.ui.latest_dispatches') }}
                        @endif
                    </span>
                    <span>{{ __('site.ui.showing_of', ['range' => $totalItems === 0 ? '0-0' : (($page - 1) * config('tbwim_news.per_page') + 1).'-'.(($page - 1) * config('tbwim_news.per_page') + count($grid)), 'total' => $totalItems]) }}</span>
                </div>

                @if($grid !== [])
                    <div class="news-cards-grid">
                        @foreach($grid as $item)
                            <article class="news-article-card">
                                <div class="news-article-card__media">
                                    @if(!empty($item['image']))
                                    <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}" loading="lazy" onerror="this.closest('.news-article-card__media')?.remove()">
                                    @else
                                    <div class="news-fallback-media">
                                        <span class="news-fallback-brand">{{ $item['source'] }}</span>
                                        <span class="news-fallback-symbol">{{ $categories[$item['category']]['icon'] ?? '◈' }}</span>
                                        <span class="news-fallback-meta">{{ $item['category_label'] }}</span>
                                    </div>
                                    @endif
                                </div>

                                <div class="news-article-card__body">
                                    <div class="news-article-card__meta">
                                        <strong>{{ $item['source'] }}</strong>
                                        @if($item['published_at'])<time datetime="{{ $item['published_at']->toIso8601String() }}">{{ $item['published_at']->format('d M Y') }}</time>@endif
                                    </div>

                                    <h3 class="news-article-card__title">
                                        <a href="{{ $item['link'] }}" target="_blank" rel="noopener noreferrer">{{ $item['title'] }}</a>
                                    </h3>

                                    @if($item['excerpt'] !== '')<p class="news-article-card__excerpt">{{ $item['excerpt'] }}</p>@endif

                                    <div class="news-article-card__footer">
                                        <a href="{{ $item['link'] }}" target="_blank" rel="noopener noreferrer" class="news-article-card__link">
                                            {{ __('site.ui.read_source') }}
                                        </a>
                                        <span class="news-article-card__cat">{{ $item['category_label'] }}</span>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <!-- Editorial Pagination -->
                    @if($totalPages > 1)
                    <nav class="news-pagination-bar" aria-label="{{ __('site.ui.pagination') }}">
                        @if($prevUrl !== null)
                            <a href="{{ $prevUrl }}" class="news-page-btn">{{ __('site.ui.previous') }}</a>
                        @else
                            <span class="news-page-btn" style="opacity: 0.4; cursor: not-allowed;">{{ __('site.ui.previous') }}</span>
                        @endif

                        <span class="news-page-info">
                            {{ __('site.ui.page_of', ['page' => $page, 'pages' => $totalPages]) }}
                        </span>

                        @if($nextUrl !== null)
                            <a href="{{ $nextUrl }}" class="news-page-btn">{{ __('site.ui.next_page') }}</a>
                        @else
                            <span class="news-page-btn" style="opacity: 0.4; cursor: not-allowed;">{{ __('site.ui.next_page') }}</span>
                        @endif
                    </nav>
                    @endif
                @else
                    <div class="news-empty-state">
                        <h3>{{ __('site.ui.no_signals') }}</h3>
                        <p>{{ $searchQuery !== '' ? 'Nothing matches that search. Try fewer words, or clear it to see the full dispatch.' : 'The feeds are refreshing. The latest stories land here as soon as the next fetch completes.' }}</p>
                        @if($searchQuery !== '')
                            <a href="{{ route('adisaan.public.news', $activeCategory !== '' ? ['category' => $activeCategory] : []) }}" class="btn-primary-news">{{ __('site.ui.clear_search_btn') }}</a>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Right Sidebar: Editorial Radar & Signals Desk -->
            <aside class="news-sidebar">
                <!-- Widget 1: Fast Signals (Top Ranked) -->
                @if($fastSignals !== [])
                    <div class="news-widget">
                        <h3 class="news-widget__title">
                            <span>{{ __('site.ui.fast_signals') }}</span>
                        </h3>
                        <ol class="news-ranked-list">
                            @foreach($fastSignals as $rank => $signal)
                                <li class="news-ranked-item">
                                    <span class="news-ranked-num">{{ str_pad((string) ($rank + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                    <div class="news-ranked-content">
                                        <h4><a href="{{ $signal['link'] }}" target="_blank" rel="noopener noreferrer">{{ $signal['title'] }}</a></h4>
                                        <div class="news-ranked-meta">{{ $signal['source'] }}@if($signal['published_at']) • {{ $signal['published_at']->diffForHumans() }}@endif</div>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif

                <!-- Widget 2: The Curator's Lens -->
                <div class="news-widget news-curator-card">
                    <div class="news-curator-head">
                        <img src="/assets/adisaan/site/avatar-cutout.webp" alt="{{ __('site.ui.avatar_alt') }}" class="news-curator-avatar">
                        <div class="news-curator-name">
                            <h4>{{ __('site.ui.curator_lens') }}</h4>
                            <span>{{ __('site.ui.curator_role') }}</span>
                        </div>
                    </div>
                    <p class="news-curator-quote">{{ __('site.ui.curator_quote') }}</p>
                    <a href="{{ route('adisaan.public.million') }}" class="btn-primary-news" style="width: 100%; justify-content: center; font-size: 11px;">
                        {{ __('site.ui.explore_race') }}
                    </a>
                </div>

                <!-- Widget 3: Newsletter / Follow The Build -->
                <div class="news-widget news-subscribe-widget">
                    <h3>{{ __('site.ui.follow_build') }}</h3>
                    <p>{{ __('site.ui.follow_build_text') }}</p>

                    <form class="news-subscribe-form" method="POST" action="{{ route('adisaan.public.construction.lead') }}">
                        @csrf
                        <input type="email" name="email" placeholder="{{ __('site.ui.your_email_address') }}" required aria-label="{{ __('site.ui.email_address') }}">
                        <label class="news-subscribe-consent">
                            <input type="checkbox" name="consent_updates" value="1" checked required>
                            <span>{{ __('site.ui.consent_updates') }}</span>
                        </label>
                        <button type="submit">{{ __('site.ui.get_dispatches') }}</button>
                    </form>
                </div>

                <!-- Widget 4: Live RSS Streams Status -->
                @if($streams !== [])
                <div class="news-widget">
                    <h3 class="news-widget__title">
                        <span>{{ __('site.ui.streams') }}</span>
                    </h3>
                    <div class="news-feed-status-list">
                        @foreach($streams as $stream)
                            <div class="news-feed-status-item">
                                <span class="news-feed-status-name">
                                    <span class="news-feed-status-dot"@if(!$stream['ok']) style="background: #b33927;"@endif></span>
                                    {{ $stream['label'] }}
                                </span>
                                <span class="news-feed-status-badge">{{ $stream['ok'] ? number_format($stream['items']).' '.__('site.ui.items') : __('site.ui.retrying') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </aside>
        </div>
    </div>
</div>
@endsection
