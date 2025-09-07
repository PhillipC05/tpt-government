<?php
/**
 * TPT Government Platform - ERP Data Mapper
 *
 * Handles data transformation between different ERP systems and the platform.
 * Provides mapping configurations and data normalization.
 */

namespace Core;

class ERPDataMapper
{
    /**
     * Data mapping configurations
     */
    private array $mappings = [
        'employees' => [
            'sap' => [
                'id' => 'PERNR',
                'first_name' => 'VORNA',
                'last_name' => 'NACHN',
                'email' => 'USRID',
                'department' => 'ORGEH',
                'position' => 'PLANS',
                'hire_date' => 'BEGDA',
                'salary' => 'BETRG'
            ],
            'oracle' => [
                'id' => 'EMPLOYEE_ID',
                'first_name' => 'FIRST_NAME',
                'last_name' => 'LAST_NAME',
                'email' => 'EMAIL',
                'department' => 'DEPARTMENT_ID',
                'position' => 'JOB_ID',
                'hire_date' => 'HIRE_DATE',
                'salary' => 'SALARY'
            ],
            'dynamics' => [
                'id' => 'EmployeeNumber',
                'first_name' => 'FirstName',
                'last_name' => 'LastName',
                'email' => 'Email',
                'department' => 'Department',
                'position' => 'Position',
                'hire_date' => 'HireDate',
                'salary' => 'Salary'
            ]
        ],
        'departments' => [
            'sap' => [
                'id' => 'ORGEH',
                'name' => 'ORGTX',
                'parent_id' => 'UP_ORG',
                'manager_id' => 'ORGEH_MANAGER',
                'cost_center' => 'KOSTL'
            ],
            'oracle' => [
                'id' => 'DEPARTMENT_ID',
                'name' => 'DEPARTMENT_NAME',
                'parent_id' => 'PARENT_DEPARTMENT_ID',
                'manager_id' => 'MANAGER_ID',
                'cost_center' => 'COST_CENTER'
            ],
            'dynamics' => [
                'id' => 'DepartmentId',
                'name' => 'Name',
                'parent_id' => 'ParentDepartmentId',
                'manager_id' => 'ManagerId',
                'cost_center' => 'CostCenter'
            ]
        ],
        'budget' => [
            'sap' => [
                'id' => 'BELNR',
                'department_id' => 'ORGEH',
                'fiscal_year' => 'GJAHR',
                'amount' => 'WTGXXX',
                'currency' => 'WAERS',
                'category' => 'HKONT'
            ],
            'oracle' => [
                'id' => 'BUDGET_ID',
                'department_id' => 'DEPARTMENT_ID',
                'fiscal_year' => 'FISCAL_YEAR',
                'amount' => 'AMOUNT',
                'currency' => 'CURRENCY_CODE',
                'category' => 'BUDGET_CATEGORY'
            ],
            'dynamics' => [
                'id' => 'BudgetId',
                'department_id' => 'DepartmentId',
                'fiscal_year' => 'FiscalYear',
                'amount' => 'Amount',
                'currency' => 'Currency',
                'category' => 'Category'
            ]
        ]
    ];

    /**
     * Standard field mappings for the platform
     */
    private array $standardFields = [
        'employees' => [
            'id' => 'string',
            'first_name' => 'string',
            'last_name' => 'string',
            'email' => 'string',
            'department' => 'string',
            'position' => 'string',
            'hire_date' => 'date',
            'salary' => 'decimal',
            'status' => 'string',
            'phone' => 'string'
        ],
        'departments' => [
            'id' => 'string',
            'name' => 'string',
            'parent_id' => 'string',
            'manager_id' => 'string',
            'cost_center' => 'string',
            'budget' => 'decimal',
            'employee_count' => 'integer'
        ],
        'budget' => [
            'id' => 'string',
            'department_id' => 'string',
            'fiscal_year' => 'string',
            'amount' => 'decimal',
            'currency' => 'string',
            'category' => 'string',
            'approved' => 'boolean'
        ]
    ];

    /**
     * Transform data from ERP format to platform format
     */
    public function transformFromERP(string $erpType, string $entityType, array $erpData): array
    {
        if (!isset($this->mappings[$entityType][$erpType])) {
            // Return data as-is if no mapping exists
            return $erpData;
        }

        $mapping = $this->mappings[$entityType][$erpType];
        $transformed = [];

        foreach ($erpData as $record) {
            $transformedRecord = [];

            foreach ($mapping as $platformField => $erpField) {
                if (isset($record[$erpField])) {
                    $transformedRecord[$platformField] = $this->normalizeValue(
                        $record[$erpField],
                        $this->standardFields[$entityType][$platformField] ?? 'string'
                    );
                }
            }

            // Add metadata
            $transformedRecord['_erp_source'] = $erpType;
            $transformedRecord['_erp_id'] = $record[$mapping['id']] ?? null;
            $transformedRecord['_transformed_at'] = date('Y-m-d H:i:s');

            $transformed[] = $transformedRecord;
        }

        return $transformed;
    }

