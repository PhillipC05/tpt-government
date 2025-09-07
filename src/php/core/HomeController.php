<?php
/**
 * TPT Government Platform - Home Controller
 *
 * Handles the main landing page and public content.
 */

namespace Core;

class HomeController extends Controller
{
    /**
     * Show home page
     *
     * @return void
     */
    public function index(): void
    {
        $data = [
            'title' => 'TPT Government Platform',
            'description' => 'Modern, AI-powered government services platform',
            'features' => [
                'AI Integration' => 'Powered by OpenAI, Anthropic, Gemini, and OpenRouter',
                'PWA Support' => 'Works offline and installs like a native app',
                'Multi-language' => 'Supports multiple languages for diverse communities',
                'Secure & Compliant' => 'GDPR compliant with comprehensive audit logging',
                'Modular Design' => 'Extensible plugin system for custom services'
            ],
            'services' => [
                [
                    'title' => 'Permit Applications',
                    'description' => 'Apply for business permits and licenses online',
                    'icon' => 'document'
                ],
                [
                    'title' => 'Benefit Programs',
                    'description' => 'Access government benefits and assistance programs',
                    'icon' => 'heart'
                ],
                [
                    'title' => 'Tax Services',
                    'description' => 'File taxes and manage tax-related services',
                    'icon' => 'calculator'
                ],
                [
                    'title' => 'Public Records',
                    'description' => 'Access government documents and public records',
                    'icon' => 'search'
                ]
            ],
            'authenticated' => $this->isAuthenticated()
        ];

        $this->view('home.index', $data);
    }

    /**
     * Show about page
     *
     * @return void
     */
    public function about(): void
    {
        $data = [
            'title' => 'About - TPT Government Platform',
            'mission' => 'To modernize government services through open-source technology, AI integration, and citizen-centric design.',
            'features' => [
                'Open Source' => 'MIT licensed, transparent, and community-driven',
                'AI-Powered' => 'Leverages multiple AI providers for intelligent automation',
                'Accessible' => 'WCAG 2.1 AA compliant for all citizens',
                'Secure' => 'Government-grade security with comprehensive audit trails',
                'Scalable' => 'Built to handle growing citizen demands'
            ]
        ];

        $this->view('home.about', $data);
    }

    /**
     * Show contact page
     *
     * @return void
     */
    public function contact(): void
    {
        $data = [
            'title' => 'Contact - TPT Government Platform',
            'contact_info' => [
                'email' => 'support@gov.local',
                'phone' => '+1 (555) 123-4567',
                'address' => 'Government Technology Center, Suite 100'
            ]
        ];

        $this->view('home.contact', $data);
    }

    /**
     * Handle contact form submission
     *
     * @return void
     */
    public function submitContact(): void
    {
        $validation = $this->validate([
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'subject' => 'required|min:5|max:200',
            'message' => 'required|min:10|max:1000'
        ]);

        if (!$validation['valid']) {
            $this->error('Validation failed', 422);
            return;
        }

        $contactData = [
            'name' => $this->request->post('name'),
            'email' => $this->request->post('email'),
            'subject' => $this->request->post('subject'),
            'message' => $this->request->post('message'),
            'ip_address' => $this->request->getClientIp(),
            'user_agent' => $this->request->getUserAgent(),
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Store contact message
        if ($this->database) {
            try {
                $this->database->insert('contact_messages', $contactData);
            } catch (\Exception $e) {
                error_log('Contact form error: ' . $e->getMessage());
            }
        }

        // Log the contact submission
        $this->logAction('contact_form_submitted', [
            'subject' => $contactData['subject'],
            'email' => $contactData['email']
        ]);

        $this->success([], 'Thank you for your message. We will get back to you soon.');
    }
}
