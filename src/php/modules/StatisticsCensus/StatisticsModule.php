<?php
/**
 * TPT Government Platform - Statistics & Census Module
 *
 * Comprehensive statistical data collection, analysis, and census management system
 * supporting population surveys, economic indicators, and government reporting
 */

namespace Modules\StatisticsCensus;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;

class StatisticsModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Statistics & Census',
        'version' => '1.0.0',
        'description' => 'Comprehensive statistical data collection, analysis, and census management system',
        'author' => 'TPT Government Platform',
        'category' => 'statistics_census',
        'dependencies' => ['database', 'workflow', 'payment', 'notification']
    ];

    /**
     * Module dependencies
     */
    protected array $dependencies = [
        ['name' => 'Database', 'version' => '>=1.0.0'],
        ['name' => 'WorkflowEngine', 'version' => '>=1.0.0'],
        ['name' => 'PaymentGateway', 'version' => '>=1.0.0'],
        ['name' => 'NotificationManager', 'version' => '>=1.0.0']
    ];

    /**
     * Module permissions
     */
    protected array $permissions = [
        'statistics.view' => 'View statistical data and reports',
        'statistics.collect' => 'Collect and manage statistical data',
        'statistics.analyze' => 'Analyze statistical data and generate insights',
        'statistics.publish' => 'Publish statistical reports and findings',
        'census.admin' => 'Administer census operations',
        'census.respond' => 'Respond to census surveys',
        'census.view_results' => 'View census results and aggregated data'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'census_households' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'household_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'address' => 'TEXT NOT NULL',
            'dwelling_type' => "ENUM('house','apartment','townhouse','mobile_home','other') NOT NULL",
            'occupancy_status' => "ENUM('occupied','vacant','under_construction','demolished') DEFAULT 'occupied'",
            'number_of_rooms' => 'INT NOT NULL',
            'number_of_bedrooms' => 'INT NOT NULL',
            'tenure_type' => "ENUM('owned','rented','mortgaged','other') NOT NULL",
            'monthly_rent' => 'DECIMAL(10,2) NULL',
            'property_value' => 'DECIMAL(12,2) NULL',
            'year_built' => 'YEAR NULL',
            'enumerator_id' => 'INT NULL',
            'enumeration_date' => 'DATETIME NULL',
            'response_status' => "ENUM('completed','partial','refused','not_found','pending') DEFAULT 'pending'",
            'quality_score' => 'DECIMAL(3,2) NULL',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'census_persons' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'person_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'household_id' => 'VARCHAR(20) NOT NULL',
            'relationship_to_head' => "ENUM('head','spouse','child','parent','sibling','other_relative','non_relative') NOT NULL",
            'full_name' => 'VARCHAR(255) NOT NULL',
            'date_of_birth' => 'DATE NOT NULL',
            'gender' => "ENUM('male','female','other','prefer_not_to_say') NOT NULL",
            'marital_status' => "ENUM('single','married','divorced','widowed','separated','common_law') NOT NULL",
            'nationality' => 'VARCHAR(100) NOT NULL',
            'ethnicity' => 'VARCHAR(100) NULL',
            'language_spoken' => 'VARCHAR(100) NOT NULL',
            'education_level' => "ENUM('none','primary','secondary','tertiary','postgraduate') NOT NULL",
            'employment_status' => "ENUM('employed','unemployed','student','retired','homemaker','disabled','other') NOT NULL",
            'occupation' => 'VARCHAR(100) NULL',
            'industry' => 'VARCHAR(100) NULL',
            'income_range' => "ENUM('under_10000','10000_25000','25000_50000','50000_100000','over_100000') NULL",
            'disability_status' => 'BOOLEAN DEFAULT FALSE',
            'health_insurance' => 'BOOLEAN DEFAULT FALSE',
            'internet_access' => 'BOOLEAN DEFAULT FALSE',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'statistical_surveys' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'survey_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'survey_name' => 'VARCHAR(255) NOT NULL',
            'survey_type' => "ENUM('household','business','agricultural','labor','health','education','economic','other') NOT NULL",
            'description' => 'TEXT NOT NULL',
            'target_population' => 'VARCHAR(255) NOT NULL',
            'sample_size' => 'INT NOT NULL',
            'survey_period_start' => 'DATE NOT NULL',
            'survey_period_end' => 'DATE NOT NULL',
            'collection_method' => "ENUM('online','telephone','face_to_face','mail','mixed') NOT NULL",
            'status' => "ENUM('planning','active','completed','cancelled') DEFAULT 'planning'",
            'response_rate' => 'DECIMAL(5,2) DEFAULT 0',
            'budget' => 'DECIMAL(12,2) NOT NULL',
            'funding_source' => 'VARCHAR(255) NULL',
            'data_collection_url' => 'VARCHAR(500) NULL',
            'survey_questions' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'survey_responses' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'response_id' => 'VARCHAR(30) UNIQUE NOT NULL',
            'survey_id' => 'VARCHAR(20) NOT NULL',
            'respondent_id' => 'VARCHAR(20) NOT NULL',
            'response_data' => 'JSON NOT NULL',
            'response_timestamp' => 'DATETIME NOT NULL',
            'response_method' => "ENUM('online','telephone','face_to_face','mail') NOT NULL",
            'interviewer_id' => 'INT NULL',
            'completion_time_minutes' => 'INT NULL',
            'quality_score' => 'DECIMAL(3,2) NULL',
            'validation_status' => "ENUM('pending','validated','rejected','flagged') DEFAULT 'pending'",
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'economic_indicators' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'indicator_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'indicator_name' => 'VARCHAR(255) NOT NULL',
            'indicator_category' => "ENUM('gdp','inflation','employment','trade','investment','poverty','inequality') NOT NULL",
            'description' => 'TEXT NOT NULL',
            'unit_of_measure' => 'VARCHAR(50) NOT NULL',
            'frequency' => "ENUM('daily','weekly','monthly','quarterly','annually') NOT NULL",
            'data_source' => 'VARCHAR(255) NOT NULL',
            'last_updated' => 'DATETIME NOT NULL',
            'current_value' => 'DECIMAL(15,4) NULL',
            'previous_value' => 'DECIMAL(15,4) NULL',
            'change_percentage' => 'DECIMAL(6,2) NULL',
            'historical_data' => 'JSON',
            'forecast_data' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'demographic_data' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'data_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'data_type' => "ENUM('population','age_distribution','gender_ratio','ethnic_composition','education_levels','employment_rates','income_distribution','migration') NOT NULL",
            'geographic_level' => "ENUM('national','regional','district','local') NOT NULL",
            'geographic_area' => 'VARCHAR(100) NOT NULL',
            'data_year' => 'YEAR NOT NULL',
            'data_period' => "ENUM('annual','quarterly','monthly') DEFAULT 'annual'",
            'total_population' => 'INT NULL',
            'male_population' => 'INT NULL',
            'female_population' => 'INT NULL',
            'age_0_14' => 'INT NULL',
            'age_15_64' => 'INT NULL',
            'age_65_plus' => 'INT NULL',
            'urban_population' => 'INT NULL',
            'rural_population' => 'INT NULL',
            'data_values' => 'JSON',
            'data_quality_score' => 'DECIMAL(3,2) NULL',
            'source' => 'VARCHAR(255) NOT NULL',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'statistical_reports' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'report_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'report_title' => 'VARCHAR(255) NOT NULL',
            'report_type' => "ENUM('census','survey','economic','demographic','annual','quarterly','special') NOT NULL",
            'description' => 'TEXT NOT NULL',
            'publication_date' => 'DATE NOT NULL',
            'report_period_start' => 'DATE NOT NULL',
            'report_period_end' => 'DATE NOT NULL',
            'author' => 'VARCHAR(255) NOT NULL',
            'reviewer' => 'VARCHAR(255) NULL',
            'status' => "ENUM('draft','review','approved','published','archived') DEFAULT 'draft'",
            'file_path' => 'VARCHAR(500) NULL',
            'file_size' => 'INT NULL',
            'download_count' => 'INT DEFAULT 0',
            'key_findings' => 'JSON',
            'methodology' => 'TEXT',
            'data_sources' => 'JSON',
            'limitations' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ]
    ];

    /**
     * Module API endpoints
     */
    protected array $endpoints = [
        [
            'method' => 'GET',
            'path' => '/api/statistics/census',
            'handler' => 'getCensusData',
            'auth' => true,
            'permissions' => ['census.view_results']
        ],
        [
            'method' => 'POST',
            'path' => '/api/statistics/census/response',
            'handler' => 'submitCensusResponse',
            'auth' => true,
            'permissions' => ['census.respond']
        ],
        [
            'method' => 'GET',
            'path' => '/api/statistics/surveys',
            'handler' => 'getSurveys',
            'auth' => true,
            'permissions' => ['statistics.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/statistics/surveys/{surveyId}/response',
            'handler' => 'submitSurveyResponse',
            'auth' => true,
            'permissions' => ['statistics.collect']
        ],
        [
            'method' => 'GET',
            'path' => '/api/statistics/indicators',
            'handler' => 'getEconomicIndicators',
            'auth' => true,
            'permissions' => ['statistics.view']
        ],
        [
            'method' => 'GET',
            'path' => '/api/statistics/reports',
            'handler' => 'getStatisticalReports',
            'auth' => true,
            'permissions' => ['statistics.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/statistics/reports',
            'handler' => 'createStatisticalReport',
            'auth' => true,
            'permissions' => ['statistics.publish']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'census_enumeration_process' => [
            'name' => 'Census Enumeration Process',
            'description' => 'Complete workflow for census data collection and validation',
            'steps' => [
                'planning' => ['name' => 'Census Planning', 'next' => 'field_preparation'],
                'field_preparation' => ['name' => 'Field Preparation', 'next' => 'enumeration'],
                'enumeration' => ['name' => 'Data Enumeration', 'next' => 'data_entry'],
                'data_entry' => ['name' => 'Data Entry', 'next' => 'validation'],
                'validation' => ['name' => 'Data Validation', 'next' => ['approved', 'corrections_required']],
                'corrections_required' => ['name' => 'Corrections Required', 'next' => 'validation'],
                'approved' => ['name' => 'Data Approved', 'next' => 'publication'],
                'publication' => ['name' => 'Results Publication', 'next' => null]
            ]
        ],
        'survey_conduct_process' => [
            'name' => 'Survey Conduct Process',
            'description' => 'Workflow for conducting statistical surveys',
            'steps' => [
                'design' => ['name' => 'Survey Design', 'next' => 'sampling'],
                'sampling' => ['name' => 'Sample Selection', 'next' => 'pilot'],
                'pilot' => ['name' => 'Pilot Testing', 'next' => 'fieldwork'],
                'fieldwork' => ['name' => 'Field Data Collection', 'next' => 'data_processing'],
                'data_processing' => ['name' => 'Data Processing', 'next' => 'analysis'],
                'analysis' => ['name' => 'Data Analysis', 'next' => 'reporting'],
                'reporting' => ['name' => 'Report Generation', 'next' => 'dissemination'],
                'dissemination' => ['name' => 'Results Dissemination', 'next' => null]
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'census_household_form' => [
            'name' => 'Census Household Form',
            'fields' => [
                'dwelling_type' => ['type' => 'select', 'required' => true, 'label' => 'Type of Dwelling'],
                'occupancy_status' => ['type' => 'select', 'required' => true, 'label' => 'Occupancy Status'],
                'number_of_rooms' => ['type' => 'number', 'required' => true, 'label' => 'Number of Rooms'],
                'number_of_bedrooms' => ['type' => 'number', 'required' => true, 'label' => 'Number of Bedrooms'],
                'tenure_type' => ['type' => 'select', 'required' => true, 'label' => 'Tenure Type'],
                'monthly_rent' => ['type' => 'number', 'required' => false, 'label' => 'Monthly Rent'],
                'property_value' => ['type' => 'number', 'required' => false, 'label' => 'Property Value'],
                'year_built' => ['type' => 'number', 'required' => false, 'label' => 'Year Built']
            ]
        ],
        'census_person_form' => [
            'name' => 'Census Person Form',
            'fields' => [
                'relationship_to_head' => ['type' => 'select', 'required' => true, 'label' => 'Relationship to Head of Household'],
                'full_name' => ['type' => 'text', 'required' => true, 'label' => 'Full Name'],
                'date_of_birth' => ['type' => 'date', 'required' => true, 'label' => 'Date of Birth'],
                'gender' => ['type' => 'select', 'required' => true, 'label' => 'Gender'],
                'marital_status' => ['type' => 'select', 'required' => true, 'label' => 'Marital Status'],
                'nationality' => ['type' => 'text', 'required' => true, 'label' => 'Nationality'],
                'ethnicity' => ['type' => 'text', 'required' => false, 'label' => 'Ethnicity'],
                'language_spoken' => ['type' => 'text', 'required' => true, 'label' => 'Primary Language'],
                'education_level' => ['type' => 'select', 'required' => true, 'label' => 'Education Level'],
                'employment_status' => ['type' => 'select', 'required' => true, 'label' => 'Employment Status'],
                'occupation' => ['type' => 'text', 'required' => false, 'label' => 'Occupation'],
                'industry' => ['type' => 'text', 'required' => false, 'label' => 'Industry'],
                'income_range' => ['type' => 'select', 'required' => false, 'label' => 'Income Range'],
                'disability_status' => ['type' => 'checkbox', 'required' => false, 'label' => 'Has Disability'],
                'health_insurance' => ['type' => 'checkbox', 'required' => false, 'label' => 'Has Health Insurance'],
                'internet_access' => ['type' => 'checkbox', 'required' => false, 'label' => 'Has Internet Access']
            ]
        ],
        'survey_creation_form' => [
            'name' => 'Survey Creation Form',
            'fields' => [
                'survey_name' => ['type' => 'text', 'required' => true, 'label' => 'Survey Name'],
                'survey_type' => ['type' => 'select', 'required' => true, 'label' => 'Survey Type'],
                'description' => ['type' => 'textarea', 'required' => true, 'label' => 'Description'],
                'target_population' => ['type' => 'text', 'required' => true, 'label' => 'Target Population'],
                'sample_size' => ['type' => 'number', 'required' => true, 'label' => 'Sample Size'],
                'survey_period_start' => ['type' => 'date', 'required' => true, 'label' => 'Survey Start Date'],
                'survey_period_end' => ['type' => 'date', 'required' => true, 'label' => 'Survey End Date'],
                'collection_method' => ['type' => 'select', 'required' => true, 'label' => 'Collection Method'],
                'budget' => ['type' => 'number', 'required' => true, 'label' => 'Budget'],
                'funding_source' => ['type' => 'text', 'required' => false, 'label' => 'Funding Source']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'census_population_report' => [
            'name' => 'Population Census Report',
            'description' => 'Comprehensive population statistics and demographics',
            'parameters' => [
                'year' => ['type' => 'select', 'required' => true],
                'geographic_level' => ['type' => 'select', 'required' => false],
                'geographic_area' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'total_population', 'population_growth', 'age_distribution',
                'gender_ratio', 'urban_rural_split', 'ethnic_composition'
            ]
        ],
        'economic_indicators_report' => [
            'name' => 'Economic Indicators Report',
            'description' => 'Key economic performance indicators and trends',
            'parameters' => [
                'indicator_category' => ['type' => 'select', 'required' => false],
                'date_range' => ['type' => 'date_range', 'required' => false]
            ],
            'columns' => [
                'indicator_name', 'current_value', 'previous_value',
                'change_percentage', 'trend_direction', 'forecast'
            ]
        ],
        'survey_results_report' => [
            'name' => 'Survey Results Report',
            'description' => 'Analysis and findings from statistical surveys',
            'parameters' => [
                'survey_id' => ['type' => 'select', 'required' => true],
                'analysis_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'question', 'response_distribution', 'key_findings',
                'statistical_significance', 'confidence_intervals'
            ]
        ],
        'demographic_trends_report' => [
            'name' => 'Demographic Trends Report',
            'description' => 'Long-term demographic changes and projections',
            'parameters' => [
                'time_period' => ['type' => 'date_range', 'required' => true],
                'demographic_category' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'demographic_indicator', 'historical_values', 'current_value',
                'projected_values', 'growth_rate', 'key_drivers'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'census_announcement' => [
            'name' => 'Census Announcement',
            'template' => 'The {census_year} Census is now open. Please complete your census form by {deadline_date}.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['census_started']
        ],
        'census_reminder' => [
            'name' => 'Census Reminder',
            'template' => 'Reminder: Your census response is due in {days_remaining} days. Complete your form now.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['census_deadline_approaching']
        ],
        'survey_invitation' => [
            'name' => 'Survey Invitation',
            'template' => 'You have been selected to participate in the "{survey_name}" survey. Please respond by {deadline_date}.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['survey_invitation_sent']
        ],
        'report_publication' => [
            'name' => 'Statistical Report Publication',
            'template' => 'New statistical report "{report_title}" is now available for download.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['report_published']
        ],
        'data_update_notification' => [
            'name' => 'Economic Data Update',
            'template' => '{indicator_name} updated: {current_value} ({change_percentage}% change from previous period).',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['economic_indicator_updated']
        ]
    ];

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Get module metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get default configuration
     */
    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'census_frequency_years' => 5,
            'default_response_deadline_days' => 30,
            'minimum_response_rate' => 80.0,
            'data_retention_years' => 10,
            'auto_generate_household_id' => true,
            'auto_generate_person_id' => true,
            'quality_threshold' => 0.85
        ];
    }

    /**
     * Initialize module
     */
    protected function initializeModule(): void
    {
        // Initialize module-specific data
    }

    /**
     * Get census data (API handler)
     */
    public function getCensusData(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            // Get household data
            $householdSql = "SELECT * FROM census_households WHERE 1=1";
            $householdParams = [];

            if (isset($filters['dwelling_type'])) {
                $householdSql .= " AND dwelling_type = ?";
                $householdParams[] = $filters['dwelling_type'];
            }

            if (isset($filters['tenure_type'])) {
                $householdSql .= " AND tenure_type = ?";
                $householdParams[] = $filters['tenure_type'];
            }

            $households = $db->fetchAll($householdSql, $householdParams);

            // Get person data
            $personSql = "SELECT * FROM census_persons WHERE 1=1";
            $personParams = [];

            if (isset($filters['age_group'])) {
                $personSql .= " AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN ? AND ?";
                $personParams = array_merge($personParams, $this->getAgeRange($filters['age_group']));
            }

            if (isset($filters['gender'])) {
                $personSql .= " AND gender = ?";
                $personParams[] = $filters['gender'];
            }

            $persons = $db->fetchAll($personSql, $personParams);

            // Calculate aggregated statistics
            $statistics = $this->calculateCensusStatistics($households, $persons);

            return [
                'success' => true,
                'households' => $households,
                'persons' => $persons,
                'statistics' => $statistics,
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            error_log("Error getting census data: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve census data'
            ];
        }
    }

    /**
     * Submit census response (API handler)
     */
    public function submitCensusResponse(array $responseData): array
    {
        // Validate response data
        $validation = $this->validateCensusResponse($responseData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate household ID
        $householdId = $this->generateHouseholdId();

        // Save household data
        $household = [
            'household_id' => $householdId,
            'address' => $responseData['address'],
            'dwelling_type' => $responseData['dwelling_type'],
            'occupancy_status' => 'occupied',
            'number_of_rooms' => $responseData['number_of_rooms'],
            'number_of_bedrooms' => $responseData['number_of_bedrooms'],
            'tenure_type' => $responseData['tenure_type'],
            'monthly_rent' => $responseData['monthly_rent'] ?? null,
            'property_value' => $responseData['property_value'] ?? null,
            'year_built' => $responseData['year_built'] ?? null,
            'enumeration_date' => date('Y-m-d H:i:s'),
            'response_status' => 'completed'
        ];

        $this->saveHousehold($household);

        // Save person data
        foreach ($responseData['persons'] as $personData) {
            $personId = $this->generatePersonId();

            $person = [
                'person_id' => $personId,
                'household_id' => $householdId,
                'relationship_to_head' => $personData['relationship_to_head'],
                'full_name' => $personData['full_name'],
                'date_of_birth' => $personData['date_of_birth'],
                'gender' => $personData['gender'],
                'marital_status' => $personData['marital_status'],
                'nationality' => $personData['nationality'],
                'ethnicity' => $personData['ethnicity'] ?? null,
                'language_spoken' => $personData['language_spoken'],
                'education_level' => $personData['education_level'],
                'employment_status' => $personData['employment_status'],
                'occupation' => $personData['occupation'] ?? null,
                'industry' => $personData['industry'] ?? null,
                'income_range' => $personData['income_range'] ?? null,
                'disability_status' => $personData['disability_status'] ?? false,
                'health_insurance' => $personData['health_insurance'] ?? false,
                'internet_access' => $personData['internet_access'] ?? false
            ];

            $this->savePerson($person);
        }

        // Send confirmation notification
        $this->sendNotification('census_response_submitted', $responseData['user_id'], [
            'household_id' => $householdId
        ]);

        return [
            'success' => true,
            'household_id' => $householdId,
            'persons_count' => count($responseData['persons']),
            'message' => 'Census response submitted successfully'
        ];
    }

    /**
     * Get surveys (API handler)
     */
    public function getSurveys(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM statistical_surveys WHERE 1=1";
            $params = [];

            if (isset($filters['type'])) {
                $sql .= " AND survey_type = ?";
                $params[] = $filters['type'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            $sql .= " ORDER BY survey_period_start DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['survey_questions'] = json_decode($result['survey_questions'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting surveys: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve surveys'
            ];
        }
    }

    /**
     * Submit survey response (API handler)
     */
    public function submitSurveyResponse(string $surveyId, array $responseData): array
    {
        // Validate survey exists and is active
        $survey = $this->getSurvey($surveyId);
        if (!$survey || $survey['status'] !== 'active') {
            return [
                'success' => false,
                'error' => 'Survey not found or not active'
            ];
        }

        // Validate response data
        $validation = $this->validateSurveyResponse($responseData, $survey);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate response ID
        $responseId = $this->generateResponseId();

        // Save response
        $response = [
            'response_id' => $responseId,
            'survey_id' => $surveyId,
            'respondent_id' => $responseData['respondent_id'],
            'response_data' => json_encode($responseData['responses']),
            'response_timestamp' => date('Y-m-d H:i:s'),
            'response_method' => $responseData['response_method'] ?? 'online',
            'interviewer_id' => $responseData['interviewer_id'] ?? null,
            'completion_time_minutes' => $responseData['completion_time_minutes'] ?? null
        ];

        $this->saveSurveyResponse($response);

        // Update survey response rate
        $this->updateSurveyResponseRate($surveyId);

        return [
            'success' => true,
            'response_id' => $responseId,
            'survey_id' => $surveyId,
            'message' => 'Survey response submitted successfully'
        ];
    }

    /**
     * Get economic indicators (API handler)
     */
    public function getEconomicIndicators(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM economic_indicators WHERE 1=1";
            $params = [];

            if (isset($filters['category'])) {
                $sql .= " AND indicator_category = ?";
                $params[] = $filters['category'];
            }

            if (isset($filters['frequency'])) {
                $sql .= " AND frequency = ?";
                $params[] = $filters['frequency'];
            }

            $sql .= " ORDER BY last_updated DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['historical_data'] = json_decode($result['historical_data'], true);
                $result['forecast_data'] = json_decode($result['forecast_data'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting economic indicators: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve economic indicators'
            ];
        }
    }

    /**
     * Get statistical reports (API handler)
     */
    public function getStatisticalReports(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM statistical_reports WHERE 1=1";
            $params = [];

            if (isset($filters['type'])) {
                $sql .= " AND report_type = ?";
                $params[] = $filters['type'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            $sql .= " ORDER BY publication_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['key_findings'] = json_decode($result['key_findings'], true);
                $result['data_sources'] = json_decode($result['data_sources'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting statistical reports: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve statistical reports'
            ];
        }
    }

    /**
     * Create statistical report (API handler)
     */
    public function createStatisticalReport(array $reportData): array
    {
        // Validate report data
        $validation = $this->validateReportData($reportData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate report ID
        $reportId = $this->generateReportId();

        // Create report record
        $report = [
            'report_id' => $reportId,
            'report_title' => $reportData['report_title'],
            'report_type' => $reportData['report_type'],
            'description' => $reportData['description'],
            'publication_date' => $reportData['publication_date'],
            'report_period_start' => $reportData['report_period_start'],
            'report_period_end' => $reportData['report_period_end'],
            'author' => $reportData['author'],
            'reviewer' => $reportData['reviewer'] ?? null,
            'status' => 'draft',
            'key_findings' => json_encode($reportData['key_findings'] ?? []),
            'methodology' => $reportData['methodology'] ?? '',
            'data_sources' => json_encode($reportData['data_sources'] ?? []),
            'limitations' => $reportData['limitations'] ?? ''
        ];

        $this->saveStatisticalReport($report);

        return [
            'success' => true,
            'report_id' => $reportId,
            'status' => 'draft',
            'message' => 'Statistical report created successfully'
        ];
    }

    /**
     * Generate household ID
     */
    private function generateHouseholdId(): string
    {
        return 'HH' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate person ID
     */
    private function generatePersonId(): string
    {
        return 'PS' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate response ID
     */
    private function generateResponseId(): string
    {
        return 'RESP' . date('YmdHis') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate report ID
     */
    private function generateReportId(): string
    {
        return 'RPT' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get age range for filtering
     */
    private function getAgeRange(string $ageGroup): array
    {
        $ranges = [
            '0-14' => [0, 14],
            '15-24' => [15, 24],
            '25-34' => [25, 34],
            '35-44' => [35, 44],
            '45-54' => [45, 54],
            '55-64' => [55, 64],
            '65+' => [65, 120]
        ];

        return $ranges[$ageGroup] ?? [0, 120];
    }

    /**
     * Calculate census statistics
     */
    private function calculateCensusStatistics(array $households, array $persons): array
    {
        $stats = [
            'total_households' => count($households),
            'total_persons' => count($persons),
            'average_household_size' => count($persons) / max(1, count($households)),
            'gender_distribution' => [],
            'age_distribution' => [],
            'education_levels' => [],
            'employment_status' => []
        ];

        // Calculate gender distribution
        $genderCounts = array_count_values(array_column($persons, 'gender'));
        foreach ($genderCounts as $gender => $count) {
            $stats['gender_distribution'][$gender] = [
                'count' => $count,
                'percentage' => round(($count / count($persons)) * 100, 2)
            ];
        }

        // Calculate age distribution
        foreach ($persons as $person) {
            $age = $this->calculateAge($person['date_of_birth']);
            $ageGroup = $this->getAgeGroup($age);
            if (!isset($stats['age_distribution'][$ageGroup])) {
                $stats['age_distribution'][$ageGroup] = 0;
            }
            $stats['age_distribution'][$ageGroup]++;
        }

        // Calculate education levels
        $educationCounts = array_count_values(array_column($persons, 'education_level'));
        foreach ($educationCounts as $level => $count) {
            $stats['education_levels'][$level] = [
                'count' => $count,
                'percentage' => round(($count / count($persons)) * 100, 2)
            ];
        }

        // Calculate employment status
        $employmentCounts = array_count_values(array_column($persons, 'employment_status'));
        foreach ($employmentCounts as $status => $count) {
            $stats['employment_status'][$status] = [
                'count' => $count,
                'percentage' => round(($count / count($persons)) * 100, 2)
            ];
        }

        return $stats;
    }

    /**
     * Get age group
     */
    private function getAgeGroup(int $age): string
    {
        if ($age < 15) return '0-14';
        if ($age < 25) return '15-24';
        if ($age < 35) return '25-34';
        if ($age < 45) return '35-44';
        if ($age < 55) return '45-54';
        if ($age < 65) return '55-64';
        return '65+';
    }

    /**
     * Calculate age from date of birth
     */
    private function calculateAge(string $dateOfBirth): int
    {
        $dob = new \DateTime($dateOfBirth);
        $now = new \DateTime();
        return $now->diff($dob)->y;
    }

    /**
     * Save household
     */
    private function saveHousehold(array $household): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO census_households (
                household_id, address, dwelling_type, occupancy_status,
                number_of_rooms, number_of_bedrooms, tenure_type,
                monthly_rent, property_value, year_built, enumeration_date, response_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $household['household_id'],
                $household['address'],
                $household['dwelling_type'],
                $household['occupancy_status'],
                $household['number_of_rooms'],
                $household['number_of_bedrooms'],
                $household['tenure_type'],
                $household['monthly_rent'],
                $household['property_value'],
                $household['year_built'],
                $household['enumeration_date'],
                $household['response_status']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving household: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save person
     */
    private function savePerson(array $person): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO census_persons (
                person_id, household_id, relationship_to_head, full_name,
                date_of_birth, gender, marital_status, nationality, ethnicity,
                language_spoken, education_level, employment_status, occupation,
                industry, income_range, disability_status, health_insurance, internet_access
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $person['person_id'],
                $person['household_id'],
                $person['relationship_to_head'],
                $person['full_name'],
                $person['date_of_birth'],
                $person['gender'],
                $person['marital_status'],
                $person['nationality'],
                $person['ethnicity'],
                $person['language_spoken'],
                $person['education_level'],
                $person['employment_status'],
                $person['occupation'],
                $person['industry'],
                $person['income_range'],
                $person['disability_status'],
                $person['health_insurance'],
                $person['internet_access']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving person: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get survey
     */
    private function getSurvey(string $surveyId): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM statistical_surveys WHERE survey_id = ?";
            $result = $db->fetch($sql, [$surveyId]);

            if ($result) {
                $result['survey_questions'] = json_decode($result['survey_questions'], true);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error getting survey: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Save survey response
     */
    private function saveSurveyResponse(array $response): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO survey_responses (
                response_id, survey_id, respondent_id, response_data,
                response_timestamp, response_method, interviewer_id, completion_time_minutes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $response['response_id'],
                $response['survey_id'],
                $response['respondent_id'],
                $response['response_data'],
                $response['response_timestamp'],
                $response['response_method'],
                $response['interviewer_id'],
                $response['completion_time_minutes']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving survey response: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update survey response rate
     */
    private function updateSurveyResponseRate(string $surveyId): bool
    {
        try {
            $db = Database::getInstance();

            // Calculate current response rate
            $sql = "SELECT COUNT(*) as total_responses FROM survey_responses WHERE survey_id = ?";
            $result = $db->fetch($sql, [$surveyId]);
            $totalResponses = $result ? $result['total_responses'] : 0;

            // Get survey sample size
            $sql = "SELECT sample_size FROM statistical_surveys WHERE survey_id = ?";
            $survey = $db->fetch($sql, [$surveyId]);

            if ($survey && $survey['sample_size'] > 0) {
                $responseRate = ($totalResponses / $survey['sample_size']) * 100;

                // Update survey response rate
                $sql = "UPDATE statistical_surveys SET response_rate = ? WHERE survey_id = ?";
                return $db->execute($sql, [$responseRate, $surveyId]);
            }

            return false;
        } catch (\Exception $e) {
            error_log("Error updating survey response rate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save statistical report
     */
    private function saveStatisticalReport(array $report): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO statistical_reports (
                report_id, report_title, report_type, description,
                publication_date, report_period_start, report_period_end,
                author, reviewer, status, key_findings, methodology,
                data_sources, limitations
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $report['report_id'],
                $report['report_title'],
                $report['report_type'],
                $report['description'],
                $report['publication_date'],
                $report['report_period_start'],
                $report['report_period_end'],
                $report['author'],
                $report['reviewer'],
                $report['status'],
                $report['key_findings'],
                $report['methodology'],
                $report['data_sources'],
                $report['limitations']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving statistical report: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification
     */
    private function sendNotification(string $type, ?int $userId, array $data = []): bool
    {
        try {
            $notificationManager = new NotificationManager();
            return $notificationManager->sendNotification($type, $userId, $data);
        } catch (\Exception $e) {
            error_log("Error sending notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate census response
     */
    private function validateCensusResponse(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'user_id', 'address', 'dwelling_type', 'number_of_rooms',
            'number_of_bedrooms', 'tenure_type', 'persons'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate persons array
        if (isset($data['persons']) && is_array($data['persons'])) {
            foreach ($data['persons'] as $index => $person) {
                $personErrors = $this->validatePersonData($person);
                if (!empty($personErrors)) {
                    $errors = array_merge($errors, array_map(function($error) use ($index) {
                        return "Person " . ($index + 1) . ": {$error}";
                    }, $personErrors));
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate person data
     */
    private function validatePersonData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'relationship_to_head', 'full_name', 'date_of_birth',
            'gender', 'marital_status', 'nationality', 'language_spoken',
            'education_level', 'employment_status'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate date of birth
        if (isset($data['date_of_birth'])) {
            $age = $this->calculateAge($data['date_of_birth']);
            if ($age < 0 || $age > 150) {
                $errors[] = "Invalid date of birth";
            }
        }

        return $errors;
    }

    /**
     * Validate survey response
     */
    private function validateSurveyResponse(array $data, array $survey): array
    {
        $errors = [];

        $requiredFields = ['respondent_id', 'responses'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate responses against survey questions
        if (isset($data['responses']) && isset($survey['survey_questions'])) {
            $questions = json_decode($survey['survey_questions'], true);
            if (is_array($questions)) {
                foreach ($questions as $question) {
                    if (isset($question['required']) && $question['required']) {
                        $questionId = $question['id'] ?? $question['question_id'];
                        if (!isset($data['responses'][$questionId]) || empty($data['responses'][$questionId])) {
                            $errors[] = "Required question not answered: {$question['text']}";
                        }
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate report data
     */
    private function validateReportData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'report_title', 'report_type', 'description',
            'publication_date', 'report_period_start', 'report_period_end', 'author'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate date sequence
        if (isset($data['report_period_start']) && isset($data['report_period_end'])) {
            if (strtotime($data['report_period_start']) > strtotime($data['report_period_end'])) {
                $errors[] = "Report period start date must be before end date";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
