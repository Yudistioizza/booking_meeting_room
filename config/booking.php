<?php

/*
 * Booking policy. Everything here can be tuned per company through .env
 * without touching code.
 */
return [
    'company' => env('COMPANY_NAME', 'PT Nusantara Teknologi'),

    // Role names stored in the roles table
    'roles' => [
        'admin' => 'Admin',
        'approver' => 'Manager',
        'employee' => 'Employee',
    ],

    // Default bookable window when a room has no availability rules
    'hours' => [
        'days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
        'from' => env('BOOKING_HOURS_FROM', '07:00'),
        'to' => env('BOOKING_HOURS_TO', '20:00'),
    ],

    'slot_minutes' => 15,
    'max_advance_days' => (int) env('BOOKING_MAX_ADVANCE_DAYS', 90),
    'min_duration_minutes' => 15,
    'max_duration_minutes' => (int) env('BOOKING_MAX_DURATION', 480),
    'max_recurrence' => 52,
    'max_attendees_emails' => 100,
    // A booking can be cancelled or moved until it starts
    'cancel_cutoff_minutes' => 0,

    // Mail and calendar
    'mail' => [
        'enabled' => env('BOOKING_MAIL', true),
        'organizer_from' => env('MAIL_FROM_ADDRESS', 'rooms@example.com'),
    ],

    // SSO: only these email domains may sign in with Microsoft. Empty = any
    // account of the tenant. Comma separated.
    'sso_domains' => array_filter(array_map('trim', explode(',', env('SSO_ALLOWED_DOMAINS', '')))),
    'sso_default_role' => 'Employee',
];
