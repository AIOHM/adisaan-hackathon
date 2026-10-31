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
            // The owner pastes feed rows here (URL + category slug); rows can
            // also arrive as JSON through TBWIM_NEWS_FEEDS_JSON without a
            // redeploy. Shape: ['url' => ..., 'category' => ..., 'label' => ...].
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

    'per_page' => 12,

    'max_items_per_feed' => 60,
];
