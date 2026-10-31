@extends('adisaan-site.layout')
@section('title', 'The Avatar Lab Dispatch — AdiSaan')
@section('description', 'Live intelligence on AI digital avatars, expressive voice synthesis, video models, and the AdiSaan journey to 1,000,000 followers.')
@push('styles')
<link rel="stylesheet" href="/assets/adisaan/site/news-dispatch.css?v=20261031-news">
@endpush
@section('content')
<div class="news-portal">
    <!-- 1. EDITORIAL MASTHEAD -->
    <header class="news-masthead">
        <div class="container">
            <div class="news-masthead__meta">
                <span class="news-live-tag">Live Intelligence Desk</span>
                <span>{{ now()->format('l, d F Y') }}</span>
                <span>{{ number_format($counts['total']) }} Signals Tracked</span>
            </div>

            <div class="news-masthead__hero">
                <div class="news-masthead__title-group">
                    <h1>The Avatar Lab Dispatch</h1>
                    <p>Live intelligence on AI digital avatars, expressive voice synthesis, video models, and the AdiSaan journey to 1,000,000 followers.</p>
                </div>

                <form class="news-search-form" method="GET" action="{{ route('adisaan.public.news') }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="q" value="{{ $searchQuery }}" placeholder="Search avatar &amp; video news..." aria-label="Search news">
                    @if($searchQuery !== '')
                        <a class="news-search-clear" href="{{ route('adisaan.public.news', $activeCategory !== '' ? ['category' => $activeCategory] : []) }}" aria-label="Clear search">×</a>
                    @endif
                    <button type="submit" aria-label="Submit search">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- 2. CATEGORY FILTER RIBBON -->
    <nav class="news-categories-nav" aria-label="Filter news by topic">
        <div class="container">
            <div class="news-categories-scroll">
                <a href="{{ route('adisaan.public.news') }}" class="news-pill {{ $activeCategory === '' ? 'is-active' : '' }}">
                   All Signals <span class="news-pill__count">{{ number_format($counts['total']) }}</span>
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
                        <span class="news-badge">Lead Story</span>
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
                                Read Full Story ↗
                            </a>
                            <button type="button" class="news-page-btn" data-share data-share-title="{{ $lead['title'] }}" data-share-url="{{ $lead['link'] }}">
                                Share Signal
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
                        <h2>🎬 AdiSaan Video Chronicles</h2>
                        <p>The authentic AdiSaan journey, building in public toward 1,000,000 followers.</p>
                    </div>
                    <a href="https://www.youtube.com/channel/{{ config('tbwim_news.youtube_channel_id') }}" target="_blank" rel="noopener noreferrer" class="btn-primary-news" style="background: rgba(255,255,255,0.15); border: 1px solid var(--news-gold); font-size: 11px;">
                        Subscribe on YouTube ↗
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
                                    <span>YouTube Episode</span>
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
                            Results for “{{ $searchQuery }}”
                        @elseif($activeCategory !== '' && isset($categories[$activeCategory]))
                            {{ $categories[$activeCategory]['label'] }}
                        @else
                            Latest Intelligence Dispatches
                        @endif
                    </span>
                    <span>Showing {{ $totalItems === 0 ? '0-0' : (($page - 1) * config('tbwim_news.per_page') + 1).'-'.(($page - 1) * config('tbwim_news.per_page') + count($grid)) }} of {{ $totalItems }}</span>
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
                                            Read Source ↗
                                        </a>
                                        <span class="news-article-card__cat">{{ $item['category_label'] }}</span>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <!-- Editorial Pagination -->
                    @if($totalPages > 1)
                    <nav class="news-pagination-bar" aria-label="Pagination">
                        @if($prevUrl !== null)
                            <a href="{{ $prevUrl }}" class="news-page-btn">← Previous</a>
                        @else
                            <span class="news-page-btn" style="opacity: 0.4; cursor: not-allowed;">← Previous</span>
                        @endif

                        <span class="news-page-info">
                            Page {{ $page }} of {{ $totalPages }}
                        </span>

                        @if($nextUrl !== null)
                            <a href="{{ $nextUrl }}" class="news-page-btn">Next Page →</a>
                        @else
                            <span class="news-page-btn" style="opacity: 0.4; cursor: not-allowed;">Next Page →</span>
                        @endif
                    </nav>
                    @endif
                @else
                    <div class="news-empty-state">
                        <h3>No signals on this wire right now</h3>
                        <p>{{ $searchQuery !== '' ? 'Nothing matches that search. Try fewer words, or clear it to see the full dispatch.' : 'The feeds are refreshing. The latest stories land here as soon as the next fetch completes.' }}</p>
                        @if($searchQuery !== '')
                            <a href="{{ route('adisaan.public.news', $activeCategory !== '' ? ['category' => $activeCategory] : []) }}" class="btn-primary-news">Clear the search</a>
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
                            <span>📡 Fast Signals</span>
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
                        <img src="/assets/adisaan/site/avatar-cutout.webp" alt="AdiSaan avatar" class="news-curator-avatar">
                        <div class="news-curator-name">
                            <h4>The Curator’s Lens</h4>
                            <span>AdiSaan — Your Online Partner</span>
                        </div>
                    </div>
                    <p class="news-curator-quote">
                        “Every video, avatar prompt, and voice clone is an experiment in public. We curate the signals we test in our lab as we build the AdiSaan personal brand to 1,000,000 followers.”
                    </p>
                    <a href="{{ route('adisaan.public.million') }}" class="btn-primary-news" style="width: 100%; justify-content: center; font-size: 11px;">
                        Explore The 1M Follower Race →
                    </a>
                </div>

                <!-- Widget 3: Newsletter / Follow The Build -->
                <div class="news-widget news-subscribe-widget">
                    <h3>Follow The Build</h3>
                    <p>Receive occasional updates as AdiSaan produces new episodes, tests creative tools, and shares lessons in public.</p>

                    <form class="news-subscribe-form" method="POST" action="{{ route('adisaan.public.construction.lead') }}">
                        @csrf
                        <input type="email" name="email" placeholder="Your email address" required aria-label="Email address">
                        <label class="news-subscribe-consent">
                            <input type="checkbox" name="consent_updates" value="1" checked required>
                            <span>I agree to receive occasional AdiSaan journey updates. Unsubscribe anytime.</span>
                        </label>
                        <button type="submit">Get Weekly Dispatches ↘</button>
                    </form>
                </div>

                <!-- Widget 4: Live RSS Streams Status -->
                @if($streams !== [])
                <div class="news-widget">
                    <h3 class="news-widget__title">
                        <span>⚡ Intelligence Streams</span>
                    </h3>
                    <div class="news-feed-status-list">
                        @foreach($streams as $stream)
                            <div class="news-feed-status-item">
                                <span class="news-feed-status-name">
                                    <span class="news-feed-status-dot"@if(!$stream['ok']) style="background: #b33927;"@endif></span>
                                    {{ $stream['label'] }}
                                </span>
                                <span class="news-feed-status-badge">{{ $stream['ok'] ? number_format($stream['items']).' items' : 'retrying' }}</span>
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
