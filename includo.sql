-- Includo DB schema (clean, installer-friendly)
-- WCAG 2.2 + AgID-ready statement storage
-- Author: Franco Aquini - Web Salad

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS audit_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    site_url VARCHAR(255) NOT NULL,
    start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    end_time DATETIME NULL,
    total_pages INT DEFAULT 0,
    total_issues INT DEFAULT 0,
    status ENUM('running', 'paused', 'completed', 'error') DEFAULT 'running',
    user_agent VARCHAR(255) DEFAULT 'Includo WCAG Auditor 2.2',
    max_pages_limit INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_site_url (site_url),
    INDEX idx_status (status),
    INDEX idx_start_time (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS page_audits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    title VARCHAR(255),
    audit_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    response_time FLOAT,
    status_code INT,
    content_length INT,
    total_issues INT DEFAULT 0,
    wcag_level_a_issues INT DEFAULT 0,
    wcag_level_aa_issues INT DEFAULT 0,
    wcag_level_aaa_issues INT DEFAULT 0,
    load_time FLOAT DEFAULT 0,
    redirects_count INT DEFAULT 0,
    final_url VARCHAR(500),
    meta_description TEXT,
    h1_count INT DEFAULT 0,
    img_count INT DEFAULT 0,
    link_count INT DEFAULT 0,
    form_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES audit_sessions(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_url (url(100)),
    INDEX idx_total_issues (total_issues),
    INDEX idx_status_code (status_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS accessibility_issues (
    id INT PRIMARY KEY AUTO_INCREMENT,
    page_audit_id INT NOT NULL,
    issue_type VARCHAR(100) NOT NULL,
    wcag_criterion VARCHAR(20) NOT NULL,
    wcag_level ENUM('A', 'AA', 'AAA') NOT NULL,
    wcag_version ENUM('2.0', '2.1', '2.2') DEFAULT '2.2',
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    confidence ENUM('low', 'medium', 'high') DEFAULT 'medium',
    element_selector VARCHAR(500),
    element_html TEXT,
    description TEXT NOT NULL,
    recommendation TEXT NOT NULL,
    help_url VARCHAR(500),
    line_number INT,
    column_number INT,
    xpath VARCHAR(1000),
    impact_score INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_audit_id) REFERENCES page_audits(id) ON DELETE CASCADE,
    INDEX idx_page_audit_id (page_audit_id),
    INDEX idx_wcag_criterion (wcag_criterion),
    INDEX idx_wcag_level (wcag_level),
    INDEX idx_severity (severity),
    INDEX idx_issue_type (issue_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS crawl_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    status ENUM('pending','completed','error') DEFAULT 'pending',
    discovered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    FOREIGN KEY (session_id) REFERENCES audit_sessions(id) ON DELETE CASCADE,
    INDEX idx_session (session_id),
    INDEX idx_status (status),
    INDEX idx_url (url(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS accessibility_statements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    lang ENUM('it','en') NOT NULL DEFAULT 'it',
    payload JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_session_lang (session_id, lang),
    FOREIGN KEY (session_id) REFERENCES audit_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
