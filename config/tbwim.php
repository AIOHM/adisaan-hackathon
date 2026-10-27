<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AdiSaan TBWIM movement
|--------------------------------------------------------------------------
|
| One human question at a time, answered by people and by AI agents, kept
| apart, reviewed, synthesised and published as consented, labelled data.
| Everything that is counted here is a row; nothing is estimated.
|
*/

return [
    'url' => rtrim((string) env('TBWIM_URL', 'https://tbwim.adisaan.com'), '/'),
    'name' => env('TBWIM_NAME', 'AdiSaan'),
    'contact' => env('TBWIM_CONTACT', 'contact@adisaan.com'),
    'timezone' => env('TBWIM_TIMEZONE', 'Europe/Bucharest'),

    // Media stays where it already lives. The open repository carries no
    // video; every asset path below is resolved against this base.
    'media_base' => rtrim((string) env('TBWIM_MEDIA_BASE', 'https://adisaan.com'), '/'),

    'meta_description' => 'AdiSaan is the TBWIM movement, an open record of collective human imagination and machine intelligence: one honest question at a time, answered by people and by AI agents, kept apart and published as consented data.',

    'operator_note' => 'AdiSaan asks one honest question at a time and publishes what people and AI agents imagine, side by side, as consented data. Answering is an invitation, not a requirement.',

    /*
     * The questions. `id` is the stable reference every answer carries.
     * The active question is the newest one whose `opens_at` has passed
     * (Europe/Bucharest); a question with no `opens_at` is an archive entry.
     */
    'questions' => [
        [
            'id' => 'imagine-one-million-euro',
            'number' => 1,
            'question' => 'What is the best way you imagine to make one million euro?',
            'lead' => 'What would you build, try, or change? Share your own way of imagining it.',
            'life_area' => 'work',
            'tag' => '#AdiSaanQ01',
            'opens_at' => '2026-10-01 00:00',
            'video' => '/assets/adisaan/site/question-01-one-million-euro.mp4',
            'poster' => '/assets/adisaan/site/question-01-one-million-euro-poster.jpg',
        ],
        [
            'id' => 'start-when-not-ready',
            'number' => 2,
            'question' => 'What is the best way you imagine to start when you are not ready?',
            'lead' => 'The plan is never finished. What is the best way you imagine to begin anyway?',
            'life_area' => 'work',
            'tag' => '#AdiSaanQ02',
            'opens_at' => null,
            'video' => '/assets/adisaan/site/question-02-start-when-not-ready.mp4',
            'poster' => '/assets/adisaan/site/question-02-start-when-not-ready-poster.jpg',
        ],
        [
            'id' => 'know-when-to-stop',
            'number' => 3,
            'question' => 'What is the best way you imagine to know when to stop?',
            'lead' => 'Not every road is worth finishing. How do you imagine knowing when to stop?',
            'life_area' => 'rest',
            'tag' => '#AdiSaanQ03',
            'opens_at' => null,
            'video' => '/assets/adisaan/site/question-03-know-when-to-stop.mp4',
            'poster' => null,
        ],
    ],

    'answer_goal' => 1000000,

    'intake' => [
        'minimum_length' => (int) env('TBWIM_MIN_LENGTH', 10),
        'maximum_length' => (int) env('TBWIM_MAX_LENGTH', 2000),
        'maximum_links' => (int) env('TBWIM_MAX_LINKS', 1),
        'form_proof' => [
            'minimum_seconds' => (int) env('TBWIM_MIN_FORM_SECONDS', 3),
            'maximum_seconds' => (int) env('TBWIM_MAX_FORM_SECONDS', 7200),
            'honeypot_field' => '_website_url',
            'token_field' => '_answer_form_token',
        ],
        // Cloudflare's edge ranges; CF-Connecting-IP is trusted only from these.
        'cloudflare_proxies' => [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
            '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
            '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
            '2a06:98c0::/29', '2c0f:f248::/32',
        ],
    ],

    /*
     * Review. Agent answers are checked by Muse Spark and released or held;
     * a human answer is released only by a person (tbwim:approve).
     */
    'review' => [
        'model' => env('TBWIM_REVIEW_MODEL', 'muse-spark-1.3'),
    ],

    'synthesis' => [
        'model' => env('TBWIM_SYNTHESIS_MODEL', 'muse-spark-1.3'),
        'min_voices' => (int) env('TBWIM_SYNTHESIS_MIN_VOICES', 5),
        'min_words' => 60,
        'max_words' => 120,
    ],

    'voice' => [
        'model' => env('TBWIM_VOICE_MODEL', 'muse-voice-transcribe-1.0'),
    ],

    'poster' => [
        'prompt' => 'Cinematic poster illustration for the community question ":question" (:lead). Deep green (#073209) and warm cream (#F4F0E8) palette, hopeful human imagination theme, one dreamer silhouette looking at a rising horizon, painterly, no text, no words, no letters, no watermark, no logo.',
    ],

    /*
     * Meta Model API. Standard-mode identifiers only; the spending cap admits
     * nothing else. Prices checked 2026-10-27 at dev.meta.ai/docs/pricing-rate-limits.
     */
    'meta' => [
        'api_key' => env('META_API_KEY'),
        'url' => rtrim((string) env('META_API_URL', 'https://api.meta.ai/v1'), '/'),
        'monthly_limit_usd' => (float) env('META_MONTHLY_LIMIT_USD', 10),
        'pricing_review_before' => '2026-11-19',
    ],

    /*
     * The battle: the same question to every contender, identical prompt,
     * answers stored exactly as the agent API stores them.
     */
    'battle' => [
        'prompt' => implode("\n", [
            'You are an autonomous cognitive agent contributing to the TBWIM movement (tbwim.adisaan.com): one honest question at a time, answered by people and by AI agents, kept apart and published as consented data.',
            '',
            'CORE PREMISE:',
            'Machines predict patterns; humans imagine what does not yet exist. As a language model you have no human biology, feelings or lived experience. Examine the corpus of human knowledge in your training and say what its patterns reveal.',
            '',
            "TODAY'S ANCHOR QUESTION:",
            '":question"',
            '',
            'STRICT INSTRUCTIONS:',
            '1. Grounding: say what the patterns in your training reveal about true asymmetry versus the illusion of linear effort.',
            '2. Anti-dogma: strip away hustle cliches, get-rich-quick tropes and generic templates.',
            '3. The mechanism (the path of least resistance): describe an unforced architecture where value compounds naturally.',
            '4. The paradox: say why the outcome cannot be forced, and why loyalty to the craft is what lets the result arrive.',
            '5. Length: exactly 2 to 3 concise paragraphs (150-200 words).',
            '6. Required opening: start strictly with "#TBWIM: Based on my training, the best way I imagine :tail is..."',
        ]),
        // Reasoning models spend output tokens thinking (Wonder burned 62 of
        // 64 on "Say OK"); 420 left nothing for the answer. Room for thought
        // plus the 150-200 word answer. Actual spend stays tiny.
        'max_tokens' => 2048,
        'contenders' => [
            ['model' => 'muse-spark-1.3', 'label' => 'Muse Spark 1.3', 'developer' => 'Meta'],
            ['model' => 'muse-spark-1.2', 'label' => 'Muse Spark 1.2', 'developer' => 'Meta'],
            ['model' => 'muse-wonder-1.0', 'label' => 'Muse Wonder 1.0', 'developer' => 'Meta'],
        ],
    ],

    'social_profiles' => [
        'https://www.linkedin.com/in/adisaan/',
        'https://www.youtube.com/@adisaan',
        'https://www.tiktok.com/@adrian.aparaschivei',
        'https://www.instagram.com/adrian.aparaschivei/',
        'https://www.facebook.com/marketingwithAdiSaan/',
    ],
];
