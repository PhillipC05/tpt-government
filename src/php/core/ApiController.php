<?php
/**
 * TPT Government Platform - API Controller
 *
 * Handles API health checks and general API functionality.
 */

namespace Core;

class ApiController extends Controller
{
    /**
     * Health check endpoint
     *
     * @return void
     */
    public function health(): void
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'version' => '1.0.0',
            'services' => [
                'database' => $this->database ? 'connected' : 'disconnected',
                'session' => Session::isAuthenticated() ? 'authenticated' : 'anonymous'
            ]
        ];

        $this->json($health);
    }

    /**
     * Get API information
     *
     * @return void
     */
    public function info(): void
    {
        $info = [
            'name' => 'TPT Government Platform API',
            'version' => '1.0.0',
            'description' => 'Open-source government platform with AI integration',
            'endpoints' => [
                'GET /api/health' => 'Health check',
                'POST /api/auth/login' => 'User authentication',
                'GET /api/user/profile' => 'User profile (authenticated)',
                'GET /api/services' => 'Available government services',
                'POST /api/webhooks' => 'Webhook management'
            ],
            'features' => [
                'AI Integration' => 'OpenAI, Anthropic, Gemini, OpenRouter',
                'PWA Support' => 'Progressive Web App capabilities',
                'Multi-language' => 'Internationalization support',
                'Audit Logging' => 'Complete activity tracking',
                'Zapier Integration' => 'Workflow automation'
            ]
        ];

        $this->json($info);
    }

    /**
     * Get available services
     *
     * @return void
     */
    public function services(): void
    {
        $services = [
            [
                'id' => 'permits',
                'name' => 'Permit Applications',
                'description' => 'Apply for business permits and licenses',
                'category' => 'Business Services'
            ],
            [
                'id' => 'benefits',
                'name' => 'Benefit Applications',
                'description' => 'Apply for government benefits and assistance',
                'category' => 'Social Services'
            ],
            [
                'id' => 'taxes',
                'name' => 'Tax Services',
                'description' => 'File taxes and access tax information',
                'category' => 'Financial Services'
            ],
            [
                'id' => 'records',
                'name' => 'Public Records',
                'description' => 'Access government documents and records',
                'category' => 'Information Services'
            ],
            [
                'id' => 'reports',
                'name' => 'Reporting',
                'description' => 'Generate and submit reports',
                'category' => 'Administrative Services'
            ]
        ];

        $this->json(['services' => $services]);
    }

    /**
     * Get API statistics
     *
     * @return void
     */
    public function stats(): void
    {
        if (!$this->hasRole('admin')) {
            $this->error('Access denied', 403);
            return;
        }

        $stats = [
            'total_users' => 0,
            'active_sessions' => 0,
            'api_requests_today' => 0,
            'database_connections' => $this->database ? 1 : 0,
            'uptime' => time() - ($_SERVER['REQUEST_TIME'] ?? time())
        ];

        // Get real statistics from database if available
        if ($this->database) {
            try {
                $userCount = $this->database->selectOne('SELECT COUNT(*) as count FROM users');
                $stats['total_users'] = $userCount['count'] ?? 0;
            } catch (\Exception $e) {
                // Database not set up yet
            }
        }

        $this->json($stats);
    }
}
