<?php
/**
 * AEIMS Logging System
 * Provides structured logging for API calls, authentication, calls, and system events
 */

class AeimsLogger {
    private static $logDirectory = '/var/log/aeims/';
    private static $instance = null;

    // Log files
    const API_LOG = 'api_calls.log';
    const AUTH_LOG = 'auth.log';
    const CALLS_LOG = 'calls.log';
    const HTTPD_LOG = 'aeims-httpd.log';
    const LIB_LOG = 'aeims-lib.log';
    const ERROR_LOG = 'error.log';
    const DEBUG_LOG = 'debug.log';

    private function __construct() {
        $this->ensureLogDirectory();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        if (!is_dir(self::$logDirectory)) {
            mkdir(self::$logDirectory, 0755, true);
        }
    }

    /**
     * Write log entry
     */
    private function writeLog($filename, $level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s.u T');
        $pid = getmypid();
        $session_id = session_id() ?: 'no-session';
        $user_id = $_SESSION['user_id'] ?? 'anonymous';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $request_id = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid();

        $logEntry = [
            'timestamp' => $timestamp,
            'pid' => $pid,
            'session_id' => $session_id,
            'user_id' => $user_id,
            'ip' => $ip,
            'request_id' => $request_id,
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context
        ];

        $logLine = json_encode($logEntry) . "\n";

        $filepath = self::$logDirectory . $filename;
        file_put_contents($filepath, $logLine, FILE_APPEND | LOCK_EX);

        // Also log to syslog for critical errors
        if (in_array($level, ['error', 'critical', 'emergency'])) {
            syslog(LOG_ERR, "AEIMS[$filename]: $message");
        }
    }

    /**
     * API Call Logging
     */
    public function logApiCall($endpoint, $method, $status_code, $response_time_ms, $context = []) {
        $this->writeLog(self::API_LOG, 'info', "API Call: $method $endpoint", [
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $status_code,
            'response_time_ms' => $response_time_ms,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'context' => $context
        ]);
    }

    /**
     * Authentication Logging
     */
    public function logAuth($action, $username, $success, $context = []) {
        $level = $success ? 'info' : 'warning';
        $status = $success ? 'SUCCESS' : 'FAILED';

        $this->writeLog(self::AUTH_LOG, $level, "Auth $action: $username [$status]", [
            'action' => $action, // login, logout, register, password_reset
            'username' => $username,
            'success' => $success,
            'user_type' => $_SESSION['user_type'] ?? null,
            'site' => $_SESSION['current_site'] ?? null,
            'context' => $context
        ]);
    }

    /**
     * Call/Communication Logging
     */
    public function logCall($action, $call_id, $from, $to, $duration_seconds = null, $context = []) {
        $this->writeLog(self::CALLS_LOG, 'info', "Call $action: $call_id", [
            'action' => $action, // initiated, connected, ended, failed
            'call_id' => $call_id,
            'from' => $from,
            'to' => $to,
            'duration_seconds' => $duration_seconds,
            'call_type' => $context['call_type'] ?? 'voice', // voice, video, chat
            'site' => $_SESSION['current_site'] ?? null,
            'context' => $context
        ]);
    }

    /**
     * HTTP Server Logging
     */
    public function logHttpd($message, $level = 'info', $context = []) {
        $this->writeLog(self::HTTPD_LOG, $level, $message, [
            'host' => $_SERVER['HTTP_HOST'] ?? '',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'context' => $context
        ]);
    }

    /**
     * AEIMS Library Logging
     */
    public function logLib($component, $action, $message, $level = 'info', $context = []) {
        $this->writeLog(self::LIB_LOG, $level, "[$component] $action: $message", [
            'component' => $component,
            'action' => $action,
            'context' => $context
        ]);
    }

