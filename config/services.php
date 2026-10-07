<?php

return [
    /**
     * POOLred / Fundación del Olivar, the only market data source (spec §8).
     *
     * The owner obtained permission on 2026-10-07, which is the condition the
     * spec attached to writing the importer. Only the two endpoints that need no
     * credentials are used; see App\Services\Poolred\Client for why the other
     * three indices are deliberately not fetched.
     */
    'poolred' => [
        'enabled' => (bool) env('POOLRED_IMPORT_ENABLED', false),

        /** Public REST API, up to but excluding the index name. */
        'index_base' => env('POOLRED_INDEX_BASE', 'https://api.poolred.com/v1/ipool'),

        /** The index that answers without a token. */
        'index' => env('POOLRED_INDEX', 'extra'),

        /**
         * Official WordPress ticker endpoint, used for the variation of all four
         * indices. Public, no token.
         */
        'ticker_url' => env('POOLRED_TICKER_URL', 'https://poolred.com/wp-json/poolred-ticker/v1/datos'),

        /** Where a visitor should be sent to see the source. */
        'source_url' => env('POOLRED_SOURCE_URL', 'https://poolred.com/'),
    ],

    /**
     * Cloudflare Pages deploy hook. Fired by RequestSiteRebuild when content is
     * published, so the static site picks up the change (spec §8).
     */
    'cloudflare' => [
        'deploy_hook_url' => env('CLOUDFLARE_PAGES_DEPLOY_HOOK_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
