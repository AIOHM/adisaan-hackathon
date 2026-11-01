<?php

declare(strict_types=1);

return [
    /*
     * The dispatch's RSS/Atom sources, one per category. The owner pastes
     * these from the brand Filament (Content Studio); until then the page
     * renders the videos plus an honest empty state, never a guess.
     * Shape: ['url' => ..., 'category' => '<slug below>', 'label' => ...].
     */
    'feeds' => array_merge(
        [
            // The dispatch's own wires, re-curated 2026-11-01 for the
            // article-picture rule: publisher feeds that carry the story's
            // own image in the item (or serve it as the article's og:image).
            // The previous Google News search wires were dropped: Google
            // serves no article picture a server client may use (its splash
            // page's og:image is one generic brand graphic for every story),
            // so under the rule those wires published placeholders. More
            // rows can arrive as JSON through TBWIM_NEWS_FEEDS_JSON without
            // a redeploy.
            ['url' => 'https://synthedia.substack.com/feed', 'category' => 'avatars-voice-tech', 'label' => 'Avatar & Video Models'],
            ['url' => 'https://www.theverge.com/rss/ai-artificial-intelligence/index.xml', 'category' => 'avatars-voice-tech', 'label' => 'Avatar & Video Models'],
            ['url' => 'https://www.unite.ai/feed/', 'category' => 'avatars-voice-tech', 'label' => 'Voice & AI Models'],
            ['url' => 'https://www.tubefilter.com/feed/', 'category' => 'creator-journey-tips', 'label' => 'Creator Tools'],
            ['url' => 'https://the-decoder.com/feed/', 'category' => 'aiohm-journey', 'label' => 'Builder Wire'],
            ['url' => 'https://arstechnica.com/feed/', 'category' => 'aiohm-journey', 'label' => 'Builder Wire'],
            ['url' => 'https://techcrunch.com/category/startups/feed/', 'category' => 'aiohm-journey', 'label' => 'Builder Wire'],
        ],
        json_decode((string) env('TBWIM_NEWS_FEEDS_JSON', '[]'), true) ?: []
    ),

    'categories' => [
        'adisaan-video-journey' => ['label' => 'Video Journey', 'icon' => '🎬'],
        'avatars-voice-tech' => ['label' => 'Avatars & Voice Tech', 'icon' => '👤'],
        'creator-journey-tips' => ['label' => 'Creator Tips & Workflows', 'icon' => '💡'],
        'aiohm-journey' => ['label' => 'Platform Journey', 'icon' => '⚡'],
    ],

    'youtube_channel_id' => (string) env('TBWIM_NEWS_YOUTUBE', 'UCV0jCXq6c7118e0d0SAkJ7g'),

    /*
     * Real AdiSaan videos, shown when the YouTube feed itself is
     * unreachable. The live feed wins whenever it answers.
     */
    'youtube_fallback' => [
        ['id' => 'Ul20lnLnD6s', 'title' => 'When no one is looking, what do you see? | AdiSaan episode 1'],
        ['id' => '6PRg-OU981I', 'title' => 'How I use Ask MUSE to build a social post | AIOHM Social'],
        ['id' => 'Mn89nuEKEPc', 'title' => 'Your vibe attracts your tribe | AdiSaan'],
        ['id' => 'WS0AI3CJxAw', 'title' => 'Notebooklm: About AIOHM Platform'],
    ],

    'cache_minutes' => (int) env('TBWIM_NEWS_CACHE_MINUTES', 60),

    // Publish only stories carrying the article's own picture: the feed's
    // embedded image, or the article page's og:image fetched on parse.
    // TBWIM_NEWS_OG_IMAGES=false lifts the gate (every story publishes).
    'og_images' => (bool) env('TBWIM_NEWS_OG_IMAGES', true),

    'per_page' => 12,

    'max_items_per_feed' => 60,
];