    /**
     * Error Logging
     */
    public function logError($message, $exception = null, $context = []) {
        $errorContext = $context;

        if ($exception instanceof Exception) {
            $errorContext['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }

        $this->writeLog(self::ERROR_LOG, 'error', $message, $errorContext);
    }

    /**
     * Debug Logging
     */
    public function logDebug($message, $context = []) {
        if (defined('AEIMS_DEBUG') && AEIMS_DEBUG) {
            $this->writeLog(self::DEBUG_LOG, 'debug', $message, $context);
        }
    }

    /**
     * System Events
     */
    public function logSystemEvent($event, $message, $level = 'info', $context = []) {
        $this->writeLog(self::HTTPD_LOG, $level, "SYSTEM[$event]: $message", [
            'event' => $event, // startup, shutdown, config_change, deployment
            'context' => $context
        ]);
    }

    /**
     * Log rotation helper (to be called by cron)
     */
    public static function rotateLogs() {
        $logger = self::getInstance();
        $date = date('Y-m-d');

        $logFiles = [
            self::API_LOG,
            self::AUTH_LOG,
            self::CALLS_LOG,
            self::HTTPD_LOG,
            self::LIB_LOG,
            self::ERROR_LOG,
            self::DEBUG_LOG
        ];

        foreach ($logFiles as $logFile) {
            $currentPath = self::$logDirectory . $logFile;
            $archivePath = self::$logDirectory . "archive/$date-$logFile";

            if (file_exists($currentPath) && filesize($currentPath) > 0) {
                // Create archive directory
                $archiveDir = dirname($archivePath);
                if (!is_dir($archiveDir)) {
                    mkdir($archiveDir, 0755, true);
                }

                // Move current log to archive
                rename($currentPath, $archivePath);

                // Compress archive
                if (function_exists('gzopen')) {
                    $gz = gzopen($archivePath . '.gz', 'wb9');
                    $file = fopen($archivePath, 'rb');
                    while (!feof($file)) {
                        gzwrite($gz, fread($file, 8192));
                    }
                    fclose($file);
                    gzclose($gz);
                    unlink($archivePath);
                }

                // Create new empty log file
                touch($currentPath);
                chmod($currentPath, 0644);
            }
        }

        // Clean up old archives (older than 30 days)
        $archiveDir = self::$logDirectory . 'archive/';
        if (is_dir($archiveDir)) {
            $files = glob($archiveDir . '*');
            foreach ($files as $file) {
                if (filemtime($file) < strtotime('-30 days')) {
                    unlink($file);
                }
            }
        }
    }
}

/**
 * Global logging functions for easy use
 */
function aeims_log_api($endpoint, $method, $status_code, $response_time_ms, $context = []) {
    AeimsLogger::getInstance()->logApiCall($endpoint, $method, $status_code, $response_time_ms, $context);
}

function aeims_log_auth($action, $username, $success, $context = []) {
    AeimsLogger::getInstance()->logAuth($action, $username, $success, $context);
}

function aeims_log_call($action, $call_id, $from, $to, $duration_seconds = null, $context = []) {
    AeimsLogger::getInstance()->logCall($action, $call_id, $from, $to, $duration_seconds, $context);
}

function aeims_log_error($message, $exception = null, $context = []) {
    AeimsLogger::getInstance()->logError($message, $exception, $context);
}

function aeims_log_debug($message, $context = []) {
    AeimsLogger::getInstance()->logDebug($message, $context);
}

function aeims_log_system($event, $message, $level = 'info', $context = []) {
    AeimsLogger::getInstance()->logSystemEvent($event, $message, $level, $context);
}

// Set up PHP error handling to use AEIMS logger
function aeims_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    $logger = AeimsLogger::getInstance();
    $level = match($severity) {
        E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'error',
        E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'warning',
        E_NOTICE, E_USER_NOTICE, E_STRICT => 'notice',
        default => 'info'
    };

    $logger->logError("PHP Error: $message", null, [
        'severity' => $severity,
        'file' => $file,
        'line' => $line
    ]);

    return false;
}

function aeims_exception_handler($exception) {
    AeimsLogger::getInstance()->logError("Uncaught Exception: " . $exception->getMessage(), $exception);
}

// Register error handlers
set_error_handler('aeims_error_handler');
set_exception_handler('aeims_exception_handler');

// Log system startup
aeims_log_system('startup', 'AEIMS Logger initialized');
?>