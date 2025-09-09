<?php
/**
 * TPT Government Platform - Building Consent API Controller
 *
 * REST API controller for building consent operations
 */

namespace Modules\BuildingConsents\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Auth;
use Modules\BuildingConsents\Managers\BuildingConsentApplicationManager;
use Modules\BuildingConsents\Managers\BuildingInspectionManager;
use Modules\BuildingConsents\Managers\BuildingCertificateManager;
use Modules\BuildingConsents\Managers\BuildingFeeManager;
use Modules\BuildingConsents\Managers\BuildingComplianceManager;
use Exception;

class BuildingConsentApiController extends Controller
{
    private BuildingConsentApplicationManager $applicationManager;
    private BuildingInspectionManager $inspectionManager;
    private BuildingCertificateManager $certificateManager;
    private BuildingFeeManager $feeManager;
    private BuildingComplianceManager $complianceManager;

    public function __construct(
        BuildingConsentApplicationManager $applicationManager,
        BuildingInspectionManager $inspectionManager,
        BuildingCertificateManager $certificateManager,
        BuildingFeeManager $feeManager,
        BuildingComplianceManager $complianceManager
    ) {
        $this->applicationManager = $applicationManager;
        $this->inspectionManager = $inspectionManager;
        $this->certificateManager = $certificateManager;
        $this->feeManager = $feeManager;
        $this->complianceManager = $complianceManager;
    }

