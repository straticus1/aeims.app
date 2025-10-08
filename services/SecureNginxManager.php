<?php
/**
 * Secure Nginx Manager with Command Injection Prevention
 */

namespace AEIMS\Services;

class SecureNginxManager {
    private $nginxBinary;

    public function __construct() {
        // Only allow specific nginx binary paths - no user input
        $this->nginxBinary = '/usr/sbin/nginx'; // Fixed path only
    }

    /**
     * Test nginx configuration safely
     */
    public function testConfiguration(): bool {
        // No user input allowed - fixed command only
        $command = escapeshellcmd($this->nginxBinary) . ' -t 2>&1';
        $output = [];
        $returnCode = 0;

        exec($command, $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Reload nginx configuration safely
     */
    public function reload(): bool {
        // No user input allowed - fixed command only
        $command = escapeshellcmd($this->nginxBinary) . ' -s reload 2>&1';
        $output = [];
        $returnCode = 0;

        exec($command, $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Get nginx status safely
     */
    public function getStatus(): array {
        $status = [
            'running' => false,
            'version' => 'unknown',
            'config_valid' => false
        ];

        // Check if running with fixed command
        exec('pgrep nginx', $output, $returnCode);
        $status['running'] = $returnCode === 0 && !empty($output);

        // Get version safely with fixed command
        exec('nginx -v 2>&1', $versionOutput, $versionCode);
        if ($versionCode === 0 && !empty($versionOutput)) {
            $status['version'] = $versionOutput[0] ?? 'unknown';
        }

        // Test configuration
        $status['config_valid'] = $this->testConfiguration();

        return $status;
    }
}
?>