<?php

namespace AEIMS\Lib;

/**
 * Microservice Client
 * Handles HTTP communication with AEIMS microservices
 */
class MicroserviceClient {
    private $host;
    private $port;
    private $baseUrl;
    private $timeout = 10;

    public function __construct($host, $port) {
        $this->host = $host;
        $this->port = $port;
        $this->baseUrl = "http://{$host}:{$port}";
    }

    /**
     * Perform GET request
     */
    public function get($endpoint, array $params = []) {
        $url = $this->baseUrl . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $this->makeRequest('GET', $url);
    }

    /**
     * Perform POST request
     */
    public function post($endpoint, array $data = []) {
        $url = $this->baseUrl . $endpoint;
        return $this->makeRequest('POST', $url, $data);
    }

    /**
     * Perform PUT request
     */
    public function put($endpoint, array $data = []) {
        $url = $this->baseUrl . $endpoint;
        return $this->makeRequest('PUT', $url, $data);
    }

    /**
     * Perform DELETE request
     */
    public function delete($endpoint) {
        $url = $this->baseUrl . $endpoint;
        return $this->makeRequest('DELETE', $url);
    }

    /**
     * Make HTTP request using cURL
     */
    private function makeRequest($method, $url, array $data = []) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        // Set custom headers
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Client: AEIMS-Web'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Set method and data
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;

            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;

            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;

            case 'GET':
            default:
                // GET is default, no additional options needed
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new \Exception("Microservice request failed: {$error}");
        }

        if ($httpCode >= 500) {
            throw new \Exception("Microservice error (HTTP {$httpCode})");
        }

        $result = json_decode($response, true);

        if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response from microservice");
        }

        return $result;
    }

    /**
     * Check if microservice is available
     */
    public function healthCheck() {
        try {
            $result = $this->get('/health');
            return $result['status'] ?? false === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Set request timeout in seconds
     */
    public function setTimeout($seconds) {
        $this->timeout = $seconds;
    }
}
