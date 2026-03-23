-- =============================================================================
-- DX-Engine — Database Migration 001
-- Creates all tables required for the Admission Case Type sample.
-- Run once against your MySQL/MariaDB database.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────────────────────
-- departments  (lookup table)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS departments (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code      VARCHAR(20)  NOT NULL UNIQUE  COMMENT 'Short code e.g. ED, ICU, ORTHO',
    name      VARCHAR(100) NOT NULL         COMMENT 'Display name',
    is_active TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO departments (code, name) VALUES
    ('ED',    'Emergency Department'),
    ('ICU',   'Intensive Care Unit'),
    ('ORTHO', 'Orthopaedics'),
    ('CARD',  'Cardiology'),
    ('NEURO', 'Neurology'),
    ('PEDS',  'Paediatrics'),
    ('OB',    'Obstetrics & Gynaecology'),
    ('SURG',  'General Surgery'),
    ('PSYCH', 'Psychiatry'),
    ('ONCO',  'Oncology');

-- ─────────────────────────────────────────────────────────────────────────────
-- patients
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS patients (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(80)  NOT NULL,
    last_name       VARCHAR(80)  NOT NULL,
    date_of_birth   DATE         NOT NULL,
    gender          ENUM('male','female','other','prefer_not') NOT NULL,
    contact_phone   VARCHAR(20)  NOT NULL,
    contact_email   VARCHAR(120) NULL,
    address         TEXT         NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (last_name, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- admissions
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admissions (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id           INT UNSIGNED  NOT NULL,
    department_id        INT UNSIGNED  NOT NULL,
    triage_level         TINYINT UNSIGNED NOT NULL  COMMENT '1=Immediate 2=Emergent 3=Urgent 4=Less-Urgent 5=Non-Urgent',
    chief_complaint      VARCHAR(255)  NOT NULL,
    attending_physician  VARCHAR(120)  NULL,
    status               ENUM('pending','admitted','discharged','transferred') NOT NULL DEFAULT 'pending',
    notes                TEXT          NULL,
    admission_date       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_adm_patient    FOREIGN KEY (patient_id)    REFERENCES patients(id)    ON DELETE RESTRICT,
    CONSTRAINT fk_adm_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
    INDEX idx_patient    (patient_id),
    INDEX idx_department (department_id),
    INDEX idx_status     (status),
    INDEX idx_triage     (triage_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- insurance_details
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS insurance_details (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admission_id    INT UNSIGNED  NOT NULL,
    provider_name   VARCHAR(120)  NOT NULL,
    policy_number   VARCHAR(60)   NOT NULL,
    group_number    VARCHAR(60)   NULL,
    holder_name     VARCHAR(120)  NOT NULL,
    holder_dob      DATE          NOT NULL,
    coverage_type   VARCHAR(60)   NULL,
    expiry_date     DATE          NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ins_admission FOREIGN KEY (admission_id) REFERENCES admissions(id) ON DELETE CASCADE,
    INDEX idx_admission (admission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
