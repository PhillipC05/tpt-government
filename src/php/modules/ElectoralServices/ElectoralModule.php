<?php
/**
 * TPT Government Platform - Electoral Services Module
 *
 * Comprehensive election management and voter services system
 * supporting voter registration, candidate management, election administration, and results reporting
 */

namespace Modules\ElectoralServices;

use Modules\ServiceModule;
use Core\Database;
use Core\WorkflowEngine;
use Core\NotificationManager;
use Core\PaymentGateway;

class ElectoralModule extends ServiceModule
{
    /**
     * Module metadata
     */
    protected array $metadata = [
        'name' => 'Electoral Services',
        'version' => '1.0.0',
        'description' => 'Comprehensive election management and voter services system',
        'author' => 'TPT Government Platform',
        'category' => 'electoral_services',
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
        'electoral.view' => 'View electoral information and records',
        'electoral.voter_registration' => 'Manage voter registration',
        'electoral.candidate_registration' => 'Manage candidate registration',
        'electoral.election_admin' => 'Administer elections',
        'electoral.results' => 'View and manage election results',
        'electoral.compliance' => 'Monitor electoral compliance',
        'electoral.reports' => 'Generate electoral reports'
    ];

    /**
     * Module database tables
     */
    protected array $tables = [
        'voters' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'voter_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'user_id' => 'INT NOT NULL',
            'national_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'full_name' => 'VARCHAR(255) NOT NULL',
            'date_of_birth' => 'DATE NOT NULL',
            'gender' => "ENUM('male','female','other','prefer_not_to_say') NOT NULL",
            'address' => 'TEXT NOT NULL',
            'electoral_district' => 'VARCHAR(100) NOT NULL',
            'polling_station' => 'VARCHAR(100) NOT NULL',
            'registration_date' => 'DATETIME NOT NULL',
            'status' => "ENUM('active','inactive','suspended','deceased') DEFAULT 'active'",
            'voter_category' => "ENUM('general','overseas','special','proxy') DEFAULT 'general'",
            'disability_status' => 'BOOLEAN DEFAULT FALSE',
            'language_preference' => 'VARCHAR(50) DEFAULT \'english\'',
            'contact_details' => 'JSON',
            'documents' => 'JSON',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'elections' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'election_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'election_name' => 'VARCHAR(255) NOT NULL',
            'election_type' => "ENUM('general','by_election','local','referendum','presidential') NOT NULL",
            'election_date' => 'DATE NOT NULL',
            'nomination_start' => 'DATE NOT NULL',
            'nomination_end' => 'DATE NOT NULL',
            'campaign_start' => 'DATE NOT NULL',
            'campaign_end' => 'DATE NOT NULL',
            'status' => "ENUM('planning','nomination','campaign','polling','counting','completed','cancelled') DEFAULT 'planning'",
            'total_voters' => 'INT DEFAULT 0',
            'total_votes_cast' => 'INT DEFAULT 0',
            'turnout_percentage' => 'DECIMAL(5,2) DEFAULT 0',
            'electoral_districts' => 'JSON',
            'election_rules' => 'JSON',
            'results' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'candidates' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'candidate_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'user_id' => 'INT NOT NULL',
            'election_id' => 'VARCHAR(20) NOT NULL',
            'full_name' => 'VARCHAR(255) NOT NULL',
            'party_affiliation' => 'VARCHAR(100)',
            'position_sought' => 'VARCHAR(100) NOT NULL',
            'electoral_district' => 'VARCHAR(100) NOT NULL',
            'nomination_date' => 'DATETIME NOT NULL',
            'qualification_status' => "ENUM('pending','qualified','disqualified') DEFAULT 'pending'",
            'withdrawal_date' => 'DATETIME NULL',
            'manifesto' => 'TEXT',
            'background_info' => 'JSON',
            'contact_details' => 'JSON',
            'documents' => 'JSON',
            'campaign_info' => 'JSON',
            'votes_received' => 'INT DEFAULT 0',
            'status' => "ENUM('nominated','qualified','withdrawn','elected','defeated') DEFAULT 'nominated'",
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'polling_stations' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'station_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'station_name' => 'VARCHAR(255) NOT NULL',
            'electoral_district' => 'VARCHAR(100) NOT NULL',
            'location' => 'TEXT NOT NULL',
            'capacity' => 'INT NOT NULL',
            'registered_voters' => 'INT DEFAULT 0',
            'votes_cast' => 'INT DEFAULT 0',
            'turnout_percentage' => 'DECIMAL(5,2) DEFAULT 0',
            'presiding_officer' => 'INT NULL',
            'assistant_officers' => 'JSON',
            'equipment_status' => 'JSON',
            'opening_time' => 'TIME NOT NULL',
            'closing_time' => 'TIME NOT NULL',
            'status' => "ENUM('active','inactive','closed') DEFAULT 'active'",
            'incident_reports' => 'JSON',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'votes' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'vote_id' => 'VARCHAR(30) UNIQUE NOT NULL',
            'election_id' => 'VARCHAR(20) NOT NULL',
            'voter_id' => 'VARCHAR(20) NOT NULL',
            'polling_station_id' => 'VARCHAR(20) NOT NULL',
            'candidate_id' => 'VARCHAR(20) NOT NULL',
            'vote_timestamp' => 'DATETIME NOT NULL',
            'vote_method' => "ENUM('in_person','postal','proxy','electronic') NOT NULL",
            'verification_code' => 'VARCHAR(10) NOT NULL',
            'ballot_number' => 'VARCHAR(20) NOT NULL',
            'status' => "ENUM('cast','verified','invalid','spoiled') DEFAULT 'cast'",
            'invalid_reason' => 'VARCHAR(100)',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
        ],
        'electoral_compliance' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'compliance_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'election_id' => 'VARCHAR(20) NOT NULL',
            'entity_type' => "ENUM('candidate','party','campaign','voter','station') NOT NULL",
            'entity_id' => 'VARCHAR(20) NOT NULL',
            'violation_type' => 'VARCHAR(100) NOT NULL',
            'description' => 'TEXT NOT NULL',
            'severity' => "ENUM('minor','moderate','major','critical') NOT NULL",
            'reported_date' => 'DATETIME NOT NULL',
            'investigation_status' => "ENUM('pending','investigating','resolved','dismissed') DEFAULT 'pending'",
            'resolution' => 'TEXT',
            'penalty' => 'JSON',
            'investigator_id' => 'INT NULL',
            'notes' => 'TEXT',
            'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ],
        'electoral_campaigns' => [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'campaign_id' => 'VARCHAR(20) UNIQUE NOT NULL',
            'candidate_id' => 'VARCHAR(20) NOT NULL',
            'election_id' => 'VARCHAR(20) NOT NULL',
            'campaign_name' => 'VARCHAR(255) NOT NULL',
            'campaign_type' => "ENUM('advertising','events','social_media','door_to_door','media') NOT NULL",
            'start_date' => 'DATE NOT NULL',
            'end_date' => 'DATE NOT NULL',
            'budget' => 'DECIMAL(12,2) NOT NULL',
            'funding_sources' => 'JSON',
            'campaign_materials' => 'JSON',
            'target_audience' => 'JSON',
            'performance_metrics' => 'JSON',
            'status' => "ENUM('planned','active','completed','suspended') DEFAULT 'planned'",
            'compliance_status' => "ENUM('compliant','under_review','violations_found') DEFAULT 'compliant'",
            'notes' => 'TEXT',
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
            'path' => '/api/electoral/voters',
            'handler' => 'getVoters',
            'auth' => true,
            'permissions' => ['electoral.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/electoral/voters',
            'handler' => 'registerVoter',
            'auth' => true,
            'permissions' => ['electoral.voter_registration']
        ],
        [
            'method' => 'GET',
            'path' => '/api/electoral/elections',
            'handler' => 'getElections',
            'auth' => true,
            'permissions' => ['electoral.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/electoral/elections',
            'handler' => 'createElection',
            'auth' => true,
            'permissions' => ['electoral.election_admin']
        ],
        [
            'method' => 'GET',
            'path' => '/api/electoral/candidates',
            'handler' => 'getCandidates',
            'auth' => true,
            'permissions' => ['electoral.view']
        ],
        [
            'method' => 'POST',
            'path' => '/api/electoral/candidates',
            'handler' => 'registerCandidate',
            'auth' => true,
            'permissions' => ['electoral.candidate_registration']
        ],
        [
            'method' => 'POST',
            'path' => '/api/electoral/vote',
            'handler' => 'castVote',
            'auth' => true,
            'permissions' => ['electoral.view']
        ],
        [
            'method' => 'GET',
            'path' => '/api/electoral/results/{electionId}',
            'handler' => 'getElectionResults',
            'auth' => true,
            'permissions' => ['electoral.results']
        ]
    ];

    /**
     * Module workflows
     */
    protected array $workflows = [
        'voter_registration_process' => [
            'name' => 'Voter Registration Process',
            'description' => 'Complete workflow for voter registration and verification',
            'steps' => [
                'draft' => ['name' => 'Application Draft', 'next' => 'submitted'],
                'submitted' => ['name' => 'Application Submitted', 'next' => 'verification'],
                'verification' => ['name' => 'Document Verification', 'next' => ['approved', 'additional_info']],
                'additional_info' => ['name' => 'Additional Information Required', 'next' => 'verification'],
                'approved' => ['name' => 'Registration Approved', 'next' => 'active'],
                'active' => ['name' => 'Voter Active', 'next' => null],
                'rejected' => ['name' => 'Application Rejected', 'next' => null]
            ]
        ],
        'candidate_nomination_process' => [
            'name' => 'Candidate Nomination Process',
            'description' => 'Workflow for candidate nomination and qualification',
            'steps' => [
                'nominated' => ['name' => 'Candidate Nominated', 'next' => 'qualification_check'],
                'qualification_check' => ['name' => 'Qualification Check', 'next' => ['qualified', 'disqualified']],
                'qualified' => ['name' => 'Candidate Qualified', 'next' => null],
                'disqualified' => ['name' => 'Candidate Disqualified', 'next' => null],
                'withdrawn' => ['name' => 'Candidate Withdrawn', 'next' => null]
            ]
        ],
        'election_process' => [
            'name' => 'Election Process',
            'description' => 'Complete election workflow from planning to completion',
            'steps' => [
                'planning' => ['name' => 'Election Planning', 'next' => 'nomination'],
                'nomination' => ['name' => 'Nomination Period', 'next' => 'campaign'],
                'campaign' => ['name' => 'Campaign Period', 'next' => 'polling'],
                'polling' => ['name' => 'Polling Day', 'next' => 'counting'],
                'counting' => ['name' => 'Vote Counting', 'next' => 'completed'],
                'completed' => ['name' => 'Election Completed', 'next' => null],
                'cancelled' => ['name' => 'Election Cancelled', 'next' => null]
            ]
        ]
    ];

    /**
     * Module forms
     */
    protected array $forms = [
        'voter_registration' => [
            'name' => 'Voter Registration Form',
            'fields' => [
                'full_name' => ['type' => 'text', 'required' => true, 'label' => 'Full Name'],
                'date_of_birth' => ['type' => 'date', 'required' => true, 'label' => 'Date of Birth'],
                'national_id' => ['type' => 'text', 'required' => true, 'label' => 'National ID Number'],
                'gender' => ['type' => 'select', 'required' => true, 'label' => 'Gender'],
                'address' => ['type' => 'textarea', 'required' => true, 'label' => 'Residential Address'],
                'electoral_district' => ['type' => 'select', 'required' => true, 'label' => 'Electoral District'],
                'contact_phone' => ['type' => 'tel', 'required' => true, 'label' => 'Contact Phone'],
                'contact_email' => ['type' => 'email', 'required' => true, 'label' => 'Contact Email'],
                'voter_category' => ['type' => 'select', 'required' => true, 'label' => 'Voter Category'],
                'disability_status' => ['type' => 'checkbox', 'required' => false, 'label' => 'Person with Disability'],
                'language_preference' => ['type' => 'select', 'required' => true, 'label' => 'Language Preference']
            ],
            'documents' => [
                'national_id_copy' => ['required' => true, 'label' => 'National ID Copy'],
                'proof_of_address' => ['required' => true, 'label' => 'Proof of Address'],
                'passport_photo' => ['required' => true, 'label' => 'Passport Size Photo'],
                'disability_certificate' => ['required' => false, 'label' => 'Disability Certificate']
            ]
        ],
        'candidate_registration' => [
            'name' => 'Candidate Registration Form',
            'fields' => [
                'full_name' => ['type' => 'text', 'required' => true, 'label' => 'Full Name'],
                'election_id' => ['type' => 'select', 'required' => true, 'label' => 'Election'],
                'position_sought' => ['type' => 'select', 'required' => true, 'label' => 'Position Sought'],
                'electoral_district' => ['type' => 'select', 'required' => true, 'label' => 'Electoral District'],
                'party_affiliation' => ['type' => 'text', 'required' => false, 'label' => 'Party Affiliation'],
                'contact_phone' => ['type' => 'tel', 'required' => true, 'label' => 'Contact Phone'],
                'contact_email' => ['type' => 'email', 'required' => true, 'label' => 'Contact Email'],
                'manifesto' => ['type' => 'textarea', 'required' => true, 'label' => 'Election Manifesto'],
                'qualifications' => ['type' => 'textarea', 'required' => true, 'label' => 'Qualifications and Experience'],
                'criminal_record' => ['type' => 'checkbox', 'required' => true, 'label' => 'No Criminal Record Declaration']
            ],
            'documents' => [
                'national_id_copy' => ['required' => true, 'label' => 'National ID Copy'],
                'educational_certificates' => ['required' => true, 'label' => 'Educational Certificates'],
                'police_clearance' => ['required' => true, 'label' => 'Police Clearance Certificate'],
                'party_nomination' => ['required' => false, 'label' => 'Party Nomination Letter'],
                'manifesto_document' => ['required' => true, 'label' => 'Detailed Manifesto Document']
            ]
        ],
        'election_creation' => [
            'name' => 'Election Creation Form',
            'fields' => [
                'election_name' => ['type' => 'text', 'required' => true, 'label' => 'Election Name'],
                'election_type' => ['type' => 'select', 'required' => true, 'label' => 'Election Type'],
                'election_date' => ['type' => 'date', 'required' => true, 'label' => 'Election Date'],
                'nomination_start' => ['type' => 'date', 'required' => true, 'label' => 'Nomination Start Date'],
                'nomination_end' => ['type' => 'date', 'required' => true, 'label' => 'Nomination End Date'],
                'campaign_start' => ['type' => 'date', 'required' => true, 'label' => 'Campaign Start Date'],
                'campaign_end' => ['type' => 'date', 'required' => true, 'label' => 'Campaign End Date'],
                'electoral_districts' => ['type' => 'textarea', 'required' => true, 'label' => 'Electoral Districts (JSON)'],
                'election_rules' => ['type' => 'textarea', 'required' => true, 'label' => 'Election Rules and Regulations']
            ]
        ]
    ];

    /**
     * Module reports
     */
    protected array $reports = [
        'voter_registration_report' => [
            'name' => 'Voter Registration Report',
            'description' => 'Summary of voter registration statistics',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'electoral_district' => ['type' => 'select', 'required' => false],
                'voter_category' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'total_registrations', 'active_voters', 'by_district',
                'by_age_group', 'by_gender', 'registration_trends'
            ]
        ],
        'election_results_report' => [
            'name' => 'Election Results Report',
            'description' => 'Comprehensive election results and analysis',
            'parameters' => [
                'election_id' => ['type' => 'select', 'required' => true],
                'electoral_district' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'candidate_name', 'party', 'votes_received', 'percentage',
                'electoral_district', 'position', 'status'
            ]
        ],
        'election_turnout_report' => [
            'name' => 'Election Turnout Report',
            'description' => 'Voter turnout analysis and statistics',
            'parameters' => [
                'election_id' => ['type' => 'select', 'required' => true],
                'electoral_district' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'electoral_district', 'registered_voters', 'votes_cast',
                'turnout_percentage', 'by_time_period', 'by_demographics'
            ]
        ],
        'electoral_compliance_report' => [
            'name' => 'Electoral Compliance Report',
            'description' => 'Monitoring of electoral law compliance',
            'parameters' => [
                'date_range' => ['type' => 'date_range', 'required' => false],
                'violation_type' => ['type' => 'select', 'required' => false]
            ],
            'columns' => [
                'total_violations', 'by_type', 'by_severity', 'resolution_rate',
                'penalties_issued', 'trends_over_time'
            ]
        ]
    ];

