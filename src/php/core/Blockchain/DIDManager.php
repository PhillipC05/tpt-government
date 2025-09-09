<?php
/**
 * TPT Government Platform - DID Manager
 *
 * Specialized manager for Decentralized Identity (DID) operations and management
 */

namespace Core\Blockchain;

use Core\Database;

class DIDManager
{
    /**
     * DID configuration
     */
    private array $config = [
        'enabled' => true,
        'supported_methods' => ['did:ethr', 'did:web', 'did:key', 'did:gov'],
        'default_method' => 'did:gov',
        'verification_enabled' => true,
        'credential_issuance' => true,
        'selective_disclosure' => true,
        'zero_knowledge_proofs' => true,
        'revocation_enabled' => true,
        'delegation_enabled' => true
    ];

    /**
     * Managed DIDs
     */
    private array $dids = [];

    /**
     * Verifiable credentials
     */
    private array $credentials = [];

    /**
     * DID documents
     */
    private array $documents = [];

    /**
     * Verification results
     */
    private array $verifications = [];

    /**
     * Revocation registry
     */
    private array $revocations = [];

    /**
     * Delegation records
     */
    private array $delegations = [];

    /**
     * Database connection
     */
    private Database $database;

    /**
     * Constructor
     */
    public function __construct(Database $database, array $config = [])
    {
        $this->database = $database;
        $this->config = array_merge($this->config, $config);
        $this->initializeDIDManager();
    }

    /**
     * Initialize DID manager
     */
    private function initializeDIDManager(): void
    {
        // Load existing DIDs
        $this->loadDIDs();

        // Initialize DID methods
        $this->initializeDIDMethods();

        // Set up verification system
        $this->setupVerificationSystem();

        // Initialize credential system
        $this->initializeCredentialSystem();
    }

    /**
     * Create new DID
     */
    public function createDID(string $controller, array $options = []): array
    {
        $method = $options['method'] ?? $this->config['default_method'];
        $didId = $this->generateDID($method);

        $did = [
            'id' => $didId,
            'controller' => $controller,
            'method' => $method,
            'status' => 'active',
            'created_at' => time(),
            'updated_at' => time(),
            'public_keys' => $this->generateKeyPair($method),
            'authentication' => [$didId . '#keys-1'],
            'assertion_method' => [$didId . '#keys-1'],
            'key_agreement' => [$didId . '#keys-2'],
            'capability_invocation' => [$didId . '#keys-1'],
            'capability_delegation' => [$didId . '#keys-1'],
            'service_endpoints' => $options['service_endpoints'] ?? [],
            'metadata' => $options['metadata'] ?? []
        ];

        // Create DID document
        $document = $this->createDIDDocument($did);
        $did['document'] = $document;

        $this->dids[$didId] = $did;
        $this->documents[$didId] = $document;

        $this->saveDID($didId, $did);
        $this->saveDIDDocument($didId, $document);

        return [
            'success' => true,
            'did' => $didId,
            'document' => $document,
            'message' => 'DID created successfully'
        ];
    }

    /**
     * Resolve DID
     */
    public function resolveDID(string $did): array
    {
        if (!isset($this->dids[$did])) {
            return [
                'success' => false,
                'error' => 'DID not found'
            ];
        }

        $didData = $this->dids[$did];
        $document = $this->documents[$did] ?? $this->createDIDDocument($didData);

        return [
            'success' => true,
            'did' => $did,
            'document' => $document,
            'metadata' => [
                'created' => date('c', $didData['created_at']),
                'updated' => date('c', $didData['updated_at']),
                'status' => $didData['status']
            ]
        ];
    }

    /**
     * Update DID
     */
    public function updateDID(string $did, array $updates): array
    {
        if (!isset($this->dids[$did])) {
            return [
                'success' => false,
                'error' => 'DID not found'
            ];
        }

        $didData = $this->dids[$did];
        $didData = array_merge($didData, $updates);
        $didData['updated_at'] = time();

        // Update DID document
        $document = $this->createDIDDocument($didData);
        $didData['document'] = $document;

        $this->dids[$did] = $didData;
        $this->documents[$did] = $document;

        $this->saveDID($did, $didData);
        $this->saveDIDDocument($did, $document);

        return [
            'success' => true,
            'did' => $did,
            'message' => 'DID updated successfully'
        ];
    }

    /**
     * Deactivate DID
     */
    public function deactivateDID(string $did): array
    {
        if (!isset($this->dids[$did])) {
            return [
                'success' => false,
                'error' => 'DID not found'
            ];
        }

        $this->dids[$did]['status'] = 'deactivated';
        $this->dids[$did]['updated_at'] = time();

        $this->saveDID($did, $this->dids[$did]);

        return [
            'success' => true,
            'did' => $did,
            'message' => 'DID deactivated successfully'
        ];
    }

