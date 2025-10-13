<?php

// AEIMS Production Configuration
return [
    // Site configuration
    'site' => [
        'name' => 'AEIMS',
        'full_name' => 'Adult Entertainment Interactive Management System',
        'company' => 'After Dark Systems',
        'url' => 'https://www.aeims.app',
        'frontend_url' => 'https://www.aeims.app',
        'contact_email' => 'info@aeims.app',
        'response_time' => '24 hours',
        'powered_sites' => [
            ['name' => 'NYC Flirts', 'url' => 'https://nycflirts.com', 'description' => 'Premium NYC dating service'],
            ['name' => 'Flirts NYC', 'url' => 'https://flirts.nyc', 'description' => 'New York\'s hottest connections'],
            ['name' => 'SexaComms', 'url' => 'https://sexacomms.com', 'description' => 'Adult communication platform']
        ]
    ],

    // Application
    'app' => [
        'env' => 'production',
        'debug' => false,
        'url' => 'https://www.aeims.app',
        'frontend_url' => 'https://www.aeims.app'
    ],

    // Database
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: 5432,
        'name' => getenv('DB_NAME') ?: 'aeims_core',
        'user' => getenv('DB_USER') ?: 'aeims_user',
        'password' => getenv('DB_PASS') ?: ''
    ],

    // Security
    'jwt_secret' => getenv('JWT_SECRET') ?: 'CHANGE_THIS_IN_PRODUCTION',

    // Session
    'session' => [
        'name' => 'AEIMS_SESSION',
        'lifetime' => 7200, // 2 hours
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ],

    // AEIMS Integration
    'aeims_integration' => [
        'enabled' => false  // Disabled in production container
    ],

    // Statistics
    'stats' => [
        'sites_powered' => 10,
        'uptime' => 99.9,
        'support_hours' => 24,
        'cross_site_operators' => 85,
        'total_calls_today' => 1247,
        'messages_today' => 3856,
        'revenue_today' => 12458
    ],

    // Powered Sites (for homepage display)
    'powered_sites' => [
        [
            'domain' => 'nycflirts.com',
            'theme' => 'Romance & Dating',
            'description' => 'Premium NYC dating and entertainment service with video chat, voice calls, and operator profiles',
            'services' => ['Video Chat', 'Voice Calls', 'Text Chat', 'Operator Profiles']
        ],
        [
            'domain' => 'flirts.nyc',
            'theme' => 'Entertainment & Connections',
            'description' => 'New York\'s hottest connections and entertainment platform with cross-site operator support',
            'services' => ['Video Chat', 'Voice Calls', 'Text Chat', 'Live Streaming']
        ],
        [
            'domain' => 'sexacomms.com',
            'theme' => 'Operator Platform',
            'description' => 'Adult communication platform for operators with earnings tracking and multi-site management',
            'services' => ['Operator Dashboard', 'Earnings Tracking', 'Multi-Site Support', 'Payment Processing']
        ]
    ]
];
