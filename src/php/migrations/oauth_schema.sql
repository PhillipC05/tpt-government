-- OAuth 2.0 & OpenID Connect Database Schema
-- Token storage and OAuth/OIDC management for government platform

-- OAuth tokens storage
CREATE TABLE IF NOT EXISTS oauth_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    id_token TEXT NULL,
    token_type VARCHAR(50) DEFAULT 'Bearer',
    scope TEXT NULL,
    expires_at TIMESTAMP NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rotated_at TIMESTAMP NULL,
    revoked BOOLEAN DEFAULT FALSE,
    revocation_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_revoked (revoked),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- OAuth clients (for when platform acts as OAuth server)
CREATE TABLE IF NOT EXISTS oauth_clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id VARCHAR(255) NOT NULL UNIQUE,
    client_secret VARCHAR(255) NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    client_type VARCHAR(50) NOT NULL DEFAULT 'confidential', -- confidential, public
    redirect_uris JSON NOT NULL,
    allowed_grant_types JSON NOT NULL,
    allowed_scopes JSON NOT NULL,
    token_endpoint_auth_method VARCHAR(50) DEFAULT 'client_secret_basic',
    jwks_uri VARCHAR(500) NULL,
    id_token_signed_response_alg VARCHAR(10) DEFAULT 'RS256',
    userinfo_signed_response_alg VARCHAR(10) DEFAULT 'RS256',
    request_object_signing_alg VARCHAR(10) DEFAULT 'RS256',
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client_id (client_id),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- OAuth authorization codes
CREATE TABLE IF NOT EXISTS oauth_auth_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(255) NOT NULL UNIQUE,
    client_id VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    redirect_uri VARCHAR(500) NOT NULL,
    scopes JSON NULL,
    expires_at TIMESTAMP NOT NULL,
    code_challenge VARCHAR(255) NULL,
    code_challenge_method VARCHAR(10) DEFAULT 'S256',
    used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_client_id (client_id),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_used (used),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- OAuth sessions (for OpenID Connect)
CREATE TABLE IF NOT EXISTS oauth_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    client_id VARCHAR(255) NOT NULL,
    scopes JSON NULL,
    nonce VARCHAR(255) NULL,
    auth_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    max_age INT NULL,
    acr_values VARCHAR(255) NULL,
    amr_values JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_client_id (client_id),
    INDEX idx_is_active (is_active),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- OAuth consent records
CREATE TABLE IF NOT EXISTS oauth_consents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    client_id VARCHAR(255) NOT NULL,
    scopes JSON NOT NULL,
    consent_given BOOLEAN NOT NULL,
    consent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    revoked BOOLEAN DEFAULT FALSE,
    revoked_at TIMESTAMP NULL,
    revocation_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_client (user_id, client_id),
    INDEX idx_user_id (user_id),
    INDEX idx_client_id (client_id),
    INDEX idx_consent_given (consent_given),
    INDEX idx_revoked (revoked),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- OAuth identity providers
CREATE TABLE IF NOT EXISTS oauth_identity_providers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_name VARCHAR(255) NOT NULL,
    provider_type VARCHAR(50) NOT NULL, -- oauth2, oidc, saml
    issuer VARCHAR(500) NULL,
    authorization_endpoint VARCHAR(500) NOT NULL,
    token_endpoint VARCHAR(500) NOT NULL,
    userinfo_endpoint VARCHAR(500) NULL,
    jwks_uri VARCHAR(500) NULL,
    end_session_endpoint VARCHAR(500) NULL,
    revocation_endpoint VARCHAR(500) NULL,
    registration_endpoint VARCHAR(500) NULL,
    device_authorization_endpoint VARCHAR(500) NULL,
    client_id VARCHAR(255) NOT NULL,
    client_secret VARCHAR(255) NOT NULL,
    scopes JSON NOT NULL,
    response_types JSON NOT NULL,
    grant_types JSON NOT NULL,
    token_endpoint_auth_method VARCHAR(50) DEFAULT 'client_secret_basic',
    id_token_signed_response_alg VARCHAR(10) DEFAULT 'RS256',
    userinfo_signed_response_alg VARCHAR(10) DEFAULT 'RS256',
    request_object_signing_alg VARCHAR(10) DEFAULT 'RS256',
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 100,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_provider_name (provider_name),
    INDEX idx_provider_type (provider_type),
    INDEX idx_is_active (is_active),
    INDEX idx_priority (priority),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- OAuth login attempts
CREATE TABLE IF NOT EXISTS oauth_login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    email VARCHAR(255) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    provider_name VARCHAR(255) NULL,
    attempt_result VARCHAR(50) NOT NULL, -- success, failed, blocked
    failure_reason VARCHAR(255) NULL,
    session_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_email (email),
    INDEX idx_ip_address (ip_address),
    INDEX idx_attempt_result (attempt_result),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- OAuth backchannel logout tokens