    /**
     * Issue verifiable credential
     */
    public function issueCredential(string $issuerDID, string $subjectDID, array $credentialData): array
    {
        if (!$this->config['credential_issuance']) {
            return [
                'success' => false,
                'error' => 'Credential issuance is disabled'
            ];
        }

        $credentialId = 'urn:uuid:' . uniqid();

        $credential = [
            '@context' => [
                'https://www.w3.org/2018/credentials/v1',
                'https://www.w3.org/2018/credentials/examples/v1'
            ],
            'id' => $credentialId,
            'type' => ['VerifiableCredential', $credentialData['type'] ?? 'GovernmentCredential'],
            'issuer' => $issuerDID,
            'issuanceDate' => date('c'),
            'credentialSubject' => array_merge([
                'id' => $subjectDID
            ], $credentialData['subject'] ?? []),
            'proof' => $this->generateCredentialProof($credential, $issuerDID)
        ];

        if (isset($credentialData['expirationDate'])) {
            $credential['expirationDate'] = $credentialData['expirationDate'];
        }

        $this->credentials[$credentialId] = $credential;
        $this->saveCredential($credentialId, $credential);

        return [
            'success' => true,
            'credential' => $credential,
            'message' => 'Credential issued successfully'
        ];
    }

    /**
     * Verify credential
     */
    public function verifyCredential(array $credential): array
    {
        if (!$this->config['verification_enabled']) {
            return [
                'success' => false,
                'error' => 'Credential verification is disabled'
            ];
        }

        $verificationId = uniqid('ver_');

        // Verify issuer
        $issuerVerification = $this->verifyIssuer($credential['issuer'] ?? '');

        // Verify signature
        $signatureVerification = $this->verifyCredentialSignature($credential);

        // Check expiration
        $expirationCheck = $this->checkCredentialExpiration($credential);

        // Check revocation
        $revocationCheck = $this->checkCredentialRevocation($credential['id'] ?? '');

        $isValid = $issuerVerification['valid'] &&
                  $signatureVerification['valid'] &&
                  $expirationCheck['valid'] &&
                  $revocationCheck['valid'];

        $verification = [
            'id' => $verificationId,
            'credential_id' => $credential['id'] ?? '',
            'timestamp' => time(),
            'valid' => $isValid,
            'checks' => [
                'issuer' => $issuerVerification,
                'signature' => $signatureVerification,
                'expiration' => $expirationCheck,
                'revocation' => $revocationCheck
            ]
        ];

        $this->verifications[$verificationId] = $verification;
        $this->saveVerification($verificationId, $verification);

        return [
            'success' => true,
            'verification_id' => $verificationId,
            'valid' => $isValid,
            'details' => $verification['checks'],
            'message' => $isValid ? 'Credential is valid' : 'Credential verification failed'
        ];
    }

    /**
     * Revoke credential
     */
    public function revokeCredential(string $credentialId, string $reason = ''): array
    {
        if (!$this->config['revocation_enabled']) {
            return [
                'success' => false,
                'error' => 'Credential revocation is disabled'
            ];
        }

        if (!isset($this->credentials[$credentialId])) {
            return [
                'success' => false,
                'error' => 'Credential not found'
            ];
        }

        $revocationId = uniqid('rev_');

        $revocation = [
            'id' => $revocationId,
            'credential_id' => $credentialId,
            'reason' => $reason,
            'revoked_at' => time(),
            'revoked_by' => 'system' // In real implementation, this would be the authorized revoker
        ];

        $this->revocations[$revocationId] = $revocation;
        $this->saveRevocation($revocationId, $revocation);

        return [
            'success' => true,
            'revocation_id' => $revocationId,
            'credential_id' => $credentialId,
            'message' => 'Credential revoked successfully'
        ];
    }

    /**
     * Create delegation
     */
    public function createDelegation(string $delegatorDID, string $delegateDID, array $permissions): array
    {
        if (!$this->config['delegation_enabled']) {
            return [
                'success' => false,
                'error' => 'Delegation is disabled'
            ];
        }

        $delegationId = uniqid('del_');

        $delegation = [
            'id' => $delegationId,
            'delegator_did' => $delegatorDID,
            'delegate_did' => $delegateDID,
            'permissions' => $permissions,
            'status' => 'active',
            'created_at' => time(),
            'expires_at' => $permissions['expires_at'] ?? null,
            'proof' => $this->generateDelegationProof($delegatorDID, $delegateDID, $permissions)
        ];

        $this->delegations[$delegationId] = $delegation;
        $this->saveDelegation($delegationId, $delegation);

        return [
            'success' => true,
            'delegation_id' => $delegationId,
            'delegation' => $delegation,
            'message' => 'Delegation created successfully'
        ];
    }

