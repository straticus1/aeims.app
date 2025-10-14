<?php
/**
 * Data Helper Functions
 * Common utilities for data file operations
 */

/**
 * Load JSON data from file
 */
function loadJsonData($filepath) {
    if (!file_exists($filepath)) {
        return [];
    }

    $data = file_get_contents($filepath);
    $decoded = json_decode($data, true);

    return $decoded ?: [];
}

/**
 * Save JSON data to file
 */
function saveJsonData($filepath, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT);
    return file_put_contents($filepath, $json);
}

/**
 * Ensure data file exists
 */
function ensureDataFile($filepath, $defaultData = []) {
    if (!file_exists($filepath)) {
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        saveJsonData($filepath, $defaultData);
    }
}