CREATE TABLE IF NOT EXISTS oauth_backchannel_logout (
    id INT PRIMARY KEY AUTO_INCREMENT,
    logout_token TEXT NOT NULL,
    user_id INT NOT NULL,
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_processed (processed),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- OAuth device flow codes (for device authorization)
CREATE TABLE IF NOT EXISTS oauth_device_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_code VARCHAR(255) NOT NULL UNIQUE,
    user_code VARCHAR(10) NOT NULL UNIQUE,
    client_id VARCHAR(255) NOT NULL,
    scopes JSON NULL,
    expires_at TIMESTAMP NOT NULL,
    interval_seconds INT DEFAULT 5,
    approved BOOLEAN DEFAULT FALSE,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_device_code (device_code),
    INDEX idx_user_code (user_code),
    INDEX idx_client_id (client_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_approved (approved),
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default government identity providers
INSERT IGNORE INTO oauth_identity_providers (
    provider_name, provider_type, authorization_endpoint, token_endpoint,
    scopes, response_types, grant_types, client_id, client_secret
) VALUES
('Government Login.gov', 'oidc', 'https://secure.login.gov/openid_connect/authorize', 'https://secure.login.gov/api/openid_connect/token',
 '["openid", "profile", "email", "address", "phone", "gov_identity"]',
 '["code", "code id_token"]',
 '["authorization_code", "refresh_token"]',
 'gov-platform-client-id', 'gov-platform-client-secret'),

('State Identity Provider', 'oauth2', 'https://idp.state.gov/oauth/authorize', 'https://idp.state.gov/oauth/token',
 '["profile", "email", "gov_credentials", "gov_services"]',
 '["code"]',
 '["authorization_code", "client_credentials"]',
 'state-gov-client-id', 'state-gov-client-secret'),

('Federal Identity Hub', 'oidc', 'https://idhub.federal.gov/oauth2/authorize', 'https://idhub.federal.gov/oauth2/token',
 '["openid", "profile", "email", "gov_identity", "gov_credentials", "gov_services"]',
 '["code", "code id_token", "code token id_token"]',
 '["authorization_code", "refresh_token", "client_credentials"]',
 'federal-hub-client-id', 'federal-hub-client-secret');

-- Create views for OAuth analytics
CREATE OR REPLACE VIEW oauth_login_summary AS
SELECT
    DATE(created_at) as login_date,
    attempt_result,
    provider_name,
    COUNT(*) as attempt_count,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(DISTINCT ip_address) as unique_ips
FROM oauth_login_attempts
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(created_at), attempt_result, provider_name
ORDER BY login_date DESC, attempt_count DESC;

CREATE OR REPLACE VIEW oauth_token_usage AS
SELECT
    DATE(created_at) as date,
    COUNT(*) as tokens_issued,
    COUNT(CASE WHEN refresh_token IS NOT NULL THEN 1 END) as refresh_tokens,
    COUNT(CASE WHEN id_token IS NOT NULL THEN 1 END) as id_tokens,
    AVG(TIMESTAMPDIFF(SECOND, issued_at, expires_at)) as avg_token_lifetime
FROM oauth_tokens
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;

CREATE OR REPLACE VIEW oauth_consent_summary AS
SELECT
    client_id,
    COUNT(*) as total_consents,
    SUM(CASE WHEN consent_given = 1 THEN 1 ELSE 0 END) as approved_consents,
    SUM(CASE WHEN revoked = 1 THEN 1 ELSE 0 END) as revoked_consents,
    AVG(CASE WHEN consent_given = 1 THEN TIMESTAMPDIFF(DAY, consent_date, expires_at) ELSE NULL END) as avg_consent_days
FROM oauth_consents
GROUP BY client_id
ORDER BY total_consents DESC;

CREATE OR REPLACE VIEW oauth_security_events AS
SELECT
    DATE(created_at) as event_date,
    'login_attempt' as event_type,
    attempt_result as status,
    COUNT(*) as event_count,
    COUNT(DISTINCT ip_address) as unique_ips,
    GROUP_CONCAT(DISTINCT failure_reason) as details
FROM oauth_login_attempts
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND attempt_result IN ('failed', 'blocked')
GROUP BY DATE(created_at), attempt_result

UNION ALL

SELECT
    DATE(created_at) as event_date,
    'token_revocation' as event_type,
    'revoked' as status,
    COUNT(*) as event_count,
    0 as unique_ips,
    GROUP_CONCAT(DISTINCT revocation_reason) as details
FROM oauth_tokens
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND revoked = 1
GROUP BY DATE(created_at)

ORDER BY event_date DESC, event_count DESC;
