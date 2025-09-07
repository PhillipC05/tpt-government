<?php
/**
 * TPT Government Platform - Dashboard Controller
 *
 * Handles user dashboard and main application interface.
 */

namespace Core;

class DashboardController extends Controller
{
    /**
     * Show user dashboard
     *
     * @return void
     */
    public function index(): void
    {
        if (!$this->isAuthenticated()) {
            $this->redirect('/login');
            return;
        }

        $user = $this->getCurrentUser();

        $data = [
            'title' => 'Dashboard - TPT Government Platform',
            'user' => $user,
            'recent_activities' => $this->getRecentActivities(),
            'pending_tasks' => $this->getPendingTasks(),
            'quick_actions' => [
                [
                    'title' => 'Apply for Permit',
                    'url' => '/services/permits',
                    'icon' => 'document-add'
                ],
                [
                    'title' => 'File Tax Return',
                    'url' => '/services/taxes',
                    'icon' => 'calculator'
                ],
                [
                    'title' => 'View Benefits',
                    'url' => '/services/benefits',
                    'icon' => 'heart'
                ],
                [
                    'title' => 'Access Records',
                    'url' => '/services/records',
                    'icon' => 'search'
                ]
            ],
            'notifications' => $this->getNotifications()
        ];

        $this->view('dashboard.index', $data);
    }

    /**
     * Get recent user activities
     *
     * @return array
     */
    private function getRecentActivities(): array
    {
        if (!$this->database) {
            return [
                [
                    'action' => 'Logged in',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'ip' => '127.0.0.1'
                ]
            ];
        }

        try {
            $userId = $this->getCurrentUserId();
            return $this->database->select(
                'SELECT action, data, timestamp, ip_address
                 FROM audit_logs
                 WHERE user_id = ?
                 ORDER BY timestamp DESC
                 LIMIT 10',
                [$userId]
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get pending tasks for user
     *
     * @return array
     */
    private function getPendingTasks(): array
    {
        // Mock pending tasks - in real implementation, this would query the database
        return [
            [
                'id' => 1,
                'title' => 'Complete Business Permit Application',
                'description' => 'Your business permit application is pending review',
                'due_date' => date('Y-m-d', strtotime('+7 days')),
                'priority' => 'high'
            ],
            [
                'id' => 2,
                'title' => 'Update Contact Information',
                'description' => 'Please verify your contact details are current',
                'due_date' => date('Y-m-d', strtotime('+14 days')),
                'priority' => 'medium'
            ]
        ];
    }

    /**
     * Get user notifications
     *
     * @return array
     */
    private function getNotifications(): array
    {
        // Mock notifications - in real implementation, this would query the database
        return [
            [
                'id' => 1,
                'title' => 'Application Status Update',
                'message' => 'Your permit application has been received and is under review',
                'type' => 'info',
                'read' => false,
                'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ],
            [
                'id' => 2,
                'title' => 'System Maintenance',
                'message' => 'Scheduled maintenance will occur tonight from 2-4 AM',
                'type' => 'warning',
                'read' => true,
                'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ]
        ];
    }

    /**
     * Mark notification as read
     *
     * @return void
     */
    public function markNotificationRead(): void
    {
        if (!$this->isAuthenticated()) {
            $this->error('Not authenticated', 401);
            return;
        }

        $notificationId = $this->request->post('notification_id');

        if (!$notificationId) {
            $this->error('Notification ID required', 422);
            return;
        }

        // In real implementation, update database
        $this->logAction('notification_read', ['notification_id' => $notificationId]);
        $this->success([], 'Notification marked as read');
    }

    /**
     * Get dashboard statistics
     *
     * @return void
     */
    public function stats(): void
    {
        if (!$this->isAuthenticated()) {
            $this->error('Not authenticated', 401);
            return;
        }

        $stats = [
            'applications_submitted' => 5,
            'applications_pending' => 2,
            'applications_approved' => 3,
            'messages_unread' => 1,
            'documents_uploaded' => 12
        ];

        $this->json(['stats' => $stats]);
    }
}
