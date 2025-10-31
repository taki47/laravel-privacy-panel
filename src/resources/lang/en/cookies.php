<?php

return [
        'xsrf-token' => [
            'category' => 'necessary',
            'provider' => 'Application',
            'description' => 'Essential cookie required for core functionality (e.g., session or CSRF protection).',
            'expiry' => null,
            'url' => null
        ],
        'csrf-token' => [
            'category' => 'necessary',
            'provider' => 'Application',
            'description' => 'Essential cookie required for core functionality (e.g., session or CSRF protection).',
            'expiry' => null,
            'url' => null
        ],
        'laravel-session' => [
            'category' => 'necessary',
            'provider' => 'Application',
            'description' => 'Essential cookie required for core functionality (e.g., session or CSRF protection).',
            'expiry' => null,
            'url' => null
        ],
        'session' => [
            'category' => 'necessary',
            'provider' => 'Application',
            'description' => 'Essential cookie required for core functionality (e.g., session or CSRF protection).',
            'expiry' => null,
            'url' => null
        ],
        '_ga' => [
            'category' => 'statistics',
            'provider' => 'Google Analytics',
            'description' => 'Registers a unique ID used to generate statistical data on how the visitor uses the website.',
            'expiry' => '2 years',
            'url' => 'https://business.safety.google/privacy/'
        ],
        '_gid' => [
            'category' => 'statistics',
            'provider' => 'Google Analytics',
            'description' => 'Registers a unique ID to generate statistical data for each session.',
            'expiry' => '1 day',
            'url' => 'https://business.safety.google/privacy/'
        ],
        '_gat' => [
            'category' => 'statistics',
            'provider' => 'Google Analytics',
            'description' => 'Used to throttle request rate.',
            'expiry' => '1 minute',
            'url' => 'https://business.safety.google/privacy/'
        ],
        '_gcl_au' => [
            'category' => 'marketing',
            'provider' => 'Google Ads',
            'description' => 'Used by Google AdSense for experimenting with advertisement efficiency.',
            'expiry' => '3 months',
            'url' => 'https://business.safety.google/privacy/'
        ],
        '_fbp' => [
            'category' => 'marketing',
            'provider' => 'Meta / Facebook',
            'description' => 'Used by Facebook to deliver a series of advertisement products.',
            'expiry' => '3 months',
            'url' => 'https://www.facebook.com/privacy/policy/'
        ],
        '_fbc' => [
            'category' => 'marketing',
            'provider' => 'Meta / Facebook',
            'description' => 'Stores last visit attribution for Facebook Ads.',
            'expiry' => '3 months',
            'url' => 'https://www.facebook.com/privacy/policy/'
        ],
        '_hjsessionuser' => [
            'category' => 'statistics',
            'provider' => 'Hotjar',
            'description' => 'Sets a unique ID for the session to gather analytics about user behavior.',
            'expiry' => '1 year',
            'url' => 'https://www.hotjar.com/legal/policies/privacy/'
        ],
        '_pk_id' => [
            'category' => 'statistics',
            'provider' => 'Matomo',
            'description' => 'Stores a unique user ID for analytics.',
            'expiry' => '13 months',
            'url' => 'https://matomo.org/privacy-policy/'
        ],
        '_pk_ses' => [
            'category' => 'statistics',
            'provider' => 'Matomo',
            'description' => 'Short-lived cookie used by Matomo to store temporary data.',
            'expiry' => '30 minutes',
            'url' => 'https://matomo.org/privacy-policy/'
        ],
        '_ttp' => [
            'category' => 'marketing',
            'provider' => 'TikTok',
            'description' => 'Used to measure and improve performance of advertising campaigns.',
            'expiry' => '13 months',
            'url' => 'https://www.tiktok.com/legal/page/us/privacy-policy/en'
        ],
        'ysc' => [
            'category' => 'marketing',
            'provider' => 'YouTube',
            'description' => 'Registers a unique ID to keep statistics of what videos from YouTube the user has seen.',
            'expiry' => 'Session',
            'url' => 'https://www.youtube.com/howyoutubeworks/privacy/'
        ],
    ];