<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'azure' => [
        'client_id' => env('AZURE_CLIENT_ID'),
        'client_secret' => env('AZURE_CLIENT_SECRET'),
        'redirect' => env('AZURE_REDIRECT_URI'),
        'tenant' => env('AZURE_TENANT_ID', 'common'),
        'tenant_id' => env('AZURE_TENANT_ID'),
    ],


    // Microsoft Graph: creates events in Outlook and invites attendees.
    // Needs an Entra app registration with the Calendars.ReadWrite
    // application permission (admin consent). Leave empty to use e-mail + .ics only.
    'graph' => [
        'tenant_id' => env('GRAPH_TENANT_ID', env('AZURE_TENANT_ID')),
        'client_id' => env('GRAPH_CLIENT_ID', env('AZURE_CLIENT_ID')),
        'client_secret' => env('GRAPH_CLIENT_SECRET', env('AZURE_CLIENT_SECRET')),
        // Mailbox that owns the calendar events (shared mailbox / service account)
        'organizer' => env('GRAPH_ORGANIZER'),
    ],

    // Incoming webhook for Microsoft Teams or Slack (both accept {"text": ...})
    'webhook' => [
        'url' => env('BOOKING_WEBHOOK_URL'),
    ],

];
