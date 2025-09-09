<?php
/**
 * TPT Government Platform - Building Certificate Repository
 *
 * Repository for building certificates
 */

namespace Modules\BuildingConsents\Repositories;

use Core\Repository\BaseRepository;
use Core\Database;

class BuildingCertificateRepository extends BaseRepository
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
        $this->table = 'building_certificates';
        $this->primaryKey = 'certificate_id';
        $this->fillable = [
            'application_id',
            'certificate_type',
            'certificate_number',
            'issued_by',
            'issued_date',
            'expiry_date',
            'status',
            'conditions',
            'limitations',
            'notes',
            'created_at',
            'updated_at'
        ];
        $this->casts = [
            'issued_by' => 'int',
            'issued_date' => 'date',
            'expiry_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'conditions' => 'json',
            'limitations' => 'json'
        ];
    }

    /**
     * Find certificates by application
     */
    public function findByApplication(string $applicationId, array $options = []): array
    {
        return $this->findWhere(['application_id' => $applicationId], $options);
    }

    /**
     * Find certificates by type
     */
    public function findByType(string $certificateType, array $options = []): array
    {
        return $this->findWhere(['certificate_type' => $certificateType], $options);
    }

    /**
     * Find certificates by status
     */
    public function findByStatus(string $status, array $options = []): array
    {
        return $this->findWhere(['status' => $status], $options);
    }

    /**
     * Find certificates issued by user
     */
    public function findByIssuer(int $issuedBy, array $options = []): array
    {
        return $this->findWhere(['issued_by' => $issuedBy], $options);
    }

    /**
     * Get active certificates
     */
    public function getActiveCertificates(array $options = []): array
    {
        $today = date('Y-m-d');

        $sql = "SELECT * FROM {$this->table}
                WHERE status = 'active'
                AND (expiry_date IS NULL OR expiry_date >= ?)
                ORDER BY expiry_date ASC";

        // Add limit if specified
        if (isset($options['limit'])) {
            $sql .= " LIMIT {$options['limit']}";
        }

        try {
            $results = $this->db->fetchAll($sql, [$today]);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting active certificates: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get expiring certificates
     */
    public function getExpiringCertificates(int $daysAhead = 30): array
    {
        $today = date('Y-m-d');
        $expiryDate = date('Y-m-d', strtotime("+{$daysAhead} days"));

        $sql = "SELECT * FROM {$this->table}
                WHERE status = 'active'
                AND expiry_date BETWEEN ? AND ?
                ORDER BY expiry_date ASC";

        try {
            $results = $this->db->fetchAll($sql, [$today, $expiryDate]);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting expiring certificates: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get expired certificates
     */
    public function getExpiredCertificates(): array
    {
        $today = date('Y-m-d');

        $sql = "SELECT * FROM {$this->table}
                WHERE status = 'active'
                AND expiry_date < ?
                ORDER BY expiry_date DESC";

        try {
            $results = $this->db->fetchAll($sql, [$today]);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error getting expired certificates: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Issue new certificate
     */
    public function issueCertificate(array $certificateData): int|string|false
    {
        // Generate certificate number if not provided
        if (!isset($certificateData['certificate_number'])) {
            $certificateData['certificate_number'] = $this->generateCertificateNumber(
                $certificateData['certificate_type'],
                $certificateData['application_id']
            );
        }

        $data = array_merge($certificateData, [
            'status' => 'active',
            'issued_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return $this->create($data);
    }

    /**
     * Revoke certificate
     */
    public function revokeCertificate(int $certificateId, string $reason): bool
    {
        return $this->update($certificateId, [
            'status' => 'revoked',
            'notes' => $reason,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Renew certificate
     */
    public function renewCertificate(int $certificateId, string $newExpiryDate, array $additionalData = []): bool
    {
        $data = array_merge($additionalData, [
            'expiry_date' => $newExpiryDate,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return $this->update($certificateId, $data);
    }

    /**
     * Get certificate statistics
     */
    public function getStatistics(): array
    {
        try {
            $sql = "SELECT
                        status,
                        certificate_type,
                        COUNT(*) as count
                    FROM {$this->table}
                    GROUP BY status, certificate_type
                    ORDER BY status, certificate_type";

            $results = $this->db->fetchAll($sql);

            $stats = [
                'total_certificates' => 0,
                'by_status' => [],
                'by_type' => []
            ];

            foreach ($results as $result) {
                $status = $result['status'];
                $type = $result['certificate_type'];
                $count = (int)$result['count'];

                // By status
                if (!isset($stats['by_status'][$status])) {
                    $stats['by_status'][$status] = 0;
                }
                $stats['by_status'][$status] += $count;

                // By type
                if (!isset($stats['by_type'][$type])) {
                    $stats['by_type'][$type] = 0;
                }
                $stats['by_type'][$type] += $count;

                $stats['total_certificates'] += $count;
            }

            return $stats;
        } catch (\Exception $e) {
            error_log("Error getting certificate statistics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Search certificates
     */
    public function search(string $query, array $options = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE
                certificate_number LIKE ? OR
                application_id LIKE ? OR
                certificate_type LIKE ?";

        $searchTerm = "%{$query}%";
        $params = [$searchTerm, $searchTerm, $searchTerm];

        // Add ordering
        if (isset($options['orderBy'])) {
            $sql .= " ORDER BY {$options['orderBy']}";
            if (isset($options['orderDirection'])) {
                $sql .= " {$options['orderDirection']}";
            }
        }

        // Add limit
        if (isset($options['limit'])) {
            $sql .= " LIMIT {$options['limit']}";
            if (isset($options['offset'])) {
                $sql .= " OFFSET {$options['offset']}";
            }
        }

        try {
            $results = $this->db->fetchAll($sql, $params);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error searching certificates: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get certificates by expiry date range
     */
    public function findByExpiryRange(string $startDate, string $endDate, array $options = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE expiry_date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];

        // Add ordering
        if (isset($options['orderBy'])) {
            $sql .= " ORDER BY {$options['orderBy']}";
            if (isset($options['orderDirection'])) {
                $sql .= " {$options['orderDirection']}";
            }
        }

        // Add limit
        if (isset($options['limit'])) {
            $sql .= " LIMIT {$options['limit']}";
            if (isset($options['offset'])) {
                $sql .= " OFFSET {$options['offset']}";
            }
        }

        try {
            $results = $this->db->fetchAll($sql, $params);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error finding certificates by expiry range: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate certificate number
     */
    private function generateCertificateNumber(string $type, string $applicationId): string
    {
        $prefix = match ($type) {
            'code_compliance' => 'CC',
            'building_consent' => 'BC',
            'occupancy' => 'OC',
            'completion' => 'CP',
            default => 'CT'
        };

        $timestamp = date('YmdHis');
        $random = strtoupper(substr(md5(uniqid()), 0, 4));

        return "{$prefix}{$timestamp}{$random}";
    }

    /**
     * Get certificate by number
     */
    public function findByNumber(string $certificateNumber): ?array
    {
        return $this->findBy('certificate_number', $certificateNumber);
    }

    /**
     * Check if certificate is valid
     */
    public function isValid(int $certificateId): bool
    {
        $certificate = $this->find($certificateId);

        if (!$certificate) {
            return false;
        }

        if ($certificate['status'] !== 'active') {
            return false;
        }

        if ($certificate['expiry_date']) {
            $today = date('Y-m-d');
            if ($certificate['expiry_date'] < $today) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get certificates issued in date range
     */
    public function findIssuedInRange(string $startDate, string $endDate, array $options = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE issued_date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];

        // Add ordering
        if (isset($options['orderBy'])) {
            $sql .= " ORDER BY {$options['orderBy']}";
            if (isset($options['orderDirection'])) {
                $sql .= " {$options['orderDirection']}";
            }
        }

        // Add limit
        if (isset($options['limit'])) {
            $sql .= " LIMIT {$options['limit']}";
            if (isset($options['offset'])) {
                $sql .= " OFFSET {$options['offset']}";
            }
        }

        try {
            $results = $this->db->fetchAll($sql, $params);

            // Cast attributes for each result
            foreach ($results as &$result) {
                $result = $this->castAttributes($result);
            }

            return $results;
        } catch (\Exception $e) {
            error_log("Error finding certificates issued in range: " . $e->getMessage());
            return [];
        }
    }
}
