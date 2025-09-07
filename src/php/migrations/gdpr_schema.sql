-- GDPR Compliance Database Schema
-- Tables for managing GDPR compliance features

-- Data processing records (Article 30)
CREATE TABLE IF NOT EXISTS gdpr_data_processing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    data_categories TEXT NOT NULL,
    processing_type VARCHAR(100) NOT NULL,
    legal_basis VARCHAR(100) NOT NULL DEFAULT 'consent',
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_processing (user_id, created_at),
    INDEX idx_purpose (purpose),
    INDEX idx_processing_type (processing_type)
);

-- Consent management
CREATE TABLE IF NOT EXISTS gdpr_consents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    consent_type VARCHAR(100) NOT NULL,
    granted BOOLEAN NOT NULL DEFAULT FALSE,
    consent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date TIMESTAMP NULL,
    consent_text TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    UNIQUE KEY unique_user_consent (user_id, consent_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_consent (user_id, consent_type),
    INDEX idx_expiry (expiry_date),
    INDEX idx_granted (granted)
);

-- Data subject access requests (DSAR)
CREATE TABLE IF NOT EXISTS gdpr_access_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    request_type VARCHAR(50) NOT NULL DEFAULT 'access',
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    response_data JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_request (user_id, status),
    INDEX idx_status (status),
    INDEX idx_requested_at (requested_at)
);

-- Data portability exports
CREATE TABLE IF NOT EXISTS gdpr_data_exports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    export_data JSON,
    export_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) NOT NULL DEFAULT 'completed',
    download_count INT DEFAULT 0,
    last_download TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_export (user_id, export_date),
    INDEX idx_status (status),
    INDEX idx_expires (expires_at)
);

-- Data erasure requests (Right to be Forgotten)
CREATE TABLE IF NOT EXISTS gdpr_erasure_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    reason TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    anonymized_data JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_erasure (user_id, status),
    INDEX idx_status (status),
    INDEX idx_requested_at (requested_at)
);

-- Data rectification requests
CREATE TABLE IF NOT EXISTS gdpr_rectification_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    requested_changes JSON,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    previous_data JSON,
    new_data JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_rectification (user_id, status),
    INDEX idx_status (status),
    INDEX idx_requested_at (requested_at)
);

-- Data processing restrictions
CREATE TABLE IF NOT EXISTS gdpr_processing_restrictions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    restriction_type VARCHAR(100) NOT NULL,
    reason TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lifted_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_restriction (user_id, status),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Privacy policy versions
CREATE TABLE IF NOT EXISTS gdpr_privacy_policies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    version VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    effective_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT FALSE,
    INDEX idx_version (version),
    INDEX idx_active (is_active),
    INDEX idx_effective (effective_date)
);

-- Cookie consent records
CREATE TABLE IF NOT EXISTS gdpr_cookie_consents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    session_id VARCHAR(255),
    consent_categories JSON,
    consent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    INDEX idx_user_cookie (user_id),
    INDEX idx_session_cookie (session_id),
    INDEX idx_expiry_cookie (expiry_date)
);

-- Data breach notifications
CREATE TABLE IF NOT EXISTS gdpr_data_breaches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    breach_type VARCHAR(100) NOT NULL,
    affected_users INT NOT NULL,
    data_categories TEXT,
    description TEXT,
    detection_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notification_date TIMESTAMP NULL,
    resolution_date TIMESTAMP NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'detected',
    risk_level VARCHAR(20) NOT NULL DEFAULT 'medium',
    measures_taken TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_detection (detection_date),
    INDEX idx_risk (risk_level)
);

-- International data transfers
CREATE TABLE IF NOT EXISTS gdpr_data_transfers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_country VARCHAR(100) NOT NULL,
    recipient_entity VARCHAR(255) NOT NULL,
    data_categories TEXT,
    transfer_mechanism VARCHAR(100) NOT NULL,
    legal_basis TEXT,
    transfer_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    review_date TIMESTAMP NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_country (recipient_country),
    INDEX idx_status_transfer (status),
    INDEX idx_transfer_date (transfer_date)
);