    /**
     * Module notifications
     */
    protected array $notifications = [
        'voter_registration_approved' => [
            'name' => 'Voter Registration Approved',
            'template' => 'Congratulations! Your voter registration has been approved. Your Voter ID is {voter_id}.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['voter_registration_approved']
        ],
        'election_announcement' => [
            'name' => 'Election Announcement',
            'template' => 'Election "{election_name}" has been announced for {election_date}. Nomination period: {nomination_start} to {nomination_end}.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['election_created']
        ],
        'candidate_qualified' => [
            'name' => 'Candidate Qualification Confirmed',
            'template' => 'Congratulations! You have been qualified as a candidate for {position_sought} in {electoral_district}.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['candidate_qualified']
        ],
        'voting_reminder' => [
            'name' => 'Election Voting Reminder',
            'template' => 'Reminder: Election day is tomorrow. Your polling station is {polling_station}.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['election_day_reminder']
        ],
        'election_results' => [
            'name' => 'Election Results Available',
            'template' => 'Election results for "{election_name}" are now available. Visit the portal to view detailed results.',
            'channels' => ['email', 'sms', 'in_app'],
            'triggers' => ['election_results_published']
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
            'voting_age' => 18,
            'voter_registration_deadline_days' => 21,
            'nomination_fee' => 1000.00,
            'campaign_spending_limit' => 50000.00,
            'polling_hours_start' => '07:00',
            'polling_hours_end' => '18:00',
            'results_verification_period_days' => 7,
            'auto_generate_voter_id' => true
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
     * Register voter (API handler)
     */
    public function registerVoter(array $voterData): array
    {
        // Validate voter data
        $validation = $this->validateVoterData($voterData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Check voting age
        $age = $this->calculateAge($voterData['date_of_birth']);
        if ($age < $this->config['voting_age']) {
            return [
                'success' => false,
                'error' => 'Must be at least ' . $this->config['voting_age'] . ' years old to register as a voter'
            ];
        }

        // Generate voter ID
        $voterId = $this->generateVoterId();

        // Create voter record
        $voter = [
            'voter_id' => $voterId,
            'user_id' => $voterData['user_id'],
            'national_id' => $voterData['national_id'],
            'full_name' => $voterData['full_name'],
            'date_of_birth' => $voterData['date_of_birth'],
            'gender' => $voterData['gender'],
            'address' => $voterData['address'],
            'electoral_district' => $voterData['electoral_district'],
            'polling_station' => $this->assignPollingStation($voterData['electoral_district']),
            'registration_date' => date('Y-m-d H:i:s'),
            'status' => 'active',
            'voter_category' => $voterData['voter_category'] ?? 'general',
            'disability_status' => $voterData['disability_status'] ?? false,
            'language_preference' => $voterData['language_preference'] ?? 'english',
            'contact_details' => json_encode([
                'phone' => $voterData['contact_phone'],
                'email' => $voterData['contact_email']
            ]),
            'documents' => json_encode($voterData['documents'] ?? []),
            'notes' => $voterData['notes'] ?? ''
        ];

        // Save to database
        $this->saveVoter($voter);

        // Send notification
        $this->sendNotification('voter_registration_approved', $voterData['user_id'], [
            'voter_id' => $voterId
        ]);

        return [
            'success' => true,
            'voter_id' => $voterId,
            'electoral_district' => $voterData['electoral_district'],
            'polling_station' => $voter['polling_station'],
            'message' => 'Voter registration completed successfully'
        ];
    }

    /**
     * Create election (API handler)
     */
    public function createElection(array $electionData): array
    {
        // Validate election data
        $validation = $this->validateElectionData($electionData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Generate election ID
        $electionId = $this->generateElectionId();

        // Create election record
        $election = [
            'election_id' => $electionId,
            'election_name' => $electionData['election_name'],
            'election_type' => $electionData['election_type'],
            'election_date' => $electionData['election_date'],
            'nomination_start' => $electionData['nomination_start'],
            'nomination_end' => $electionData['nomination_end'],
            'campaign_start' => $electionData['campaign_start'],
            'campaign_end' => $electionData['campaign_end'],
            'status' => 'planning',
            'electoral_districts' => json_encode(json_decode($electionData['electoral_districts'], true)),
            'election_rules' => json_encode(json_decode($electionData['election_rules'], true)),
            'results' => json_encode([])
        ];

        // Save to database
        $this->saveElection($election);

        // Send notification
        $this->sendElectionAnnouncement($election);

        return [
            'success' => true,
            'election_id' => $electionId,
            'election_name' => $electionData['election_name'],
            'status' => 'planning',
            'message' => 'Election created successfully'
        ];
    }

    /**
     * Register candidate (API handler)
     */
    public function registerCandidate(array $candidateData): array
    {
        // Validate candidate data
        $validation = $this->validateCandidateData($candidateData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Check if election exists and is in nomination period
        $election = $this->getElection($candidateData['election_id']);
        if (!$election || $election['status'] !== 'nomination') {
            return [
                'success' => false,
                'error' => 'Election not found or not in nomination period'
            ];
        }

        // Generate candidate ID
        $candidateId = $this->generateCandidateId();

        // Create candidate record
        $candidate = [
            'candidate_id' => $candidateId,
            'user_id' => $candidateData['user_id'],
            'election_id' => $candidateData['election_id'],
            'full_name' => $candidateData['full_name'],
            'party_affiliation' => $candidateData['party_affiliation'] ?? '',
            'position_sought' => $candidateData['position_sought'],
            'electoral_district' => $candidateData['electoral_district'],
            'nomination_date' => date('Y-m-d H:i:s'),
            'qualification_status' => 'pending',
            'manifesto' => $candidateData['manifesto'],
            'background_info' => json_encode([]),
            'contact_details' => json_encode([
                'phone' => $candidateData['contact_phone'],
                'email' => $candidateData['contact_email']
            ]),
            'documents' => json_encode($candidateData['documents'] ?? []),
            'campaign_info' => json_encode([]),
            'status' => 'nominated'
        ];

        // Save to database
        $this->saveCandidate($candidate);

        return [
            'success' => true,
            'candidate_id' => $candidateId,
            'election_id' => $candidateData['election_id'],
            'status' => 'nominated',
            'message' => 'Candidate registration submitted for qualification review'
        ];
    }

    /**
     * Cast vote (API handler)
     */
    public function castVote(array $voteData): array
    {
        // Validate vote data
        $validation = $this->validateVoteData($voteData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Check if election is active
        $election = $this->getElection($voteData['election_id']);
        if (!$election || $election['status'] !== 'polling') {
            return [
                'success' => false,
                'error' => 'Election not found or not in polling phase'
            ];
        }

        // Check if voter is registered and eligible
        $voter = $this->getVoter($voteData['voter_id']);
        if (!$voter || $voter['status'] !== 'active') {
            return [
                'success' => false,
                'error' => 'Voter not found or not eligible to vote'
            ];
        }

        // Check if voter has already voted
        if ($this->hasVoterVoted($voteData['voter_id'], $voteData['election_id'])) {
            return [
                'success' => false,
                'error' => 'Voter has already cast their vote in this election'
            ];
        }

        // Generate vote ID and verification code
        $voteId = $this->generateVoteId();
        $verificationCode = $this->generateVerificationCode();

        // Create vote record
        $vote = [
            'vote_id' => $voteId,
            'election_id' => $voteData['election_id'],
            'voter_id' => $voteData['voter_id'],
            'polling_station_id' => $voteData['polling_station_id'],
            'candidate_id' => $voteData['candidate_id'],
            'vote_timestamp' => date('Y-m-d H:i:s'),
            'vote_method' => $voteData['vote_method'] ?? 'in_person',
            'verification_code' => $verificationCode,
            'ballot_number' => $this->generateBallotNumber(),
            'status' => 'cast'
        ];

        // Save to database
        $this->saveVote($vote);

        // Update election vote count
        $this->incrementElectionVoteCount($voteData['election_id']);

        return [
            'success' => true,
            'vote_id' => $voteId,
            'verification_code' => $verificationCode,
            'timestamp' => $vote['vote_timestamp'],
            'message' => 'Vote cast successfully'
        ];
    }

    /**
     * Get voters (API handler)
     */
    public function getVoters(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM voters WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['electoral_district'])) {
                $sql .= " AND electoral_district = ?";
                $params[] = $filters['electoral_district'];
            }

            if (isset($filters['user_id'])) {
                $sql .= " AND user_id = ?";
                $params[] = $filters['user_id'];
            }

            $sql .= " ORDER BY registration_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['contact_details'] = json_decode($result['contact_details'], true);
                $result['documents'] = json_decode($result['documents'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting voters: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve voters'
            ];
        }
    }

    /**
     * Get elections (API handler)
     */
    public function getElections(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM elections WHERE 1=1";
            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['type'])) {
                $sql .= " AND election_type = ?";
                $params[] = $filters['type'];
            }

            $sql .= " ORDER BY election_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['electoral_districts'] = json_decode($result['electoral_districts'], true);
                $result['election_rules'] = json_decode($result['election_rules'], true);
                $result['results'] = json_decode($result['results'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting elections: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve elections'
            ];
        }
    }

    /**
     * Get candidates (API handler)
     */
    public function getCandidates(array $filters = []): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM candidates WHERE 1=1";
            $params = [];

            if (isset($filters['election_id'])) {
                $sql .= " AND election_id = ?";
                $params[] = $filters['election_id'];
            }

            if (isset($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (isset($filters['electoral_district'])) {
                $sql .= " AND electoral_district = ?";
                $params[] = $filters['electoral_district'];
            }

            $sql .= " ORDER BY nomination_date DESC";

            $results = $db->fetchAll($sql, $params);

            // Decode JSON fields
            foreach ($results as &$result) {
                $result['background_info'] = json_decode($result['background_info'], true);
                $result['contact_details'] = json_decode($result['contact_details'], true);
                $result['documents'] = json_decode($result['documents'], true);
                $result['campaign_info'] = json_decode($result['campaign_info'], true);
            }

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            error_log("Error getting candidates: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve candidates'
            ];
        }
    }

    /**
     * Get election results (API handler)
     */
    public function getElectionResults(string $electionId): array
    {
        $election = $this->getElection($electionId);
        if (!$election) {
            return [
                'success' => false,
                'error' => 'Election not found'
            ];
        }

        if ($election['status'] !== 'completed') {
            return [
                'success' => false,
                'error' => 'Election results not yet available'
            ];
        }

        $results = $this->calculateElectionResults($electionId);

        return [
            'success' => true,
            'election_name' => $election['election_name'],
            'election_date' => $election['election_date'],
            'total_votes' => $election['total_votes_cast'],
            'turnout_percentage' => $election['turnout_percentage'],
            'results' => $results,
            'timestamp' => date('c')
        ];
    }

    /**
     * Generate voter ID
     */
    private function generateVoterId(): string
    {
        return 'VOT' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate election ID
     */
    private function generateElectionId(): string
    {
        return 'ELE' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate candidate ID
     */
    private function generateCandidateId(): string
    {
        return 'CAN' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate vote ID
     */
    private function generateVoteId(): string
    {
        return 'VOTE' . date('YmdHis') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate verification code
     */
    private function generateVerificationCode(): string
    {
        return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
    }

    /**
     * Generate ballot number
     */
    private function generateBallotNumber(): string
    {
        return 'BALLOT' . date('Ymd') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
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
     * Assign polling station based on electoral district
     */
    private function assignPollingStation(string $electoralDistrict): string
    {
        // This would typically query the database for available polling stations
        // For now, return a default assignment
        return 'Station_' . strtoupper(substr($electoralDistrict, 0, 3)) . '_001';
    }

    /**
     * Save voter
     */
    private function saveVoter(array $voter): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO voters (
                voter_id, user_id, national_id, full_name, date_of_birth,
                gender, address, electoral_district, polling_station,
                registration_date, status, voter_category, disability_status,
                language_preference, contact_details, documents, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $voter['voter_id'],
                $voter['user_id'],
                $voter['national_id'],
                $voter['full_name'],
                $voter['date_of_birth'],
                $voter['gender'],
                $voter['address'],
                $voter['electoral_district'],
                $voter['polling_station'],
                $voter['registration_date'],
                $voter['status'],
                $voter['voter_category'],
                $voter['disability_status'],
                $voter['language_preference'],
                $voter['contact_details'],
                $voter['documents'],
                $voter['notes']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving voter: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save election
     */
    private function saveElection(array $election): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO elections (
                election_id, election_name, election_type, election_date,
                nomination_start, nomination_end, campaign_start, campaign_end,
                status, electoral_districts, election_rules, results
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $election['election_id'],
                $election['election_name'],
                $election['election_type'],
                $election['election_date'],
                $election['nomination_start'],
                $election['nomination_end'],
                $election['campaign_start'],
                $election['campaign_end'],
                $election['status'],
                $election['electoral_districts'],
                $election['election_rules'],
                $election['results']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving election: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save candidate
     */
    private function saveCandidate(array $candidate): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO candidates (
                candidate_id, user_id, election_id, full_name, party_affiliation,
                position_sought, electoral_district, nomination_date, qualification_status,
                manifesto, background_info, contact_details, documents, campaign_info, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $candidate['candidate_id'],
                $candidate['user_id'],
                $candidate['election_id'],
                $candidate['full_name'],
                $candidate['party_affiliation'],
                $candidate['position_sought'],
                $candidate['electoral_district'],
                $candidate['nomination_date'],
                $candidate['qualification_status'],
                $candidate['manifesto'],
                $candidate['background_info'],
                $candidate['contact_details'],
                $candidate['documents'],
                $candidate['campaign_info'],
                $candidate['status']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving candidate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save vote
     */
    private function saveVote(array $vote): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO votes (
                vote_id, election_id, voter_id, polling_station_id,
                candidate_id, vote_timestamp, vote_method, verification_code,
                ballot_number, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $vote['vote_id'],
                $vote['election_id'],
                $vote['voter_id'],
                $vote['polling_station_id'],
                $vote['candidate_id'],
                $vote['vote_timestamp'],
                $vote['vote_method'],
                $vote['verification_code'],
                $vote['ballot_number'],
                $vote['status']
            ];

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Error saving vote: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get election
     */
    private function getElection(string $electionId): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM elections WHERE election_id = ?";
            $result = $db->fetch($sql, [$electionId]);

            if ($result) {
                $result['electoral_districts'] = json_decode($result['electoral_districts'], true);
                $result['election_rules'] = json_decode($result['election_rules'], true);
                $result['results'] = json_decode($result['results'], true);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error getting election: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get voter
     */
    private function getVoter(string $voterId): ?array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT * FROM voters WHERE voter_id = ?";
            $result = $db->fetch($sql, [$voterId]);

            if ($result) {
                $result['contact_details'] = json_decode($result['contact_details'], true);
                $result['documents'] = json_decode($result['documents'], true);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Error getting voter: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if voter has already voted
     */
    private function hasVoterVoted(string $voterId, string $electionId): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT COUNT(*) as count FROM votes WHERE voter_id = ? AND election_id = ?";
            $result = $db->fetch($sql, [$voterId, $electionId]);

            return $result && $result['count'] > 0;
        } catch (\Exception $e) {
            error_log("Error checking voter vote status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Increment election vote count
     */
    private function incrementElectionVoteCount(string $electionId): bool
    {
        try {
            $db = Database::getInstance();

            $sql = "UPDATE elections SET total_votes_cast = total_votes_cast + 1 WHERE election_id = ?";
            return $db->execute($sql, [$electionId]);
        } catch (\Exception $e) {
            error_log("Error incrementing election vote count: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate election results
     */
    private function calculateElectionResults(string $electionId): array
    {
        try {
            $db = Database::getInstance();

            $sql = "SELECT
                c.candidate_id,
                c.full_name,
                c.party_affiliation,
                c.electoral_district,
                c.position_sought,
                COUNT(v.id) as votes_received,
                ROUND((COUNT(v.id) / (SELECT COUNT(*) FROM votes WHERE election_id = ?)) * 100, 2) as percentage
            FROM candidates c
            LEFT JOIN votes v ON c.candidate_id = v.candidate_id AND v.status = 'verified'
            WHERE c.election_id = ?
            GROUP BY c.candidate_id
            ORDER BY votes_received DESC";

            $results = $db->fetchAll($sql, [$electionId, $electionId]);

            return $results ?: [];
        } catch (\Exception $e) {
            error_log("Error calculating election results: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Send election announcement
     */
    private function sendElectionAnnouncement(array $election): bool
    {
        try {
            $this->sendNotification('election_announcement', null, [
                'election_name' => $election['election_name'],
                'election_date' => $election['election_date'],
                'nomination_start' => $election['nomination_start'],
                'nomination_end' => $election['nomination_end']
            ]);

            return true;
        } catch (\Exception $e) {
            error_log("Error sending election announcement: " . $e->getMessage());
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
     * Validate voter data
     */
    private function validateVoterData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'user_id', 'full_name', 'date_of_birth', 'national_id',
            'gender', 'address', 'electoral_district', 'contact_phone', 'contact_email'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate email format
        if (isset($data['contact_email']) && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        // Validate national ID uniqueness
        if (isset($data['national_id'])) {
            try {
                $db = Database::getInstance();
                $sql = "SELECT COUNT(*) as count FROM voters WHERE national_id = ?";
                $result = $db->fetch($sql, [$data['national_id']]);
                if ($result && $result['count'] > 0) {
                    $errors[] = "National ID already registered";
                }
            } catch (\Exception $e) {
                $errors[] = "Error validating national ID";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate election data
     */
    private function validateElectionData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'election_name', 'election_type', 'election_date',
            'nomination_start', 'nomination_end', 'campaign_start',
            'campaign_end', 'electoral_districts', 'election_rules'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate date sequence
        if (isset($data['nomination_start']) && isset($data['nomination_end'])) {
            if (strtotime($data['nomination_start']) >= strtotime($data['nomination_end'])) {
                $errors[] = "Nomination end date must be after start date";
            }
        }

        if (isset($data['campaign_start']) && isset($data['campaign_end'])) {
            if (strtotime($data['campaign_start']) >= strtotime($data['campaign_end'])) {
                $errors[] = "Campaign end date must be after start date";
            }
        }

        if (isset($data['election_date']) && isset($data['campaign_end'])) {
            if (strtotime($data['campaign_end']) >= strtotime($data['election_date'])) {
                $errors[] = "Election date must be after campaign end date";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate candidate data
     */
    private function validateCandidateData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'user_id', 'election_id', 'full_name', 'position_sought',
            'electoral_district', 'contact_phone', 'contact_email',
            'manifesto', 'qualifications'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        // Validate email format
        if (isset($data['contact_email']) && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        // Validate criminal record declaration
        if (!isset($data['criminal_record']) || $data['criminal_record'] !== true) {
            $errors[] = "Must declare no criminal record";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate vote data
     */
    private function validateVoteData(array $data): array
    {
        $errors = [];

        $requiredFields = [
            'election_id', 'voter_id', 'polling_station_id', 'candidate_id'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field missing: {$field}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
