<?php

/*
|--------------------------------------------------------------------------
| Participation (R-9): opt-in, person-level expertise signals
|--------------------------------------------------------------------------
| Dot.Memory may hold person-level expertise ONLY for people who opted in,
| keyed by InfoDot's user UUID, as topic + count and nothing else. See
| docs/participation-api.md for the contract and the privacy model.
*/

return [

    /*
    | One shared secret per calling platform. A platform whose secret is unset
    | or empty can never authenticate (fails closed). Generate with
    | `openssl rand -hex 32`; never commit a value.
    */
    'secrets' => [
        'infodot' => env('PARTICIPATION_SECRET_INFODOT'),
    ],

    // Allowed clock skew, in seconds, between the signer and this server.
    'tolerance' => (int) env('PARTICIPATION_TOLERANCE', 300),

    // Participation rows older than this many days (by occurred_at) are hard-deleted by participation:prune.
    'retention_days' => (int) env('PARTICIPATION_RETENTION_DAYS', 365),

    // What a recorded event can say about the contribution.
    'kinds' => ['accepted', 'verified'],

    // A topic is a lowercase slug, never free text.
    'topic_pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
    'topic_max_length' => 64,

    // Opaque identifiers (event_id, contribution_ref) may not contain spaces, '@' or any prose.
    'reference_pattern' => '/^[A-Za-z0-9._:-]+$/',

    // Most events one POST /api/participation/events may carry (as {"events": [...]}).
    'max_events_per_request' => (int) env('PARTICIPATION_MAX_EVENTS', 50),
];
