<?php
/**
 * AEIMS Agents Portal Configuration
 * Cross-Domain Operator Management System
 */

return [
    // Agents portal settings
    'portal' => [
        'name' => 'AEIMS Agents Portal',
        'subtitle' => 'Cross-Domain Operator Management',
        'version' => '1.0',
        'support_email' => 'operators@aeims.app'
    ],

    // Available domains operators can work on
    'domains' => [
        'beastybitches.com' => [
            'name' => 'Beasty Bitches',
            'theme' => 'Fierce Women',
            'services' => ['calls', 'text', 'chat', 'video'],
            'rates' => ['calls' => 2.99, 'text' => 0.99, 'chat' => 1.99, 'video' => 4.99],
            'active' => true
        ],
        'cavern.love' => [
            'name' => 'The Love Cavern', 
            'theme' => 'Intimate Experiences',
            'services' => ['calls', 'text', 'chat', 'video'],
            'rates' => ['calls' => 3.99, 'text' => 1.29, 'chat' => 2.49, 'video' => 5.99],
            'active' => true
        ],
        'nitetext.com' => [
            'name' => 'Nite Text',
            'theme' => 'Text-Only Platform',
            'services' => ['text', 'chat'],
            'rates' => ['text' => 0.79, 'chat' => 1.49],
            'active' => true
        ],
        'nineinchesof.com' => [
            'name' => 'Nine Inches Of',
            'theme' => 'Size Matters',
            'services' => ['calls', 'text', 'chat', 'video', 'cam'],
            'rates' => ['calls' => 4.99, 'text' => 1.49, 'chat' => 2.99, 'video' => 6.99, 'cam' => 7.99],
            'active' => true
        ],
        'holyflirts.com' => [
            'name' => 'Holy Flirts',
            'theme' => 'Forbidden Desires',
            'services' => ['calls', 'text', 'chat', 'video'],
            'rates' => ['calls' => 3.49, 'text' => 1.19, 'chat' => 2.29, 'video' => 5.49],
            'active' => true
        ],
        'nycflirts.com' => [
            'name' => 'NYC Flirts',
            'theme' => 'City Girls',
            'services' => ['calls', 'text', 'chat', 'video'],
            'rates' => ['calls' => 4.49, 'text' => 1.39, 'chat' => 2.79, 'video' => 6.49],
            'active' => true
        ],
        'gfecalls.com' => [
            'name' => 'GFE Calls',
            'theme' => 'Girlfriend Experience',
            'services' => ['calls', 'video', 'chat'],
            'rates' => ['calls' => 5.99, 'video' => 8.99, 'chat' => 3.99],
            'active' => true
        ],
        'latenite.love' => [
            'name' => 'Late Nite Love',
            'theme' => 'Late Night Connection',
            'services' => ['calls', 'text', 'chat', 'video'],
            'rates' => ['calls' => 3.99, 'text' => 1.29, 'chat' => 2.49, 'video' => 5.99],
            'active' => true
        ],
        'fantasyflirts.live' => [
            'name' => 'Fantasy Flirts Live',
            'theme' => 'Live Fantasy',
            'services' => ['calls', 'text', 'chat', 'video', 'cam', 'custom'],
            'rates' => ['calls' => 4.99, 'text' => 1.99, 'chat' => 3.49, 'video' => 7.49, 'cam' => 8.99, 'custom' => 9.99],
            'active' => true
        ],
        'dommecats.com' => [
            'name' => 'Domme Cats',
            'theme' => 'Dominatrix Queens',
            'services' => ['calls', 'text', 'chat', 'video', 'domination'],
            'rates' => ['calls' => 6.99, 'text' => 2.49, 'chat' => 4.99, 'video' => 9.99, 'domination' => 12.99],
            'active' => true
        ]
    ],

    // Operator service types
    'services' => [
        'calls' => [
            'name' => 'Voice Calls',
            'description' => 'Live voice conversations',
            'icon' => '📞',
            'settings' => ['forwarding', 'recording', 'caller_id', 'call_screening']
        ],
        'text' => [
            'name' => 'Text Messages',
            'description' => 'SMS and text chat',
            'icon' => '💬',
            'settings' => ['auto_reply', 'keyword_responses', 'media_sharing', 'scheduling']
        ],
        'chat' => [
            'name' => 'Live Chat',
            'description' => 'Real-time text chat',
            'icon' => '💭',
            'settings' => ['typing_indicators', 'read_receipts', 'file_sharing', 'emoji_reactions']
        ],
        'video' => [
            'name' => 'Video Calls',
            'description' => 'Live video conversations',
            'icon' => '📹',
            'settings' => ['camera_quality', 'screen_sharing', 'recording', 'backgrounds']
        ],
        'cam' => [
            'name' => 'Cam Shows',
            'description' => 'Live streaming shows',
            'icon' => '🎥',
            'settings' => ['streaming_quality', 'tips', 'private_shows', 'recordings']
        ],
        'custom' => [
            'name' => 'Custom Content',
            'description' => 'Personalized content creation',
            'icon' => '🎨',
            'settings' => ['content_types', 'pricing', 'delivery_method', 'custom_requests']
        ],
        'domination' => [
            'name' => 'Domination Services',
            'description' => 'BDSM and domination experiences',
            'icon' => '👑',
            'settings' => ['intensity_levels', 'safe_words', 'limits', 'aftercare']
        ]
    ],

    // Profile customization options
    'profile_options' => [
        'headings' => [
            'About Me',
            'What I Offer',
            'My Specialties', 
            'Rates & Services',
            'Availability',
            'Rules & Boundaries',
            'Contact Info',
            'Gallery',
            'Custom Section 1',
            'Custom Section 2'
        ],
        'image_types' => [
            'profile' => ['max_size' => '5MB', 'formats' => ['jpg', 'png', 'gif']],
            'gallery' => ['max_size' => '10MB', 'formats' => ['jpg', 'png', 'gif', 'mp4']],
            'verification' => ['max_size' => '2MB', 'formats' => ['jpg', 'png']]
        ],
        'content_items' => [
            'photos' => 'Photo Sets',
            'videos' => 'Video Content', 
            'audio' => 'Audio Messages',
            'custom' => 'Custom Content',
            'worn_items' => 'Worn Items',
            'digital' => 'Digital Content'
        ]
    ],

    // Call forwarding options
    'forwarding_options' => [
        'direct' => 'Direct to my phone',
        'voicemail' => 'Send to voicemail',
        'queue' => 'Add to call queue',
        'busy_signal' => 'Play busy signal',
        'custom_message' => 'Play custom message',
        'forward_number' => 'Forward to another number'
    ],

    // Availability settings
    'availability' => [
        'status_options' => [
            'online' => ['label' => 'Online', 'color' => '#22c55e'],
            'busy' => ['label' => 'Busy', 'color' => '#f59e0b'],
            'away' => ['label' => 'Away', 'color' => '#6b7280'],
            'offline' => ['label' => 'Offline', 'color' => '#ef4444'],
            'do_not_disturb' => ['label' => 'Do Not Disturb', 'color' => '#7c3aed']
        ],
        'schedule_options' => [
            'always_available' => 'Always Available',
            'business_hours' => 'Business Hours Only',
            'custom_schedule' => 'Custom Schedule',
            'on_demand' => 'On Demand Only'
        ]
    ],

    // Analytics and earnings
    'analytics' => [
        'metrics' => [
            'total_calls' => 'Total Calls',
            'call_duration' => 'Average Call Duration',
            'text_messages' => 'Text Messages Sent',
            'chat_sessions' => 'Chat Sessions',
            'earnings_today' => 'Today\'s Earnings',
            'earnings_week' => 'This Week\'s Earnings',
            'earnings_month' => 'This Month\'s Earnings',
            'top_domains' => 'Top Performing Domains',
            'customer_ratings' => 'Customer Ratings',
            'repeat_customers' => 'Repeat Customers'
        ]
    ],

    // Security and verification
    'security' => [
        'verification_required' => true,
        'two_factor_auth' => true,
        'photo_verification' => true,
        'background_check' => false,
        'age_verification' => true
    ],

    // Payment settings
    'payments' => [
        'methods' => ['bank_transfer', 'paypal', 'crypto', 'check'],
        'frequencies' => ['daily', 'weekly', 'bi_weekly', 'monthly'],
        'minimum_payout' => 50.00,
        'commission_rates' => [
            'calls' => 70, // 70% to operator, 30% to platform
            'text' => 65,
            'chat' => 65,
            'video' => 75,
            'cam' => 80,
            'custom' => 85,
            'domination' => 80
        ]
    ],

    // AEIMS Integration
    'aeims_integration' => [
        'enabled' => true,
        'operator_api' => 'http://localhost:3000/api/operators',
        'real_time_sync' => true,
        'cross_domain_stats' => true
    ]
];
?>