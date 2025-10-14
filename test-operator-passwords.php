<?php
/**
 * Test operator password verification
 */

$operators = [
    'nycdiamond' => ['email' => 'nycdiamond@nycflirts.com', 'password' => 'diamond2024'],
    'nycangel' => ['email' => 'nycangel@nycflirts.com', 'password' => 'angel2024'],
    'nycgoddess' => ['email' => 'nycgoddess@nycflirts.com', 'password' => 'goddess2024'],
    'manhattanqueen' => ['email' => 'manhattanqueen@flirts.nyc', 'password' => 'queen2024'],
    'brooklynbabe' => ['email' => 'brooklynbabe@flirts.nyc', 'password' => 'brooklyn2024'],
];

$operatorsData = json_decode(file_get_contents('agents/data/operators.json'), true);

echo "Testing operator passwords:\n\n";

foreach ($operators as $key => $expected) {
    if (isset($operatorsData[$key])) {
        $stored = $operatorsData[$key];
        $passwordMatch = password_verify($expected['password'], $stored['password_hash']);

        echo "✓ {$expected['email']}\n";
        echo "  Password: {$expected['password']}\n";
        echo "  Match: " . ($passwordMatch ? "YES" : "NO") . "\n";

        if (!$passwordMatch) {
            // Create correct hash
            $correctHash = password_hash($expected['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            echo "  Correct hash: $correctHash\n";
        }
        echo "\n";
    } else {
        echo "✗ {$expected['email']} - NOT FOUND\n\n";
    }
}
?>