    /**
     * Transform data from platform format to ERP format
     */
    public function transformToERP(string $erpType, string $entityType, array $platformData): array
    {
        if (!isset($this->mappings[$entityType][$erpType])) {
            // Return data as-is if no mapping exists
            return $platformData;
        }

        $mapping = $this->mappings[$entityType][$erpType];
        $transformed = [];

        // Reverse the mapping
        $reverseMapping = array_flip($mapping);

        foreach ($platformData as $record) {
            $transformedRecord = [];

            foreach ($reverseMapping as $erpField => $platformField) {
                if (isset($record[$platformField])) {
                    $transformedRecord[$erpField] = $this->denormalizeValue(
                        $record[$platformField],
                        $this->standardFields[$entityType][$platformField] ?? 'string'
                    );
                }
            }

            $transformed[] = $transformedRecord;
        }

        return $transformed;
    }

    /**
     * Normalize value based on field type
     */
    private function normalizeValue($value, string $type)
    {
        if ($value === null || $value === '') {
            return null;
        }

        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'decimal':
                return (float) $value;
            case 'boolean':
                return $this->normalizeBoolean($value);
            case 'date':
                return $this->normalizeDate($value);
            case 'string':
            default:
                return (string) $value;
        }
    }

    /**
     * Denormalize value for ERP system
     */
    private function denormalizeValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'boolean':
                return $value ? '1' : '0';
            case 'date':
                return $value instanceof \DateTime ? $value->format('Y-m-d') : $value;
            default:
                return (string) $value;
        }
    }

    /**
     * Normalize boolean values
     */
    private function normalizeBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lowerValue = strtolower($value);
            return in_array($lowerValue, ['1', 'true', 'yes', 'y', 'on']);
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return false;
    }

    /**
     * Normalize date values
     */
    private function normalizeDate($value)
    {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            // Try different date formats
            $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d'];

            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            }
        }

        return $value;
    }

    /**
     * Add custom mapping
     */
    public function addMapping(string $entityType, string $erpType, array $mapping): void
    {
        if (!isset($this->mappings[$entityType])) {
            $this->mappings[$entityType] = [];
        }

        $this->mappings[$entityType][$erpType] = $mapping;
    }

    /**
     * Get mapping for entity and ERP type
     */
    public function getMapping(string $entityType, string $erpType): ?array
    {
        return $this->mappings[$entityType][$erpType] ?? null;
    }

    /**
     * Get all mappings
     */
    public function getAllMappings(): array
    {
        return $this->mappings;
    }

    /**
     * Validate mapping configuration
     */
    public function validateMapping(string $entityType, string $erpType, array $mapping): array
    {
        $errors = [];

        if (!isset($this->standardFields[$entityType])) {
            $errors[] = "Unknown entity type: {$entityType}";
            return $errors;
        }

        $requiredFields = ['id']; // At minimum, ID field is required

        foreach ($requiredFields as $field) {
            if (!isset($mapping[$field])) {
                $errors[] = "Required field '{$field}' is missing from mapping";
            }
        }

        // Check if all mapped fields exist in standard fields
        foreach ($mapping as $platformField => $erpField) {
            if (!isset($this->standardFields[$entityType][$platformField])) {
                $errors[] = "Unknown platform field: {$platformField}";
            }
        }

        return $errors;
    }

    /**
     * Get standard field definitions
     */
    public function getStandardFields(string $entityType): ?array
    {
        return $this->standardFields[$entityType] ?? null;
    }

    /**
     * Add custom entity type
     */
    public function addEntityType(string $entityType, array $fields): void
    {
        $this->standardFields[$entityType] = $fields;
    }

    /**
     * Merge data from multiple sources
     */
    public function mergeData(array $sources, string $mergeKey = 'id'): array
    {
        $merged = [];
        $seen = [];

        foreach ($sources as $source) {
            foreach ($source as $record) {
                $key = $record[$mergeKey] ?? null;

                if ($key === null) {
                    $merged[] = $record;
                    continue;
                }

                if (!isset($seen[$key])) {
                    $seen[$key] = $record;
                    $merged[] = $record;
                } else {
                    // Merge records with same key
                    $merged[array_search($seen[$key], $merged)] = array_merge($seen[$key], $record);
                }
            }
        }

        return $merged;
    }

    /**
     * Filter data based on criteria
     */
    public function filterData(array $data, array $criteria): array
    {
        return array_filter($data, function($record) use ($criteria) {
            foreach ($criteria as $field => $value) {
                if (!isset($record[$field])) {
                    return false;
                }

                if (is_array($value)) {
                    if (!in_array($record[$field], $value)) {
                        return false;
                    }
                } else {
                    if ($record[$field] !== $value) {
                        return false;
                    }
                }
            }

            return true;
        });
    }

    /**
     * Sort data by field
     */
    public function sortData(array $data, string $field, string $direction = 'asc'): array
    {
        usort($data, function($a, $b) use ($field, $direction) {
            $aValue = $a[$field] ?? null;
            $bValue = $b[$field] ?? null;

            if ($aValue === $bValue) {
                return 0;
            }

            $result = $aValue <=> $bValue;

            return $direction === 'desc' ? -$result : $result;
        });

        return $data;
    }

    /**
     * Paginate data
     */
    public function paginateData(array $data, int $page = 1, int $perPage = 25): array
    {
        $total = count($data);
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        return [
            'data' => array_slice($data, $offset, $perPage),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ];
    }
}
