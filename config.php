<?php
/**
 * AEIMS Website Configuration
 */

return [
    // Contact form settings
    'contact' => [
        'recipient_email' => 'coleman.ryan@gmail.com',
        'subject_prefix' => '[AEIMS License Inquiry]',
        'from_email' => 'noreply@aeims.app',
        'from_name' => 'AEIMS Contact Form',
        'max_message_length' => 2000,
        'required_fields' => ['name', 'email', 'message']
    ],

    // Rate limiting
    'rate_limit' => [
        'max_requests' => 5,
        'time_window' => 3600, // 1 hour in seconds
    ],

    // Site settings
    'site' => [
        'name' => 'AEIMS',
        'full_name' => 'Adult Entertainment Information Management System',
        'company' => 'After Dark Systems',
        'contact_email' => 'coleman.ryan@gmail.com',
        'response_time' => '24 hours'
    ],

    // Powered by sites
    'powered_sites' => [
        [
            'domain' => 'beastybitches.com',
            'theme' => 'Beasty Women',
            'description' => 'Fierce and confident women who know what they want',
            'services' => ['Live Chat', 'Video Calls', 'Voice Calls']
        ],
        [
            'domain' => 'cavern.love',
            'theme' => 'The Love Cavern',
            'description' => 'Intimate and mysterious adult connection experiences',
            'services' => ['Live Chat', 'Video Calls', 'Voice Calls']
        ],
        [
            'domain' => 'nitetext.com',
            'theme' => 'Text-Only Platform',
            'description' => 'Pure text-based adult entertainment experience',
            'services' => ['Text Chat', 'SMS Services', 'Chat Rooms']
        ],
        [
            'domain' => 'nineinchesof.com',
            'theme' => 'Size Matters',
            'description' => 'For those who think they have what it takes',
            'services' => ['Live Chat', 'Video Calls', 'Cam Shows']
        ],
        [
            'domain' => 'holyflirts.com',
            'theme' => 'Dirty Church Girls',
            'description' => 'Forbidden desires and sacred temptations',
            'services' => ['Live Chat', 'Video Calls', 'Voice Calls']
        ],
        [
            'domain' => 'nycflirts.com',
            'theme' => 'City Girls, City Pearls',
            'description' => 'Sophisticated urban women with attitude',
            'services' => ['Live Chat', 'Video Calls', 'Voice Calls']
        ],
        [
            'domain' => 'gfecalls.com',
            'theme' => 'Girlfriend Experience',
            'description' => 'Dedicated GFE connections and intimate conversations',
            'services' => ['Voice Calls', 'Video Calls', 'Text Chat']
        ],
        [
            'domain' => 'latenite.love',
            'theme' => 'Late Night Love',
            'description' => 'For lonely nights when you need connection',
            'services' => ['Live Chat', 'Video Calls', 'Voice Calls']
        ],
        [
            'domain' => 'fantasyflirts.live',
            'theme' => 'Your Fantasy, Your Flirt',
            'description' => 'Live fantasy fulfillment and role-playing',
            'services' => ['Live Shows', 'Interactive Chat', 'Custom Content']
        ],
        [
            'domain' => 'dommecats.com',
            'theme' => 'Dominatrix Queens',
            'description' => 'Powerful women who will make any man purr',
            'services' => ['Domination Chat', 'Video Sessions', 'Voice Commands']
        ]
    ],

    // Platform statistics (will be overridden by real AEIMS data when available)
    'stats' => [
        'sites_powered' => 10,
        'uptime' => 99.9,
        'support_hours' => 24,
        'cross_site_operators' => 85,
        'discrete_transactions' => 'all'
    ],
    
    // AEIMS Integration Settings
    'aeims_integration' => [
        'enabled' => true,
        'cli_path' => '../aeims/cli/aeims',
        'api_url' => 'http://localhost:3000/api',
        'fallback_to_mock' => true, // Use mock data when AEIMS unavailable
        'cache_timeout' => 300 // Cache AEIMS data for 5 minutes
    ],

    // Key platform features
    'key_features' => [
        'cross_site_operators' => [
            'title' => 'Cross-Site Operator Support',
            'description' => 'One operator can work across multiple sites simultaneously, maximizing earning potential and operational efficiency.',
            'benefits' => [
                'Unified operator dashboard across all sites',
                'Single login for multiple platforms',
                'Consolidated earnings and statistics',
                'Streamlined operator onboarding',
                'Reduced training overhead'
            ]
        ],
        'discrete_billing' => [
            'title' => 'Discrete Billing',
            'description' => 'All transactions appear with discrete, non-descriptive billing descriptors to protect customer privacy.',
            'benefits' => [
                'Privacy-focused transaction descriptions',
                'Non-identifiable merchant names',
                'Secure payment processing',
                'Multiple payment method support',
                'Chargeback protection'
            ]
        ],
        'device_control' => [
            'title' => 'Revolutionary Device Control (aeimsLib)',
            'description' => 'Industry-first comprehensive device integration supporting 15+ major brands and protocols with real-time control, patterns, and synchronization.',
            'benefits' => [
                'Lovense, WeVibe, Kiiroo, Magic Motion support',
                'VR/XR integration with haptic feedback',
                'Real-time device synchronization',
                'Custom pattern creation and marketplace',
                'Audio/video synchronization',
                'Mesh networking for multiple devices',
                'Mobile app integration (iOS/Android)',
                'AI-powered pattern generation',
                'Secure WebSocket device control'
            ],
            'supported_brands' => [
                'Lovense', 'WeVibe/WowTech', 'Kiiroo', 'Magic Motion',
                'Svakom', 'Vorze', 'Handy/Stroker', 'PiShock',
                'Satisfyer Connect', 'Vibease', 'LoveLife',
                'Bluetooth TENS Units', 'TCode Protocol'
            ]
        ]
    ]
];
?>