-- Automated decision making records
CREATE TABLE IF NOT EXISTS gdpr_automated_decisions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    decision_type VARCHAR(100) NOT NULL,
    decision_logic TEXT,
    decision_factors JSON,
    decision_outcome VARCHAR(100) NOT NULL,
    decision_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    human_review BOOLEAN DEFAULT FALSE,
    review_outcome VARCHAR(100) NULL,
    review_date TIMESTAMP NULL,
    explanation TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_decision (user_id, decision_date),
    INDEX idx_type (decision_type),
    INDEX idx_outcome (decision_outcome)
);

-- Compliance audit log
CREATE TABLE IF NOT EXISTS gdpr_compliance_audit (
    id INT PRIMARY KEY AUTO_INCREMENT,
    audit_type VARCHAR(100) NOT NULL,
    audit_scope VARCHAR(255) NOT NULL,
    findings JSON,
    recommendations TEXT,
    auditor VARCHAR(100),
    audit_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    next_audit_date TIMESTAMP NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type_audit (audit_type),
    INDEX idx_date_audit (audit_date),
    INDEX idx_status_audit (status)
);

-- Add GDPR fields to existing users table
ALTER TABLE users
ADD COLUMN IF NOT EXISTS processing_restricted BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS consent_version VARCHAR(20) DEFAULT '1.0',
ADD COLUMN IF NOT EXISTS data_retention_expiry TIMESTAMP NULL,
ADD INDEX IF NOT EXISTS idx_processing_restricted (processing_restricted),
ADD INDEX IF NOT EXISTS idx_retention_expiry (data_retention_expiry);

-- Insert default privacy policy
INSERT IGNORE INTO gdpr_privacy_policies (version, title, content, is_active) VALUES (
    '1.0',
    'Privacy Policy - TPT Government Platform',
    'This privacy policy explains how we collect, use, and protect your personal data in compliance with GDPR regulations.',
    TRUE
);

-- Create views for GDPR reporting
CREATE OR REPLACE VIEW gdpr_user_data_summary AS
SELECT
    u.id,
    u.email,
    u.first_name,
    u.last_name,
    u.created_at as registration_date,
    u.processing_restricted,
    COUNT(DISTINCT dp.id) as processing_records,
    COUNT(DISTINCT c.id) as consent_records,
    COUNT(DISTINCT ar.id) as access_requests,
    COUNT(DISTINCT er.id) as erasure_requests,
    MAX(dp.created_at) as last_processing_date,
    MAX(c.consent_date) as last_consent_date
FROM users u
LEFT JOIN gdpr_data_processing dp ON u.id = dp.user_id
LEFT JOIN gdpr_consents c ON u.id = c.user_id
LEFT JOIN gdpr_access_requests ar ON u.id = ar.user_id
LEFT JOIN gdpr_erasure_requests er ON u.id = er.user_id
GROUP BY u.id, u.email, u.first_name, u.last_name, u.created_at, u.processing_restricted;

-- Create view for consent compliance
CREATE OR REPLACE VIEW gdpr_consent_compliance AS
SELECT
    c.consent_type,
    COUNT(*) as total_consents,
    SUM(CASE WHEN c.granted = 1 THEN 1 ELSE 0 END) as granted_consents,
    SUM(CASE WHEN c.expiry_date < NOW() THEN 1 ELSE 0 END) as expired_consents,
    AVG(CASE WHEN c.granted = 1 THEN DATEDIFF(c.expiry_date, c.consent_date) ELSE NULL END) as avg_consent_days
FROM gdpr_consents c
GROUP BY c.consent_type;

-- Create view for data processing compliance
CREATE OR REPLACE VIEW gdpr_processing_compliance AS
SELECT
    dp.purpose,
    dp.data_categories,
    dp.processing_type,
    dp.legal_basis,
    COUNT(*) as records_count,
    COUNT(DISTINCT dp.user_id) as unique_users,
    MIN(dp.created_at) as first_processing,
    MAX(dp.created_at) as last_processing
FROM gdpr_data_processing dp
GROUP BY dp.purpose, dp.data_categories, dp.processing_type, dp.legal_basis;
