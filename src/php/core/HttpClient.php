<?php
/**
 * TPT Government Platform - HTTP Client
 *
 * Simple HTTP client for making API calls without external dependencies.
 */

namespace Core;

class HttpClient
{
    /**
     * Default timeout in seconds
     */
    private const DEFAULT_TIMEOUT = 30;

    /**
     * User agent string
     */
    private const USER_AGENT = 'TPT-Government-Platform/1.0';

    /**
     * Make GET request
     *
     * @param string $url Request URL
     * @param array $headers Request headers
     * @param array $options Additional options
     * @return array Response data
     */
    public function get(string $url, array $headers = [], array $options = []): array
    {
        return $this->request('GET', $url, null, $headers, $options);
    }

    /**
     * Make POST request
     *
     * @param string $url Request URL
     * @param mixed $data Request data
     * @param array $headers Request headers
     * @param array $options Additional options
     * @return array Response data
     */
    public function post(string $url, $data = null, array $headers = [], array $options = []): array
    {
        return $this->request('POST', $url, $data, $headers, $options);
    }

    /**
     * Make PUT request
     *
     * @param string $url Request URL
     * @param mixed $data Request data
     * @param array $headers Request headers
     * @param array $options Additional options
     * @return array Response data
     */
    public function put(string $url, $data = null, array $headers = [], array $options = []): array
    {
        return $this->request('PUT', $url, $data, $headers, $options);
    }

    /**
     * Make DELETE request
     *
     * @param string $url Request URL
     * @param array $headers Request headers
     * @param array $options Additional options
     * @return array Response data
     */
    public function delete(string $url, array $headers = [], array $options = []): array
    {
        return $this->request('DELETE', $url, null, $headers, $options);
    }

    /**
     * Make HTTP request
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param mixed $data Request data
     * @param array $headers Request headers
     * @param array $options Additional options
     * @return array Response data
     */
    public function request(string $method, string $url, $data = null, array $headers = [], array $options = []): array
    {
        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
        $followRedirects = $options['follow_redirects'] ?? true;
        $verifySsl = $options['verify_ssl'] ?? true;

        // Initialize cURL
        $ch = curl_init();

        // Set basic options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);

        // Set method
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default:
                curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        // Set data
        if ($data !== null) {
            if (is_array($data)) {
                $data = json_encode($data);
                $headers['Content-Type'] = 'application/json';
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        // Set headers
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        if (!empty($headerLines)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        }

        // Handle redirects
        if ($followRedirects) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        }

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        $errorCode = curl_errno($ch);

        curl_close($ch);

        // Handle errors
        if ($errorCode !== 0) {
            return [
                'success' => false,
                'error' => $error,
                'error_code' => $errorCode,
                'http_code' => $httpCode
            ];
        }

        return [
            'success' => true,
            'body' => $response,
            'http_code' => $httpCode,
            'content_type' => $contentType,
            'headers' => $this->parseResponseHeaders($response)
        ];
    }

    /**
     * Parse response headers from raw response
     *
     * @param string $response Raw response
     * @return array Parsed headers
     */
    private function parseResponseHeaders(string $response): array
    {
        $headers = [];
        $lines = explode("\n", $response);

        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, ':') !== false) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * Download file from URL
     *
     * @param string $url File URL
     * @param string $destination Local destination path
     * @param array $headers Request headers
     * @param array $options Additional options
     * @return array Result data
     */
    public function download(string $url, string $destination, array $headers = [], array $options = []): array
    {
        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;

        // Ensure destination directory exists
        $dir = dirname($destination);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Initialize cURL
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

        // Set headers
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        if (!empty($headerLines)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        }

        // Open file for writing
        $fp = fopen($destination, 'w');
        if (!$fp) {
            return [
                'success' => false,
                'error' => 'Cannot open destination file for writing'
            ];
        }

        curl_setopt($ch, CURLOPT_FILE, $fp);

        // Execute request
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errorCode = curl_errno($ch);

        fclose($fp);
        curl_close($ch);

        // Handle errors
        if ($errorCode !== 0) {
            if (file_exists($destination)) {
                unlink($destination);
            }
            return [
                'success' => false,
                'error' => $error,
                'error_code' => $errorCode,
                'http_code' => $httpCode
            ];
        }

        return [
            'success' => true,
            'file_path' => $destination,
            'file_size' => filesize($destination),
            'http_code' => $httpCode
        ];
    }

    /**
     * Test URL connectivity
     *
     * @param string $url URL to test
     * @param array $options Test options
     * @return array Test results
     */
    public function testConnection(string $url, array $options = []): array
    {
        $timeout = $options['timeout'] ?? 10;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $error = curl_error($ch);
        $errorCode = curl_errno($ch);

        curl_close($ch);

        return [
            'success' => $errorCode === 0,
            'http_code' => $httpCode,
            'response_time' => round($totalTime, 3),
            'error' => $errorCode !== 0 ? $error : null
        ];
    }
}
