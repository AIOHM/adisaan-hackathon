<?php

declare(strict_types=1);

/*
 * The platform's public-site templates read a few intake limits from
 * `echo_intake.*`. They are the same values the observatory keeps under
 * `observatory.intake.*`, exposed under the names the templates expect.
 */
return [
    'default_prompt' => env('OBSERVATORY_DEFAULT_PROMPT', 'What is the best way you imagine?'),
    'body' => [
        'minimum_length' => (int) env('OBSERVATORY_MIN_LENGTH', 10),
        'maximum_length' => (int) env('OBSERVATORY_MAX_LENGTH', 2000),
    ],
    'form_proof' => [
        'minimum_seconds' => (int) env('OBSERVATORY_MIN_FORM_SECONDS', 3),
        'honeypot_field' => '_website_url',
        'token_field' => '_answer_form_token',
    ],
];