    /**
     * Verify presentation (selective disclosure)
     */
    public function verifyPresentation(array $presentation): array
    {
        if (!$this->config['selective_disclosure']) {
            return [
                'success' => false,
                'error' => 'Selective disclosure is disabled'
            ];
        }

        $verificationId = uniqid('pres_ver_');

        // Verify presentation structure
        $structureCheck = $this->verifyPresentationStructure($presentation);

        // Verify credentials
        $credentialChecks = [];
        foreach ($presentation['verifiableCredential'] ?? [] as $credential) {
            $credentialChecks[] = $this->verifyCredential($credential);
        }

        // Verify proof
        $proofCheck = $this->verifyPresentationProof($presentation);

        $isValid = $structureCheck['valid'] &&
                  !in_array(false, array_column($credentialChecks, 'valid')) &&
                  $proofCheck['valid'];

        $verification = [
            'id' => $verificationId,
            'presentation_id' => $presentation['id'] ?? '',
            'timestamp' => time(),
            'valid' => $isValid,
            'checks' => [
                'structure' => $structureCheck,
                'credentials' => $credentialChecks,
                'proof' => $proofCheck
            ]
        ];

        $this->verifications[$verificationId] = $verification;
        $this->saveVerification($verificationId, $verification);

        return [
            'success' => true,
            'verification_id' => $verificationId,
            'valid' => $isValid,
            'details' => $verification['checks'],
            'message' => $isValid ? 'Presentation is valid' : 'Presentation verification failed'
        ];
    }

    /**
     * Get DID analytics
     */
    public function getDIDAnalytics(): array
    {
        $analytics = [
            'total_dids' => count($this->dids),
            'active_dids' => count(array_filter($this->dids, fn($did) => $did['status'] === 'active')),
            'total_credentials' => count($this->credentials),
            'verified_credentials' => count(array_filter($this->credentials, function($cred) {
                $verification = $this->verifications[array_key_last($this->verifications)] ?? null;
                return $verification && $verification['valid'];
            })),
            'revoked_credentials' => count($this->revocations),
            'total_delegations' => count($this->delegations),
            'active_delegations' => count(array_filter($this->delegations, fn($del) => $del['status'] === 'active')),
            'verification_requests' => count($this->verifications),
            'successful_verifications' => count(array_filter($this->verifications, fn($ver) => $ver['valid'])),
            'dids_by_method' => $this->getDIDsByMethod(),
            'credential_types' => $this->getCredentialTypes()
        ];

        return [
            'success' => true,
            'analytics' => $analytics,
            'generated_at' => time()
        ];
    }

    // Private helper methods

    private function generateDID(string $method): string
    {
        $uniqueId = bin2hex(random_bytes(16));
        return "did:{$method}:{$uniqueId}";
    }

    private function generateKeyPair(string $method): array
    {
        // Generate mock key pair
        return [
            'keys-1' => [
                'id' => 'keys-1',
                'type' => 'Ed25519VerificationKey2018',
                'controller' => '', // Will be set when DID is created
                'publicKeyBase58' => base64_encode(random_bytes(32))
            ],
            'keys-2' => [
                'id' => 'keys-2',
                'type' => 'X25519KeyAgreementKey2019',
                'controller' => '', // Will be set when DID is created
                'publicKeyBase58' => base64_encode(random_bytes(32))
            ]
        ];
    }

    private function createDIDDocument(array $did): array
    {
        $document = [
            '@context' => 'https://www.w3.org/ns/did/v1',
            'id' => $did['id'],
            'controller' => $did['controller'],
            'verificationMethod' => []
        ];

        // Add verification methods
        foreach ($did['public_keys'] as $keyId => $keyData) {
            $document['verificationMethod'][] = [
                'id' => $did['id'] . '#' . $keyId,
                'type' => $keyData['type'],
                'controller' => $did['id'],
                'publicKeyBase58' => $keyData['publicKeyBase58']
            ];
        }

        // Add verification relationships
        if (!empty($did['authentication'])) {
            $document['authentication'] = $did['authentication'];
        }
        if (!empty($did['assertion_method'])) {
            $document['assertionMethod'] = $did['assertion_method'];
        }
        if (!empty($did['key_agreement'])) {
            $document['keyAgreement'] = $did['key_agreement'];
        }
        if (!empty($did['capability_invocation'])) {
            $document['capabilityInvocation'] = $did['capability_invocation'];
        }
        if (!empty($did['capability_delegation'])) {
            $document['capabilityDelegation'] = $did['capability_delegation'];
        }

        // Add service endpoints
        if (!empty($did['service_endpoints'])) {
            $document['service'] = $did['service_endpoints'];
        }

        return $document;
    }

