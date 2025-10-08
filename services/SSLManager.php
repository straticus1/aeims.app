<?php

namespace AEIMS\Services;

use Exception;

/**
 * SSL Certificate Manager
 * Handles SSL certificate management and automation
 */
class SSLManager
{
    private string $sslPath;
    private string $certbotBinary;
    private string $acmeEmail;
    private string $environment;

    public function __construct()
    {
        $this->sslPath = $_ENV['NGINX_SSL_PATH'] ?? '/etc/nginx/ssl';
        $this->certbotBinary = $_ENV['CERTBOT_BINARY'] ?? 'certbot';
        $this->acmeEmail = $_ENV['ACME_EMAIL'] ?? 'admin@aeims.com';
        $this->environment = $_ENV['APP_ENV'] ?? 'development';
    }

    /**
     * Request SSL certificate for domain
     */
    public function requestCertificate(string $domain): bool
    {
        try {
            // In development, create self-signed certificates
            if ($this->environment === 'development') {
                return $this->createSelfSignedCertificate($domain);
            }

            // In production, use Let's Encrypt
            return $this->requestLetsEncryptCertificate($domain);
            
        } catch (Exception $e) {
            error_log("SSL certificate request failed for {$domain}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove SSL certificate for domain
     */
    public function removeCertificate(string $domain): bool
    {
        try {
            $certFile = "{$this->sslPath}/{$domain}.crt";
            $keyFile = "{$this->sslPath}/{$domain}.key";

            $removed = true;
            
            if (file_exists($certFile)) {
                $removed = $removed && unlink($certFile);
            }
            
            if (file_exists($keyFile)) {
                $removed = $removed && unlink($keyFile);
            }

            // If this was a Let's Encrypt certificate, revoke it
            if ($this->environment !== 'development') {
                $this->revokeLetsEncryptCertificate($domain);
            }

            return $removed;
            
        } catch (Exception $e) {
            error_log("SSL certificate removal failed for {$domain}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if certificate exists for domain
     */
    public function certificateExists(string $domain): bool
    {
        $certFile = "{$this->sslPath}/{$domain}.crt";
        $keyFile = "{$this->sslPath}/{$domain}.key";
        
        return file_exists($certFile) && file_exists($keyFile);
    }

    /**
     * Get certificate information
     */
    public function getCertificateInfo(string $domain): ?array
    {
        $certFile = "{$this->sslPath}/{$domain}.crt";
        
        if (!file_exists($certFile)) {
            return null;
        }

        try {
            $cert = file_get_contents($certFile);
            $certData = openssl_x509_parse($cert);
            
            if (!$certData) {
                return null;
            }

            return [
                'domain' => $domain,
                'subject' => $certData['subject'] ?? [],
                'issuer' => $certData['issuer'] ?? [],
                'valid_from' => $certData['validFrom_time_t'] ?? null,
                'valid_to' => $certData['validTo_time_t'] ?? null,
                'expires_in_days' => $certData['validTo_time_t'] ? ceil(($certData['validTo_time_t'] - time()) / (24 * 60 * 60)) : null,
                'is_expired' => $certData['validTo_time_t'] ? time() > $certData['validTo_time_t'] : null,
                'is_self_signed' => $this->isSelfSigned($certData)
            ];
            
        } catch (Exception $e) {
            error_log("Failed to parse certificate for {$domain}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create self-signed certificate for development
     */
    private function createSelfSignedCertificate(string $domain): bool
    {
        // Ensure SSL directory exists
        if (!is_dir($this->sslPath)) {
            mkdir($this->sslPath, 0755, true);
        }

        $keyFile = "{$this->sslPath}/{$domain}.key";
        $certFile = "{$this->sslPath}/{$domain}.crt";

        // Generate private key
        $keyCommand = "openssl genrsa -out {$keyFile} 2048 2>/dev/null";
        exec($keyCommand, $keyOutput, $keyReturnCode);

        if ($keyReturnCode !== 0) {
            return false;
        }

        // Generate certificate
        $certCommand = "openssl req -new -x509 -key {$keyFile} -out {$certFile} -days 365 " .
                      "-subj '/C=US/ST=State/L=City/O=AEIMS/OU=Development/CN={$domain}' 2>/dev/null";
        exec($certCommand, $certOutput, $certReturnCode);

        if ($certReturnCode !== 0) {
            // Clean up key file on failure
            if (file_exists($keyFile)) {
                unlink($keyFile);
            }
            return false;
        }

        // Set proper permissions
        chmod($keyFile, 0600);
        chmod($certFile, 0644);

        return true;
    }

    /**
     * Request Let's Encrypt certificate
     */
    private function requestLetsEncryptCertificate(string $domain): bool
    {
        // Ensure SSL directory exists
        if (!is_dir($this->sslPath)) {
            mkdir($this->sslPath, 0755, true);
        }

        $command = "{$this->certbotBinary} certonly --webroot " .
                  "-w /var/www/html " .
                  "-d {$domain} " .
                  "-d www.{$domain} " .
                  "--email {$this->acmeEmail} " .
                  "--agree-tos --non-interactive " .
                  "--cert-path {$this->sslPath}/{$domain}.crt " .
                  "--key-path {$this->sslPath}/{$domain}.key " .
                  "2>/dev/null";

        exec($command, $output, $returnCode);

        if ($returnCode === 0) {
            // Set proper permissions
            $keyFile = "{$this->sslPath}/{$domain}.key";
            $certFile = "{$this->sslPath}/{$domain}.crt";
            
            if (file_exists($keyFile)) chmod($keyFile, 0600);
            if (file_exists($certFile)) chmod($certFile, 0644);
            
            return true;
        }

        return false;
    }

    /**
     * Revoke Let's Encrypt certificate
     */
    private function revokeLetsEncryptCertificate(string $domain): bool
    {
        $certFile = "{$this->sslPath}/{$domain}.crt";
        
        if (!file_exists($certFile)) {
            return true; // Already removed
        }

        $command = "{$this->certbotBinary} revoke --cert-path {$certFile} --non-interactive 2>/dev/null";
        exec($command, $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Renew certificates that are about to expire
     */
    public function renewCertificates(): array
    {
        $results = [];
        
        // Get all certificate files
        if (!is_dir($this->sslPath)) {
            return $results;
        }

        $certFiles = glob("{$this->sslPath}/*.crt");
        
        foreach ($certFiles as $certFile) {
            $domain = basename($certFile, '.crt');
            $info = $this->getCertificateInfo($domain);
            
            if (!$info) {
                continue;
            }

            // Skip self-signed certificates
            if ($info['is_self_signed']) {
                continue;
            }

            // Renew if expires within 30 days
            if ($info['expires_in_days'] !== null && $info['expires_in_days'] <= 30) {
                $renewed = $this->renewCertificate($domain);
                $results[$domain] = [
                    'renewed' => $renewed,
                    'expires_in_days' => $info['expires_in_days'],
                    'was_expired' => $info['is_expired']
                ];
            }
        }

        return $results;
    }

    /**
     * Renew specific certificate
     */
    private function renewCertificate(string $domain): bool
    {
        if ($this->environment === 'development') {
            // Recreate self-signed certificate
            return $this->createSelfSignedCertificate($domain);
        }

        $command = "{$this->certbotBinary} renew --cert-name {$domain} --non-interactive 2>/dev/null";
        exec($command, $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Check if certificate is self-signed
     */
    private function isSelfSigned(array $certData): bool
    {
        $subject = $certData['subject'] ?? [];
        $issuer = $certData['issuer'] ?? [];
        
        // Compare subject and issuer
        return $subject === $issuer;
    }

    /**
     * Get SSL status for all domains
     */
    public function getSSLStatus(): array
    {
        $status = [
            'total_certificates' => 0,
            'valid_certificates' => 0,
            'expired_certificates' => 0,
            'expiring_soon' => 0,
            'self_signed' => 0,
            'certificates' => []
        ];

        if (!is_dir($this->sslPath)) {
            return $status;
        }

        $certFiles = glob("{$this->sslPath}/*.crt");
        $status['total_certificates'] = count($certFiles);

        foreach ($certFiles as $certFile) {
            $domain = basename($certFile, '.crt');
            $info = $this->getCertificateInfo($domain);
            
            if ($info) {
                $status['certificates'][$domain] = $info;
                
                if ($info['is_self_signed']) {
                    $status['self_signed']++;
                } elseif ($info['is_expired']) {
                    $status['expired_certificates']++;
                } elseif ($info['expires_in_days'] !== null && $info['expires_in_days'] <= 30) {
                    $status['expiring_soon']++;
                } else {
                    $status['valid_certificates']++;
                }
            }
        }

        return $status;
    }

    /**
     * Validate domain ownership (for production certificate requests)
     */
    public function validateDomainOwnership(string $domain): bool
    {
        // Create validation file
        $validationPath = '/var/www/html/.well-known/acme-challenge';
        $validationToken = bin2hex(random_bytes(16));
        $validationFile = "{$validationPath}/{$validationToken}";

        if (!is_dir($validationPath)) {
            mkdir($validationPath, 0755, true);
        }

        file_put_contents($validationFile, $validationToken);

        // Test if we can access the validation file
        $testUrl = "http://{$domain}/.well-known/acme-challenge/{$validationToken}";
        
        $ch = curl_init($testUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Clean up validation file
        if (file_exists($validationFile)) {
            unlink($validationFile);
        }

        return $httpCode === 200 && trim($response) === $validationToken;
    }
}