<?php
/**
 * Setup Test Operators
 * Creates test operator accounts and verification codes
 */

require_once __DIR__ . '/services/OperatorManager.php';
require_once __DIR__ . '/services/IDVerificationManager.php';

use AEIMS\Services\OperatorManager;
use AEIMS\Services\IDVerificationManager;

try {
    $operatorManager = new OperatorManager();
    $idVerification = new IDVerificationManager();

    $testOperators = [];

    // Load or initialize agents operators file
    $agentsOperatorsFile = __DIR__ . '/agents/data/operators.json';
    $agentsDataDir = dirname($agentsOperatorsFile);
    if (!is_dir($agentsDataDir)) {
        mkdir($agentsDataDir, 0755, true);
    }

    $agentsOperators = [];
    if (file_exists($agentsOperatorsFile)) {
        $agentsOperators = json_decode(file_get_contents($agentsOperatorsFile), true) ?? [];
    }

    // Test Operator 1: NYC Flirts - Premium
    echo "Creating test operator 1: NYCDiamond (nycflirts.com)...\n";
    $password1 = 'diamond2024';
    $op1 = $operatorManager->createOperator([
        'username' => 'NYCDiamond',
        'email' => 'nycdiamond@nycflirts.com',
        'category' => 'premium',
        'profile' => [
            'bio' => 'Premium NYC entertainer specializing in interactive shows',
            'age' => 26,
            'location' => 'New York, NY',
            'languages' => ['English'],
            'specialties' => ['interactive_toys', 'role_play', 'girlfriend_experience']
        ]
    ]);

    // Create agent auth account
    $agentsOperators['nycdiamond'] = [
        'id' => $op1['operator_id'],
        'name' => 'NYC Diamond',
        'email' => 'nycdiamond@nycflirts.com',
        'password_hash' => password_hash($password1, PASSWORD_DEFAULT),
        'phone' => '+1-555-0201',
        'status' => 'active',
        'verified' => true,
        'created_at' => date('c'),
        'domains' => ['nycflirts.com' => ['active' => true]],
        'services' => ['calls', 'text', 'chat', 'video'],
        'profile' => [
            'bio' => 'Premium NYC entertainer specializing in interactive shows',
            'age' => 26,
            'location' => 'New York, NY',
            'specialties' => ['Interactive Toys', 'Role Play', 'GFE']
        ],
        'settings' => [
            'calls_enabled' => true,
            'text_enabled' => true,
            'chat_enabled' => true,
            'video_enabled' => true
        ]
    ];

    // Generate and apply verification code
    $code1 = $idVerification->generateOverrideCode('system_admin', null, 'Test operator for nycflirts.com');
    $idVerification->verifyWithCode($op1['operator_id'], $code1);
    $operatorManager->verifyOperator($op1['operator_id']);

    $testOperators[] = [
        'site' => 'nycflirts.com',
        'username' => 'NYCDiamond',
        'email' => 'nycdiamond@nycflirts.com',
        'password' => $password1,
        'operator_id' => $op1['operator_id'],
        'category' => 'premium',
        'verification_code' => $code1
    ];
    echo "✓ Created NYCDiamond\n\n";

    // Test Operator 2: NYC Flirts - Standard
    echo "Creating test operator 2: NYCAngel (nycflirts.com)...\n";
    $password2 = 'angel2024';
    $op2 = $operatorManager->createOperator([
        'username' => 'NYCAngel',
        'email' => 'nycangel@nycflirts.com',
        'category' => 'standard',
        'profile' => [
            'bio' => 'Sweet and fun NYC model ready to chat',
            'age' => 23,
            'location' => 'New York, NY',
            'languages' => ['English', 'Spanish'],
            'specialties' => ['casual_chat', 'girlfriend_experience', 'dancing']
        ]
    ]);

    $agentsOperators['nycangel'] = [
        'id' => $op2['operator_id'],
        'name' => 'NYC Angel',
        'email' => 'nycangel@nycflirts.com',
        'password_hash' => password_hash($password2, PASSWORD_DEFAULT),
        'phone' => '+1-555-0202',
        'status' => 'active',
        'verified' => true,
        'created_at' => date('c'),
        'domains' => ['nycflirts.com' => ['active' => true]],
        'services' => ['calls', 'text', 'chat', 'video'],
        'profile' => [
            'bio' => 'Sweet and fun NYC model ready to chat',
            'age' => 23,
            'location' => 'New York, NY',
            'specialties' => ['Casual Chat', 'GFE', 'Dancing']
        ],
        'settings' => [
            'calls_enabled' => true,
            'text_enabled' => true,
            'chat_enabled' => true,
            'video_enabled' => true
        ]
    ];

    $code2 = $idVerification->generateOverrideCode('system_admin', null, 'Test operator for nycflirts.com');
    $idVerification->verifyWithCode($op2['operator_id'], $code2);
    $operatorManager->verifyOperator($op2['operator_id']);

    $testOperators[] = [
        'site' => 'nycflirts.com',
        'username' => 'NYCAngel',
        'email' => 'nycangel@nycflirts.com',
        'password' => $password2,
        'operator_id' => $op2['operator_id'],
        'category' => 'standard',
        'verification_code' => $code2
    ];
    echo "✓ Created NYCAngel\n\n";

    // Test Operator 3: Flirts.nyc - Elite
    echo "Creating test operator 3: ManhattanQueen (flirts.nyc)...\n";
    $password3 = 'queen2024';
    $op3 = $operatorManager->createOperator([
        'username' => 'ManhattanQueen',
        'email' => 'manhattanqueen@flirts.nyc',
        'category' => 'elite',
        'profile' => [
            'bio' => 'Elite Manhattan entertainer with exclusive experiences',
            'age' => 29,
            'location' => 'Manhattan, NY',
            'languages' => ['English', 'French'],
            'specialties' => ['luxury_experience', 'interactive_toys', 'vr_experience', 'fetish']
        ]
    ]);

    $agentsOperators['manhattanqueen'] = [
        'id' => $op3['operator_id'],
        'name' => 'Manhattan Queen',
        'email' => 'manhattanqueen@flirts.nyc',
        'password_hash' => password_hash($password3, PASSWORD_DEFAULT),
        'phone' => '+1-555-0203',
        'status' => 'active',
        'verified' => true,
        'created_at' => date('c'),
        'domains' => ['flirts.nyc' => ['active' => true]],
        'services' => ['calls', 'text', 'chat', 'video', 'vr'],
        'profile' => [
            'bio' => 'Elite Manhattan entertainer with exclusive experiences',
            'age' => 29,
            'location' => 'Manhattan, NY',
            'specialties' => ['Luxury Experience', 'Interactive Toys', 'VR', 'Fetish']
        ],
        'settings' => [
            'calls_enabled' => true,
            'text_enabled' => true,
            'chat_enabled' => true,
            'video_enabled' => true
        ]
    ];

    $code3 = $idVerification->generateOverrideCode('system_admin', null, 'Test operator for flirts.nyc');
    $idVerification->verifyWithCode($op3['operator_id'], $code3);
    $operatorManager->verifyOperator($op3['operator_id']);

    $testOperators[] = [
        'site' => 'flirts.nyc',
        'username' => 'ManhattanQueen',
        'email' => 'manhattanqueen@flirts.nyc',
        'password' => $password3,
        'operator_id' => $op3['operator_id'],
        'category' => 'elite',
        'verification_code' => $code3
    ];
    echo "✓ Created ManhattanQueen\n\n";

    // Test Operator 4: Flirts.nyc - Premium
    echo "Creating test operator 4: BrooklynBabe (flirts.nyc)...\n";
    $password4 = 'brooklyn2024';
    $op4 = $operatorManager->createOperator([
        'username' => 'BrooklynBabe',
        'email' => 'brooklynbabe@flirts.nyc',
        'category' => 'premium',
        'profile' => [
            'bio' => 'Brooklyn based entertainer with a playful side',
            'age' => 24,
            'location' => 'Brooklyn, NY',
            'languages' => ['English'],
            'specialties' => ['role_play', 'interactive_toys', 'gaming']
        ]
    ]);

    $agentsOperators['brooklynbabe'] = [
        'id' => $op4['operator_id'],
        'name' => 'Brooklyn Babe',
        'email' => 'brooklynbabe@flirts.nyc',
        'password_hash' => password_hash($password4, PASSWORD_DEFAULT),
        'phone' => '+1-555-0204',
        'status' => 'active',
        'verified' => true,
        'created_at' => date('c'),
        'domains' => ['flirts.nyc' => ['active' => true]],
        'services' => ['calls', 'text', 'chat', 'video'],
        'profile' => [
            'bio' => 'Brooklyn based entertainer with a playful side',
            'age' => 24,
            'location' => 'Brooklyn, NY',
            'specialties' => ['Role Play', 'Interactive Toys', 'Gaming']
        ],
        'settings' => [
            'calls_enabled' => true,
            'text_enabled' => true,
            'chat_enabled' => true,
            'video_enabled' => true
        ]
    ];

    $code4 = $idVerification->generateOverrideCode('system_admin', null, 'Test operator for flirts.nyc');
    $idVerification->verifyWithCode($op4['operator_id'], $code4);
    $operatorManager->verifyOperator($op4['operator_id']);

    $testOperators[] = [
        'site' => 'flirts.nyc',
        'username' => 'BrooklynBabe',
        'email' => 'brooklynbabe@flirts.nyc',
        'password' => $password4,
        'operator_id' => $op4['operator_id'],
        'category' => 'premium',
        'verification_code' => $code4
    ];
    echo "✓ Created BrooklynBabe\n\n";

    // Test Operator 5: NYC Flirts - Elite
    echo "Creating test operator 5: NYCGoddess (nycflirts.com)...\n";
    $password5 = 'goddess2024';
    $op5 = $operatorManager->createOperator([
        'username' => 'NYCGoddess',
        'email' => 'nycgoddess@nycflirts.com',
        'category' => 'elite',
        'profile' => [
            'bio' => 'Elite NYC goddess offering premium interactive experiences',
            'age' => 27,
            'location' => 'New York, NY',
            'languages' => ['English', 'Italian'],
            'specialties' => ['luxury_experience', 'fetish', 'domination', 'interactive_toys']
        ]
    ]);

    $agentsOperators['nycgoddess'] = [
        'id' => $op5['operator_id'],
        'name' => 'NYC Goddess',
        'email' => 'nycgoddess@nycflirts.com',
        'password_hash' => password_hash($password5, PASSWORD_DEFAULT),
        'phone' => '+1-555-0205',
        'status' => 'active',
        'verified' => true,
        'created_at' => date('c'),
        'domains' => ['nycflirts.com' => ['active' => true]],
        'services' => ['calls', 'text', 'chat', 'video', 'domination'],
        'profile' => [
            'bio' => 'Elite NYC goddess offering premium interactive experiences',
            'age' => 27,
            'location' => 'New York, NY',
            'specialties' => ['Luxury Experience', 'Fetish', 'Domination', 'Interactive Toys']
        ],
        'settings' => [
            'calls_enabled' => true,
            'text_enabled' => true,
            'chat_enabled' => true,
            'video_enabled' => true
        ]
    ];

    $code5 = $idVerification->generateOverrideCode('system_admin', null, 'Test operator for nycflirts.com');
    $idVerification->verifyWithCode($op5['operator_id'], $code5);
    $operatorManager->verifyOperator($op5['operator_id']);

    $testOperators[] = [
        'site' => 'nycflirts.com',
        'username' => 'NYCGoddess',
        'email' => 'nycgoddess@nycflirts.com',
        'password' => $password5,
        'operator_id' => $op5['operator_id'],
        'category' => 'elite',
        'verification_code' => $code5
    ];
    echo "✓ Created NYCGoddess\n\n";

    // Save agents operators file
    file_put_contents($agentsOperatorsFile, json_encode($agentsOperators, JSON_PRETTY_PRINT));

    // Save credentials to file
    $credentialsFile = __DIR__ . '/data/test_operator_credentials.json';
    file_put_contents($credentialsFile, json_encode([
        'created_at' => date('Y-m-d H:i:s'),
        'operators' => $testOperators
    ], JSON_PRETTY_PRINT));

    echo "═══════════════════════════════════════════════════════\n";
    echo "✓ ALL TEST OPERATORS CREATED AND VERIFIED\n";
    echo "═══════════════════════════════════════════════════════\n\n";

    echo "TEST OPERATOR CREDENTIALS:\n";
    echo "══════════════════════════════════════════════════════\n\n";

    foreach ($testOperators as $op) {
        echo "Site: {$op['site']}\n";
        echo "Username: {$op['username']}\n";
        echo "Email: {$op['email']}\n";
        echo "Password: {$op['password']}\n";
        echo "Category: {$op['category']}\n";
        echo "Operator ID: {$op['operator_id']}\n";
        echo "Verification Code Used: {$op['verification_code']}\n";
        echo "──────────────────────────────────────────────────────\n\n";
    }

    echo "Credentials saved to: {$credentialsFile}\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
