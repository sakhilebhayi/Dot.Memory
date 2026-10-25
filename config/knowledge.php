<?php

/*
|--------------------------------------------------------------------------
| Knowledge recommendations (R-9 sections 5 and 6)
|--------------------------------------------------------------------------
| Dot.Brain writes `decision` records about InfoDot contributions (opaque
| refs, never people) through the Sanctum intelligence API. InfoDot's
| moderators read them and report the outcome here; Brain reads the outcomes
| back to grade itself. See docs/knowledge-recommendations-api.md.
*/

return [

    /*
    | One shared HMAC secret per calling consumer. A consumer whose secret is
    | unset or empty can never authenticate (fails closed). Generate each with
    | `openssl rand -hex 32`; never commit a value; keep the two different.
    */
    'secrets' => [
        'infodot' => env('KNOWLEDGE_SECRET_INFODOT'),
        'brain' => env('KNOWLEDGE_SECRET_BRAIN'),
    ],

    // Allowed clock skew, in seconds, between the signer and this server.
    'tolerance' => (int) env('KNOWLEDGE_TOLERANCE', 300),

    // Requests per minute per client address, applied before the signature check.
    'rate_limit_per_minute' => (int) env('KNOWLEDGE_RATE_LIMIT', 120),

    // The only decisions this API ever reads.
    'subject_type' => 'infodot-contribution',
    'signature' => 'knowledge-candidate',
    'verdicts' => ['recommend_for_library', 'watch', 'do_not_recommend'],

    // A moderator may flip adopted <-> declined this many days after the FIRST outcome was recorded.
    'change_window_days' => (int) env('KNOWLEDGE_CHANGE_WINDOW_DAYS', 30),

    'default_limit' => 50,
    'max_limit' => 100,
    'outcomes_default_limit' => 100,
    'outcomes_max_limit' => 500,
];