    /**
     * Get building consents
     */
    public function getBuildingConsents(Request $request): Response
    {
        try {
            $filters = $request->getQueryParams();
            $result = $this->applicationManager->getApplications($filters);

            if (!$result['success']) {
                return $this->jsonResponse(['error' => $result['error']], 500);
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => $result['data'],
                'count' => $result['count'],
                'timestamp' => date('c')
            ]);

        } catch (Exception $e) {
            error_log("Error getting building consents: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get building consent by ID
     */
    public function getBuildingConsent(Request $request, string $applicationId): Response
    {
        try {
            $application = $this->applicationManager->getApplication($applicationId);

            if (!$application) {
                return $this->jsonResponse(['error' => 'Application not found'], 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => $application,
                'timestamp' => date('c')
            ]);

        } catch (Exception $e) {
            error_log("Error getting building consent: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Create building consent application
     */
    public function createBuildingConsent(Request $request): Response
    {
        try {
            $data = $request->getJsonBody();

            if (!$data) {
                return $this->jsonResponse(['error' => 'Invalid JSON data'], 400);
            }

            // Add user ID from authentication
            $user = Auth::getCurrentUser();
            $data['applicant_id'] = $user['id'];

            $result = $this->applicationManager->createApplication($data);

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'errors' => $result['errors']
                ], 400);
            }

            return $this->jsonResponse($result, 201);

        } catch (Exception $e) {
            error_log("Error creating building consent: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Update building consent
     */
    public function updateBuildingConsent(Request $request, string $applicationId): Response
    {
        try {
            $data = $request->getJsonBody();

            if (!$data) {
                return $this->jsonResponse(['error' => 'Invalid JSON data'], 400);
            }

            $result = $this->applicationManager->updateApplication($applicationId, $data);

            if (!$result) {
                return $this->jsonResponse(['error' => 'Failed to update application'], 500);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Application updated successfully'
            ]);

        } catch (Exception $e) {
            error_log("Error updating building consent: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Submit building consent
     */
    public function submitBuildingConsent(Request $request, string $applicationId): Response
    {
        try {
            $result = $this->applicationManager->submitApplication($applicationId);

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

            return $this->jsonResponse($result);

        } catch (Exception $e) {
            error_log("Error submitting building consent: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Review building consent
     */
    public function reviewBuildingConsent(Request $request, string $applicationId): Response
    {
        try {
            $data = $request->getJsonBody();

            if (!$data) {
                return $this->jsonResponse(['error' => 'Invalid JSON data'], 400);
            }

            $result = $this->applicationManager->reviewApplication($applicationId, $data);

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

            return $this->jsonResponse($result);

        } catch (Exception $e) {
            error_log("Error reviewing building consent: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Approve building consent
     */
    public function approveBuildingConsent(Request $request, string $applicationId): Response
    {
        try {
            $data = $request->getJsonBody() ?: [];

            $result = $this->applicationManager->approveApplication($applicationId, $data);

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

            return $this->jsonResponse($result);

        } catch (Exception $e) {
            error_log("Error approving building consent: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Reject building consent
     */
    public function rejectBuildingConsent(Request $request, string $applicationId): Response
    {
        try {
            $data = $request->getJsonBody();

            if (!$data || !isset($data['reason'])) {
                return $this->jsonResponse(['error' => 'Rejection reason required'], 400);
            }

            $result = $this->applicationManager->rejectApplication($applicationId, $data['reason']);

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

            return $this->jsonResponse($result);

        } catch (Exception $e) {
            error_log("Error rejecting building consent: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get inspections
     */
    public function getInspections(Request $request): Response
    {
        try {
            $filters = $request->getQueryParams();
            $result = $this->inspectionManager->getInspections($filters);

            if (!$result['success']) {
                return $this->jsonResponse(['error' => $result['error']], 500);
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => $result['data'],
                'count' => $result['count'],
                'timestamp' => date('c')
            ]);

        } catch (Exception $e) {
            error_log("Error getting inspections: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Schedule inspection
     */
    public function scheduleInspection(Request $request): Response
    {
        try {
            $data = $request->getJsonBody();

            if (!$data) {
                return $this->jsonResponse(['error' => 'Invalid JSON data'], 400);
            }

            $result = $this->inspectionManager->scheduleInspection($data);

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

            return $this->jsonResponse($result, 201);

        } catch (Exception $e) {
            error_log("Error scheduling inspection: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Complete inspection
     */
    public function completeInspection(Request $request, int $inspectionId): Response
    {
        try {
            $data = $request->getJsonBody();

            if (!$data) {
                return $this->jsonResponse(['error' => 'Invalid JSON data'], 400);
            }

            $result = $this->inspectionManager->completeInspection($inspectionId, $data);

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

            return $this->jsonResponse($result);

        } catch (Exception $e) {
            error_log("Error completing inspection: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get certificates
     */
    public function getCertificates(Request $request): Response
    {
        try {
            $filters = $request->getQueryParams();
            $result = $this->certificateManager->getCertificates($filters);

            if (!$result['success']) {
                return $this->jsonResponse(['error' => $result['error']], 500);
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => $result['data'],
                'count' => $result['count'],
                'timestamp' => date('c')
            ]);

        } catch (Exception $e) {
            error_log("Error getting certificates: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Issue certificate
     */
    public function issueCertificate(Request $request, string $applicationId): Response
    {
        try {
            $data = $request->getJsonBody();

            if (!$data || !isset($data['certificate_type'])) {
                return $this->jsonResponse(['error' => 'Certificate type required'], 400);
            }

            $result = $this->certificateManager->issueCertificate($applicationId, $data['certificate_type'], $data);

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

            return $this->jsonResponse($result, 201);

        } catch (Exception $e) {
            error_log("Error issuing certificate: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get fees
     */
    public function getFees(Request $request): Response
    {
        try {
            $filters = $request->getQueryParams();
            $result = $this->feeManager->getFees($filters);

            if (!$result['success']) {
                return $this->jsonResponse(['error' => $result['error']], 500);
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => $result['data'],
                'count' => $result['count'],
                'timestamp' => date('c')
            ]);

        } catch (Exception $e) {
            error_log("Error getting fees: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Process fee payment
     */
    public function processFeePayment(Request $request, string $invoiceNumber): Response
    {
        try {
            $data = $request->getJsonBody();

            if (!$data) {
                return $this->jsonResponse(['error' => 'Invalid JSON data'], 400);
            }

            $result = $this->feeManager->processFeePayment($invoiceNumber, $data);

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

            return $this->jsonResponse($result);

        } catch (Exception $e) {
            error_log("Error processing fee payment: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get compliance requirements
     */
    public function getComplianceRequirements(Request $request): Response
    {
        try {
            $filters = $request->getQueryParams();
            $result = $this->complianceManager->getComplianceRequirements($filters);

            if (!$result['success']) {
                return $this->jsonResponse(['error' => $result['error']], 500);
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => $result['data'],
                'count' => $result['count'],
                'timestamp' => date('c')
            ]);

        } catch (Exception $e) {
            error_log("Error getting compliance requirements: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Update compliance status
     */
    public function updateComplianceStatus(Request $request, int $complianceId): Response
    {
        try {
            $data = $request->getJsonBody();

            if (!$data || !isset($data['status'])) {
                return $this->jsonResponse(['error' => 'Status required'], 400);
            }

            $evidence = $data['evidence'] ?? [];
            $result = $this->complianceManager->updateComplianceStatus($complianceId, $data['status'], $evidence);

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

            return $this->jsonResponse($result);

        } catch (Exception $e) {
            error_log("Error updating compliance status: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats(Request $request): Response
    {
        try {
            // Get various statistics for dashboard
            $stats = [
                'applications' => $this->getApplicationStats(),
                'inspections' => $this->getInspectionStats(),
                'certificates' => $this->getCertificateStats(),
                'fees' => $this->getFeeStats(),
                'compliance' => $this->getComplianceStats()
            ];

            return $this->jsonResponse([
                'success' => true,
                'data' => $stats,
                'timestamp' => date('c')
            ]);

        } catch (Exception $e) {
            error_log("Error getting dashboard stats: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get application statistics
     */
    private function getApplicationStats(): array
    {
        try {
            // This would query the database for application statistics
            return [
                'total' => 0,
                'draft' => 0,
                'submitted' => 0,
                'approved' => 0,
                'rejected' => 0,
                'this_month' => 0
            ];
        } catch (Exception $e) {
            return ['error' => 'Failed to get application stats'];
        }
    }

    /**
     * Get inspection statistics
     */
    private function getInspectionStats(): array
    {
        try {
            // This would query the database for inspection statistics
            return [
                'total' => 0,
                'scheduled' => 0,
                'completed' => 0,
                'overdue' => 0,
                'this_month' => 0
            ];
        } catch (Exception $e) {
            return ['error' => 'Failed to get inspection stats'];
        }
    }

    /**
     * Get certificate statistics
     */
    private function getCertificateStats(): array
    {
        try {
            // This would query the database for certificate statistics
            return [
                'total' => 0,
                'active' => 0,
                'expired' => 0,
                'this_month' => 0
            ];
        } catch (Exception $e) {
            return ['error' => 'Failed to get certificate stats'];
        }
    }

    /**
     * Get fee statistics
     */
    private function getFeeStats(): array
    {
        try {
            // This would query the database for fee statistics
            return [
                'total_revenue' => 0,
                'paid_fees' => 0,
                'unpaid_fees' => 0,
                'overdue_fees' => 0,
                'this_month' => 0
            ];
        } catch (Exception $e) {
            return ['error' => 'Failed to get fee stats'];
        }
    }

    /**
     * Get compliance statistics
     */
    private function getComplianceStats(): array
    {
        try {
            // This would query the database for compliance statistics
            return [
                'total_requirements' => 0,
                'completed' => 0,
                'overdue' => 0,
                'compliance_rate' => 0
            ];
        } catch (Exception $e) {
            return ['error' => 'Failed to get compliance stats'];
        }
    }

    /**
     * Generate reports
     */
    public function generateReport(Request $request, string $reportType): Response
    {
        try {
            $filters = $request->getQueryParams();

            $result = match($reportType) {
                'applications' => $this->generateApplicationReport($filters),
                'inspections' => $this->generateInspectionReport($filters),
                'certificates' => $this->certificateManager->generateCertificateReport($filters),
                'fees' => $this->feeManager->generateFeeReport($filters),
                'compliance' => $this->complianceManager->generateComplianceReport($filters),
                default => ['success' => false, 'error' => 'Invalid report type']
            };

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => $result['error']
                ], 400);
            }

            return $this->jsonResponse($result);

        } catch (Exception $e) {
            error_log("Error generating report: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Generate application report
     */
    private function generateApplicationReport(array $filters): array
    {
        try {
            $result = $this->applicationManager->getApplications($filters);

            if (!$result['success']) {
                return $result;
            }

            // Process data for reporting
            $processedData = $this->processApplicationReportData($result['data']);

            return [
                'success' => true,
                'data' => $processedData,
                'filters' => $filters,
                'generated_at' => date('c')
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to generate application report'
            ];
        }
    }

    /**
     * Generate inspection report
     */
    private function generateInspectionReport(array $filters): array
    {
        try {
            $result = $this->inspectionManager->getInspections($filters);

            if (!$result['success']) {
                return $result;
            }

            // Process data for reporting
            $processedData = $this->processInspectionReportData($result['data']);

            return [
                'success' => true,
                'data' => $processedData,
                'filters' => $filters,
                'generated_at' => date('c')
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to generate inspection report'
            ];
        }
    }

    /**
     * Process application report data
     */
    private function processApplicationReportData(array $data): array
    {
        // Group by status and calculate statistics
        $stats = [
            'by_status' => [],
            'by_month' => [],
            'by_type' => []
        ];

        foreach ($data as $application) {
            // Count by status
            $status = $application['status'];
            if (!isset($stats['by_status'][$status])) {
                $stats['by_status'][$status] = 0;
            }
            $stats['by_status'][$status]++;

            // Count by month
            $month = date('Y-m', strtotime($application['created_at']));
            if (!isset($stats['by_month'][$month])) {
                $stats['by_month'][$month] = 0;
            }
            $stats['by_month'][$month]++;

            // Count by type
            $type = $application['building_consent_type'];
            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = 0;
            }
            $stats['by_type'][$type]++;
        }

        return $stats;
    }

    /**
     * Process inspection report data
     */
    private function processInspectionReportData(array $data): array
    {
        // Group by status and calculate statistics
        $stats = [
            'by_status' => [],
            'by_type' => [],
            'by_result' => [],
            'average_duration' => 0
        ];

        $totalDuration = 0;
        $durationCount = 0;

        foreach ($data as $inspection) {
            // Count by status
            $status = $inspection['status'];
            if (!isset($stats['by_status'][$status])) {
                $stats['by_status'][$status] = 0;
            }
            $stats['by_status'][$status]++;

            // Count by type
            $type = $inspection['inspection_type'];
            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = 0;
            }
            $stats['by_type'][$type]++;

            // Count by result
            if (isset($inspection['result'])) {
                $result = $inspection['result'];
                if (!isset($stats['by_result'][$result])) {
                    $stats['by_result'][$result] = 0;
                }
                $stats['by_result'][$result]++;
            }

            // Calculate average duration for completed inspections
            if ($inspection['status'] === 'completed' && isset($inspection['actual_date']) && isset($inspection['scheduled_date'])) {
                $scheduled = strtotime($inspection['scheduled_date']);
                $actual = strtotime($inspection['actual_date']);
                $duration = $actual - $scheduled;
                $totalDuration += $duration;
                $durationCount++;
            }
        }

        if ($durationCount > 0) {
            $stats['average_duration'] = round($totalDuration / $durationCount);
        }

        return $stats;
    }

    /**
     * Handle CORS preflight requests
     */
    public function handleOptions(Request $request): Response
    {
        return $this->jsonResponse([], 200)
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
}
