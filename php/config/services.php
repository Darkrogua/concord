<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, Mandrill, and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | DevBot: chub:notice, git hook после pull, make deploy.
    | env() здесь — чтобы при config:cache значения попали в кэш; в коде только config().
    */
    'devbot' => [
        'rocketchat_webhook_url' => env('DEV_BOT_ROCKETCHAT_WEBHOOK_URL', ''),
        'telegram_bot_token' => env('DEV_BOT_TELEGRAM_BOT_TOKEN', ''),
        'telegram_chat_id' => env('DEV_BOT_TELEGRAM_CHAT_ID', ''),
        /** owner/repo — если origin не github.com (иначе URL берётся из CHUB_DEPLOY_REMOTE) */
        'github_repo' => env('DEPLOY_GITHUB_REPO', ''),
    ],

];
