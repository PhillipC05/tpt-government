<?php
/**
 * TPT Government Platform - AI Service Integration
 *
 * Comprehensive AI service integration supporting multiple providers.
 * Handles OpenAI, Anthropic, Google Gemini, and OpenRouter APIs.
 */

namespace Core;

class AIService
{
    /**
     * Available AI providers
     */
    private const PROVIDERS = [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic',
        'gemini' => 'Google Gemini',
        'openrouter' => 'OpenRouter'
    ];

    /**
     * API configurations
     */
    private array $configs;

    /**
     * HTTP client for API calls
     */
    private HttpClient $httpClient;

    /**
     * Cache instance
     */
    private ?Cache $cache = null;

    /**
     * Constructor
     */
    public function __construct(array $configs = [])
    {
        $this->configs = $configs;
        $this->httpClient = new HttpClient();

        // Initialize cache if available
        if (class_exists('Cache')) {
            $this->cache = new Cache();
        }
    }

    /**
     * Generate text using AI
     *
     * @param string $prompt The text prompt
     * @param array $options Additional options
     * @return array Result with generated text
     */
    public function generateText(string $prompt, array $options = []): array
    {
        $cacheKey = $this->getCacheKey('text', $prompt, $options);

        // Check cache first
        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        $provider = $options['provider'] ?? $this->getDefaultProvider();
        $result = $this->callProvider($provider, 'generateText', [
            'prompt' => $prompt,
            'options' => $options
        ]);

        // Cache successful results
        if ($this->cache && isset($result['success']) && $result['success']) {
            $this->cache->set($cacheKey, $result, 3600); // Cache for 1 hour
        }

        return $result;
    }

    /**
     * Analyze document content
     *
     * @param string $content Document content
     * @param array $options Analysis options
     * @return array Analysis results
     */
    public function analyzeDocument(string $content, array $options = []): array
    {
        $cacheKey = $this->getCacheKey('document', md5($content), $options);

        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        $provider = $options['provider'] ?? $this->getDefaultProvider();
        $result = $this->callProvider($provider, 'analyzeDocument', [
            'content' => $content,
            'options' => $options
        ]);

        if ($this->cache && isset($result['success']) && $result['success']) {
            $this->cache->set($cacheKey, $result, 7200); // Cache for 2 hours
        }

        return $result;
    }

    /**
     * Classify content
     *
     * @param string $content Content to classify
     * @param array $categories Classification categories
     * @param array $options Additional options
     * @return array Classification results
     */
    public function classifyContent(string $content, array $categories, array $options = []): array
    {
        $cacheKey = $this->getCacheKey('classify', md5($content . json_encode($categories)), $options);

        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        $provider = $options['provider'] ?? $this->getDefaultProvider();
        $result = $this->callProvider($provider, 'classifyContent', [
            'content' => $content,
            'categories' => $categories,
            'options' => $options
        ]);

        if ($this->cache && isset($result['success']) && $result['success']) {
            $this->cache->set($cacheKey, $result, 3600);
        }

        return $result;
    }

    /**
     * Extract information from text
     *
     * @param string $text Text to extract from
     * @param array $fields Fields to extract
     * @param array $options Extraction options
     * @return array Extracted information
     */
    public function extractInformation(string $text, array $fields, array $options = []): array
    {
        $cacheKey = $this->getCacheKey('extract', md5($text . json_encode($fields)), $options);

        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        $provider = $options['provider'] ?? $this->getDefaultProvider();
        $result = $this->callProvider($provider, 'extractInformation', [
            'text' => $text,
            'fields' => $fields,
            'options' => $options
        ]);

        if ($this->cache && isset($result['success']) && $result['success']) {
            $this->cache->set($cacheKey, $result, 3600);
        }

        return $result;
    }

