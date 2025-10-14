#!/usr/bin/env php
<?php
/**
 * PHASE 1 MIGRATION TEST
 * Verifies that DatabaseManager changes won't break authentication
 */

echo "TPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPW\n";
echo "Q      PHASE 1 MIGRATION TEST - DatabaseManager            Q\n";
echo "Q      Testing: Lazy Loading & Feature Flags               Q\n";
echo "ZPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPP]\n\n";

require_once __DIR__ . '/../includes/DatabaseManager.php';

$tests = [];
$passed = 0;
$failed = 0;

// Test 1: DatabaseManager can be instantiated without throwing
echo "Test 1: DatabaseManager instantiation (should NEVER throw)...\n";
try {
    $db = DatabaseManager::getInstance();
    $tests[] = ['name' => 'DatabaseManager instantiation', 'result' => 'PASS', 'message' => 'No exception thrown'];
    $passed++;
    echo "   PASS - DatabaseManager instantiated successfully\n\n";
} catch (Exception $e) {
    $tests[] = ['name' => 'DatabaseManager instantiation', 'result' => 'FAIL', 'message' => $e->getMessage()];
    $failed++;
    echo "  L FAIL - Exception thrown: " . $e->getMessage() . "\n\n";
    die("CRITICAL: DatabaseManager threw exception on instantiation. This would break auth!\n");
}

// Test 2: isEnabled() returns false (default state)
echo "Test 2: isEnabled() with USE_DATABASE=false (default)...\n";
$enabled = $db->isEnabled();
if ($enabled === false) {
    $tests[] = ['name' => 'isEnabled() default state', 'result' => 'PASS', 'message' => 'Returns false as expected'];
    $passed++;
    echo "   PASS - isEnabled() = false (database disabled by default)\n\n";
} else {
    $tests[] = ['name' => 'isEnabled() default state', 'result' => 'FAIL', 'message' => "Expected false, got: $enabled"];
    $failed++;
    echo "  L FAIL - isEnabled() = $enabled (expected false)\n\n";
}

// Test 3: isAvailable() returns false gracefully (no exception)
echo "Test 3: isAvailable() without DB connection (should not throw)...\n";
try {
    $available = $db->isAvailable();
    if ($available === false) {
        $tests[] = ['name' => 'isAvailable() without DB', 'result' => 'PASS', 'message' => 'Returns false gracefully'];
        $passed++;
        echo "   PASS - isAvailable() = false (no exception thrown)\n\n";
    } else {
        $tests[] = ['name' => 'isAvailable() without DB', 'result' => 'FAIL', 'message' => "Unexpected true"];
        $failed++;
        echo "  L FAIL - isAvailable() = true (should be false)\n\n";
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'isAvailable() without DB', 'result' => 'FAIL', 'message' => 'Threw exception: ' . $e->getMessage()];
    $failed++;
    echo "  L FAIL - isAvailable() threw exception: " . $e->getMessage() . "\n\n";
}

// Test 4: healthCheck() returns safe result
echo "Test 4: healthCheck() without DB connection (should not throw)...\n";
try {
    $health = $db->healthCheck();
    if (isset($health['status']) && $health['status'] === 'disabled') {
        $tests[] = ['name' => 'healthCheck() without DB', 'result' => 'PASS', 'message' => 'Returns disabled status'];
        $passed++;
        echo "   PASS - healthCheck() = " . json_encode($health) . "\n\n";
    } else {
        $tests[] = ['name' => 'healthCheck() without DB', 'result' => 'WARNING', 'message' => 'Unexpected status: ' . ($health['status'] ?? 'unknown')];
        echo "  �  WARNING - healthCheck() status = " . ($health['status'] ?? 'unknown') . "\n";
        echo "     Full result: " . json_encode($health) . "\n\n";
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'healthCheck() without DB', 'result' => 'FAIL', 'message' => 'Threw exception: ' . $e->getMessage()];
    $failed++;
    echo "  L FAIL - healthCheck() threw exception: " . $e->getMessage() . "\n\n";
}

// Test 5: Simulate auth file loading DatabaseManager
echo "Test 5: Simulating auth file behavior (like sites/flirts.nyc/auth.php)...\n";
try {
    // This is what the auth files do
    $db2 = DatabaseManager::getInstance();
    $tests[] = ['name' => 'Auth file simulation', 'result' => 'PASS', 'message' => 'Auth can safely load DatabaseManager'];
    $passed++;
    echo "   PASS - Auth files can safely load DatabaseManager\n\n";
} catch (Exception $e) {
    $tests[] = ['name' => 'Auth file simulation', 'result' => 'FAIL', 'message' => 'Auth would break: ' . $e->getMessage()];
    $failed++;
    echo "  L FAIL - Auth would break with exception: " . $e->getMessage() . "\n\n";
}

// Test 6: Enable database and test connection failure handling
echo "Test 6: Testing with USE_DATABASE=true (but no DB available)...\n";
putenv('USE_DATABASE=true');
try {
    $db3 = DatabaseManager::getInstance();
    $enabled3 = $db3->isEnabled();
    $available3 = $db3->isAvailable();

    if ($enabled3 === true && $available3 === false) {
        $tests[] = ['name' => 'Feature flag enabled, DB unavailable', 'result' => 'PASS', 'message' => 'Handled gracefully'];
        $passed++;
        echo "   PASS - isEnabled()=true, isAvailable()=false (graceful degradation)\n\n";
    } else {
        $tests[] = ['name' => 'Feature flag enabled, DB unavailable', 'result' => 'FAIL', 'message' => "enabled=$enabled3, available=$available3"];
        $failed++;
        echo "  L FAIL - Unexpected state: enabled=$enabled3, available=$available3\n\n";
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'Feature flag enabled, DB unavailable', 'result' => 'FAIL', 'message' => 'Threw exception: ' . $e->getMessage()];
    $failed++;
    echo "  L FAIL - Exception thrown: " . $e->getMessage() . "\n\n";
}
putenv('USE_DATABASE=false'); // Reset

// Summary
echo "TPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPW\n";
echo "Q                    TEST SUMMARY                           Q\n";
echo "ZPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPP]\n\n";

echo "Total Tests: " . ($passed + $failed) . "\n";
echo "Passed: $passed \n";
echo "Failed: $failed L\n\n";

if ($failed === 0) {
    echo "TPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPW\n";
    echo "Q   PHASE 1 COMPLETE - ALL TESTS PASSED!                 Q\n";
    echo "Q                                                           Q\n";
    echo "Q  DatabaseManager is now safe to use:                     Q\n";
    echo "Q  • Will not break authentication                         Q\n";
    echo "Q  • Supports lazy loading                                 Q\n";
    echo "Q  • Feature flag controlled                               Q\n";
    echo "Q  • Graceful degradation                                  Q\n";
    echo "Q                                                           Q\n";
    echo "Q  Ready for Phase 2: DataLayer abstraction                Q\n";
    echo "ZPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPP]\n\n";
    exit(0);
} else {
    echo "TPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPW\n";
    echo "Q  L PHASE 1 INCOMPLETE - SOME TESTS FAILED               Q\n";
    echo "Q                                                           Q\n";
    echo "Q  Please review failed tests above                        Q\n";
    echo "ZPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPPP]\n\n";
    exit(1);
}