    private function generateCredentialProof(array $credential, string $issuerDID): array
    {
        // Generate mock proof
        return [
            'type' => 'Ed25519Signature2018',
            'created' => date('c'),
            'verificationMethod' => $issuerDID . '#keys-1',
            'proofPurpose' => 'assertionMethod',
            'jws' => 'eyJhbGciOiJFZERTQSIsImI2NCI6ZmFsc2UsImNyaXQiOlsiYjY0Il19..' . base64_encode(random_bytes(64))
        ];
    }

    private function verifyIssuer(string $issuerDID): array
    {
        $resolution = $this->resolveDID($issuerDID);
        return [
            'valid' => $resolution['success'] && ($this->dids[$issuerDID]['status'] ?? '') === 'active',
            'details' => $resolution['success'] ? 'Issuer DID is valid and active' : 'Issuer DID resolution failed'
        ];
    }

    private function verifyCredentialSignature(array $credential): array
    {
        // Mock signature verification
        return [
            'valid' => true,
            'details' => 'Signature verification successful'
        ];
    }

    private function checkCredentialExpiration(array $credential): array
    {
        if (!isset($credential['expirationDate'])) {
            return ['valid' => true, 'details' => 'No expiration date set'];
        }

        $isExpired = strtotime($credential['expirationDate']) < time();
        return [
            'valid' => !$isExpired,
            'details' => $isExpired ? 'Credential has expired' : 'Credential is not expired'
        ];
    }

    private function checkCredentialRevocation(string $credentialId): array
    {
        $isRevoked = !empty(array_filter($this->revocations, fn($rev) => $rev['credential_id'] === $credentialId));
        return [
            'valid' => !$isRevoked,
            'details' => $isRevoked ? 'Credential has been revoked' : 'Credential is not revoked'
        ];
    }

    private function generateDelegationProof(string $delegatorDID, string $delegateDID, array $permissions): array
    {
        // Generate mock delegation proof
        return [
            'type' => 'Ed25519Signature2018',
            'created' => date('c'),
            'verificationMethod' => $delegatorDID . '#keys-1',
            'proofPurpose' => 'capabilityDelegation',
            'jws' => 'eyJhbGciOiJFZERTQSIsImI2NCI6ZmFsc2UsImNyaXQiOlsiYjY0Il19..' . base64_encode(random_bytes(64))
        ];
    }

    private function verifyPresentationStructure(array $presentation): array
    {
        $requiredFields = ['@context', 'type', 'verifiableCredential', 'proof'];
        $hasRequiredFields = !empty(array_intersect($requiredFields, array_keys($presentation)));

        return [
            'valid' => $hasRequiredFields,
            'details' => $hasRequiredFields ? 'Presentation structure is valid' : 'Missing required fields in presentation'
        ];
    }

    private function verifyPresentationProof(array $presentation): array
    {
        // Mock proof verification
        return [
            'valid' => true,
            'details' => 'Presentation proof verification successful'
        ];
    }

    private function getDIDsByMethod(): array
    {
        $methods = [];
        foreach ($this->dids as $did) {
            $method = $did['method'];
            $methods[$method] = ($methods[$method] ?? 0) + 1;
        }
        return $methods;
    }

    private function getCredentialTypes(): array
    {
        $types = [];
        foreach ($this->credentials as $credential) {
            $credentialTypes = $credential['type'] ?? [];
            foreach ($credentialTypes as $type) {
                if ($type !== 'VerifiableCredential') {
                    $types[$type] = ($types[$type] ?? 0) + 1;
                }
            }
        }
        return $types;
    }

    // Database operations (mock implementations)

    private function loadDIDs(): void
    {
        // Load DIDs from database
    }

    private function saveDID(string $did, array $didData): void
    {
        // Save DID to database
    }

    private function saveDIDDocument(string $did, array $document): void
    {
        // Save DID document to database
    }

    private function saveCredential(string $credentialId, array $credential): void
    {
        // Save credential to database
    }

    private function saveVerification(string $verificationId, array $verification): void
    {
        // Save verification to database
    }

    private function saveRevocation(string $revocationId, array $revocation): void
    {
        // Save revocation to database
    }

    private function saveDelegation(string $delegationId, array $delegation): void
    {
        // Save delegation to database
    }

    private function initializeDIDMethods(): void
    {
        // Initialize DID methods
    }

    private function setupVerificationSystem(): void
    {
        // Set up verification system
    }

    private function initializeCredentialSystem(): void
    {
        // Initialize credential system
    }
}