    /**
     * Generate embeddings for text
     *
     * @param string $text Text to embed
     * @param array $options Embedding options
     * @return array Embedding vector
     */
    public function generateEmbeddings(string $text, array $options = []): array
    {
        $cacheKey = $this->getCacheKey('embeddings', md5($text), $options);

        if ($this->cache && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        $provider = $options['provider'] ?? $this->getDefaultProvider();
        $result = $this->callProvider($provider, 'generateEmbeddings', [
            'text' => $text,
            'options' => $options
        ]);

        if ($this->cache && isset($result['success']) && $result['success']) {
            $this->cache->set($cacheKey, $result, 86400); // Cache for 24 hours
        }

        return $result;
    }

    /**
     * Moderate content for safety
     *
     * @param string $content Content to moderate
     * @param array $options Moderation options
     * @return array Moderation results
     */
    public function moderateContent(string $content, array $options = []): array
    {
        $provider = $options['provider'] ?? $this->getDefaultProvider();
        return $this->callProvider($provider, 'moderateContent', [
            'content' => $content,
            'options' => $options
        ]);
    }

    /**
     * Call specific AI provider
     *
     * @param string $provider Provider name
     * @param string $method Method to call
     * @param array $params Parameters
     * @return array Result from provider
     */
    private function callProvider(string $provider, string $method, array $params): array
    {
        if (!isset($this->configs[$provider])) {
            return [
                'success' => false,
                'error' => "Provider '{$provider}' not configured"
            ];
        }

        $config = $this->configs[$provider];

        try {
            switch ($provider) {
                case 'openai':
                    return $this->callOpenAI($method, $params, $config);
                case 'anthropic':
                    return $this->callAnthropic($method, $params, $config);
                case 'gemini':
                    return $this->callGemini($method, $params, $config);
                case 'openrouter':
                    return $this->callOpenRouter($method, $params, $config);
                default:
                    return [
                        'success' => false,
                        'error' => "Unknown provider '{$provider}'"
                    ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "AI service error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI(string $method, array $params, array $config): array
    {
        $baseUrl = 'https://api.openai.com/v1';
        $headers = [
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json'
        ];

        switch ($method) {
            case 'generateText':
                $data = [
                    'model' => $params['options']['model'] ?? 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'user', 'content' => $params['prompt']]
                    ],
                    'max_tokens' => $params['options']['max_tokens'] ?? 1000,
                    'temperature' => $params['options']['temperature'] ?? 0.7
                ];

                $response = $this->httpClient->post($baseUrl . '/chat/completions', $data, $headers);
                $result = json_decode($response['body'], true);

                if (isset($result['choices'][0]['message']['content'])) {
                    return [
                        'success' => true,
                        'text' => $result['choices'][0]['message']['content'],
                        'usage' => $result['usage'] ?? null,
                        'provider' => 'openai'
                    ];
                }
                break;

            case 'generateEmbeddings':
                $data = [
                    'model' => $params['options']['model'] ?? 'text-embedding-ada-002',
                    'input' => $params['text']
                ];

                $response = $this->httpClient->post($baseUrl . '/embeddings', $data, $headers);
                $result = json_decode($response['body'], true);

                if (isset($result['data'][0]['embedding'])) {
                    return [
                        'success' => true,
                        'embeddings' => $result['data'][0]['embedding'],
                        'usage' => $result['usage'] ?? null,
                        'provider' => 'openai'
                    ];
                }
                break;
        }

        return [
            'success' => false,
            'error' => 'OpenAI API call failed',
            'provider' => 'openai'
        ];
    }

    /**
     * Call Anthropic API
     */
    private function callAnthropic(string $method, array $params, array $config): array
    {
        $baseUrl = 'https://api.anthropic.com/v1';
        $headers = [
            'x-api-key' => $config['api_key'],
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01'
        ];

        switch ($method) {
            case 'generateText':
                $data = [
                    'model' => $params['options']['model'] ?? 'claude-3-sonnet-20240229',
                    'max_tokens' => $params['options']['max_tokens'] ?? 1000,
                    'messages' => [
                        ['role' => 'user', 'content' => $params['prompt']]
                    ]
                ];

                $response = $this->httpClient->post($baseUrl . '/messages', $data, $headers);
                $result = json_decode($response['body'], true);

                if (isset($result['content'][0]['text'])) {
                    return [
                        'success' => true,
                        'text' => $result['content'][0]['text'],
                        'usage' => $result['usage'] ?? null,
                        'provider' => 'anthropic'
                    ];
                }
                break;
        }

        return [
            'success' => false,
            'error' => 'Anthropic API call failed',
            'provider' => 'anthropic'
        ];
    }

    /**
     * Call Google Gemini API
     */
    private function callGemini(string $method, array $params, array $config): array
    {
        $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
        $model = $params['options']['model'] ?? 'gemini-pro';

        switch ($method) {
            case 'generateText':
                $data = [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $params['prompt']]
                            ]
                        ]
                    ]
                ];

                $url = $baseUrl . '/models/' . $model . ':generateContent?key=' . $config['api_key'];
                $response = $this->httpClient->post($url, $data, [
                    'Content-Type' => 'application/json'
                ]);

                $result = json_decode($response['body'], true);

                if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                    return [
                        'success' => true,
                        'text' => $result['candidates'][0]['content']['parts'][0]['text'],
                        'provider' => 'gemini'
                    ];
                }
                break;
        }

        return [
            'success' => false,
            'error' => 'Gemini API call failed',
            'provider' => 'gemini'
        ];
    }

    /**
     * Call OpenRouter API
     */
    private function callOpenRouter(string $method, array $params, array $config): array
    {
        $baseUrl = 'https://openrouter.ai/api/v1';
        $headers = [
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json'
        ];

        switch ($method) {
            case 'generateText':
                $data = [
                    'model' => $params['options']['model'] ?? 'anthropic/claude-3-haiku',
                    'messages' => [
                        ['role' => 'user', 'content' => $params['prompt']]
                    ],
                    'max_tokens' => $params['options']['max_tokens'] ?? 1000
                ];

                $response = $this->httpClient->post($baseUrl . '/chat/completions', $data, $headers);
                $result = json_decode($response['body'], true);

                if (isset($result['choices'][0]['message']['content'])) {
                    return [
                        'success' => true,
                        'text' => $result['choices'][0]['message']['content'],
                        'usage' => $result['usage'] ?? null,
                        'provider' => 'openrouter'
                    ];
                }
                break;
        }

        return [
            'success' => false,
            'error' => 'OpenRouter API call failed',
            'provider' => 'openrouter'
        ];
    }

    /**
     * Get default provider
     */
    private function getDefaultProvider(): string
    {
        // Return first configured provider
        foreach (self::PROVIDERS as $key => $name) {
            if (isset($this->configs[$key])) {
                return $key;
            }
        }
        return 'openai'; // Fallback
    }

    /**
     * Generate cache key
     */
    private function getCacheKey(string $type, string $content, array $options): string
    {
        $key = 'ai_' . $type . '_' . md5($content . json_encode($options));
        return substr($key, 0, 250); // Limit key length
    }

    /**
     * Get available providers
     */
    public function getProviders(): array
    {
        $available = [];
        foreach (self::PROVIDERS as $key => $name) {
            $available[$key] = [
                'name' => $name,
                'configured' => isset($this->configs[$key])
            ];
        }
        return $available;
    }

    /**
     * Test provider connection
     */
    public function testProvider(string $provider): array
    {
        if (!isset($this->configs[$provider])) {
            return ['success' => false, 'error' => 'Provider not configured'];
        }

        try {
            $result = $this->generateText('Hello, test message', [
                'provider' => $provider,
                'max_tokens' => 10
            ]);

            return [
                'success' => $result['success'] ?? false,
                'response_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)),
                'provider' => $provider
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $provider
            ];
        }
    }
}
