<?php
/**
 * TPT Government Platform - HTTP Response Handler
 *
 * Handles HTTP responses with proper formatting and security headers.
 */

namespace Core;

class Response
{
    /**
     * HTTP status codes
     */
    private const STATUS_CODES = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable'
    ];

    /**
     * Response headers
     */
    private array $headers = [];

    /**
     * Response status code
     */
    private int $statusCode = 200;

    /**
     * Response content
     */
    private string $content = '';

    /**
     * Content type
     */
    private string $contentType = 'text/html';

    /**
     * Constructor
     */
    public function __construct()
    {
        // Set default security headers
        $this->setHeader('X-Content-Type-Options', 'nosniff');
        $this->setHeader('X-Frame-Options', 'DENY');
        $this->setHeader('X-XSS-Protection', '1; mode=block');
        $this->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * Set HTTP status code
     *
     * @param int $code The status code
     * @return self
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Get HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set a response header
     *
     * @param string $name The header name
     * @param string $value The header value
     * @return self
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Get a response header
     *
     * @param string $name The header name
     * @return string|null
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Get all response headers
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set content type
     *
     * @param string $type The content type
     * @return self
     */
    public function setContentType(string $type): self
    {
        $this->contentType = $type;
        $this->setHeader('Content-Type', $type);
        return $this;
    }

    /**
     * Get content type
     *
     * @return string
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * Set response content
     *
     * @param string $content The response content
     * @return self
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Get response content
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Send HTML response
     *
     * @param string $html The HTML content
     * @param int $statusCode The status code
     * @return void
     */
    public function html(string $html, int $statusCode = 200): void
    {
        $this->setContentType('text/html; charset=utf-8');
        $this->setStatusCode($statusCode);
        $this->setContent($html);
        $this->send();
    }

    /**
     * Send JSON response
     *
     * @param mixed $data The data to encode as JSON
     * @param int $statusCode The status code
     * @param int $options JSON encoding options
     * @return void
     */
    public function json($data, int $statusCode = 200, int $options = 0): void
    {
        $json = json_encode($data, $options | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            // JSON encoding failed
            $this->error('JSON encoding failed', 500);
            return;
        }

        $this->setContentType('application/json; charset=utf-8');
        $this->setStatusCode($statusCode);
        $this->setContent($json);
        $this->send();
    }

    /**
     * Send plain text response
     *
     * @param string $text The text content
     * @param int $statusCode The status code
     * @return void
     */
    public function text(string $text, int $statusCode = 200): void
    {
        $this->setContentType('text/plain; charset=utf-8');
        $this->setStatusCode($statusCode);
        $this->setContent($text);
        $this->send();
    }

    /**
     * Send XML response
     *
     * @param string $xml The XML content
     * @param int $statusCode The status code
     * @return void
     */
    public function xml(string $xml, int $statusCode = 200): void
    {
        $this->setContentType('application/xml; charset=utf-8');
        $this->setStatusCode($statusCode);
        $this->setContent($xml);
        $this->send();
    }

    /**
     * Send file download
     *
     * @param string $filePath The file path
     * @param string|null $filename The download filename
     * @param string $mimeType The MIME type
     * @return void
     */
    public function download(string $filePath, ?string $filename = null, string $mimeType = 'application/octet-stream'): void
    {
        if (!file_exists($filePath)) {
            $this->error('File not found', 404);
            return;
        }

        if ($filename === null) {
            $filename = basename($filePath);
        }

        $this->setHeader('Content-Type', $mimeType);
        $this->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $this->setHeader('Content-Length', (string)filesize($filePath));
        $this->setHeader('Cache-Control', 'private, max-age=0, must-revalidate');
        $this->setHeader('Pragma', 'public');

        $this->setContent(file_get_contents($filePath));
        $this->send();
    }

    /**
     * Send redirect response
     *
     * @param string $url The redirect URL
     * @param int $statusCode The redirect status code (301, 302, etc.)
     * @return void
     */
    public function redirect(string $url, int $statusCode = 302): void
    {
        $this->setHeader('Location', $url);
        $this->setStatusCode($statusCode);
        $this->setContent('');
        $this->send();
    }

    /**
     * Send error response
     *
     * @param string $message The error message
     * @param int $statusCode The error status code
     * @return void
     */
    public function error(string $message, int $statusCode = 500): void
    {
        $this->setStatusCode($statusCode);

        if (strpos($this->contentType, 'application/json') === 0) {
            $this->json([
                'error' => true,
                'message' => $message,
                'status_code' => $statusCode
            ], $statusCode);
        } else {
            $this->html('<h1>Error ' . $statusCode . '</h1><p>' . htmlspecialchars($message) . '</p>', $statusCode);
        }
    }

    /**
     * Send success response
     *
     * @param mixed $data The success data
     * @param string $message The success message
     * @param int $statusCode The status code
     * @return void
     */
    public function success($data = null, string $message = 'Success', int $statusCode = 200): void
    {
        $response = [
            'success' => true,
            'message' => $message,
            'status_code' => $statusCode
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        $this->json($response, $statusCode);
    }

    /**
     * Send the response
     *
     * @return void
     */
    public function send(): void
    {
        // Prevent multiple sends
        static $sent = false;
        if ($sent) {
            return;
        }
        $sent = true;

        // Set status code
        $statusText = self::STATUS_CODES[$this->statusCode] ?? 'Unknown Status';
        header('HTTP/1.1 ' . $this->statusCode . ' ' . $statusText, true, $this->statusCode);

        // Send headers
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        // Send content
        echo $this->content;

        // Exit to prevent further output
        exit(0);
    }

    /**
     * Clear the response
     *
     * @return self
     */
    public function clear(): self
    {
        $this->content = '';
        $this->headers = [];
        $this->statusCode = 200;
        return $this;
    }

    /**
     * Set cache headers
     *
     * @param int $maxAge Maximum age in seconds
     * @param bool $public Whether to allow public caching
     * @return self
     */
    public function cache(int $maxAge = 3600, bool $public = true): self
    {
        $cacheControl = $public ? 'public' : 'private';
        $cacheControl .= ', max-age=' . $maxAge;

        $this->setHeader('Cache-Control', $cacheControl);
        $this->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');

        return $this;
    }

    /**
     * Set no-cache headers
     *
     * @return self
     */
    public function noCache(): self
    {
        $this->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->setHeader('Pragma', 'no-cache');
        $this->setHeader('Expires', '0');

        return $this;
    }

    /**
     * Set CORS headers
     *
     * @param string $origin Allowed origin
     * @param array $methods Allowed methods
     * @param array $headers Allowed headers
     * @return self
     */
    public function cors(string $origin = '*', array $methods = ['GET', 'POST', 'PUT', 'DELETE'], array $headers = ['Content-Type', 'Authorization']): self
    {
        $this->setHeader('Access-Control-Allow-Origin', $origin);
        $this->setHeader('Access-Control-Allow-Methods', implode(', ', $methods));
        $this->setHeader('Access-Control-Allow-Headers', implode(', ', $headers));
        $this->setHeader('Access-Control-Allow-Credentials', 'true');

        return $this;
    }
}
