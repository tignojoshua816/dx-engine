-- =============================================================================
-- DX-Engine — Canonical Schema (v1.0)
-- =============================================================================
-- This is the single source of truth for the dx_engine database.
-- It replaces 001_create_tables.sql and 002_master_sync.sql.
--
-- HOW TO RUN (choose one):
--   A. phpMyAdmin
--      1. Open http://localhost/phpmyadmin
--      2. Select the "dx_engine" database (create it first if it does not exist)
--      3. Click the "SQL" tab, paste this file, click "Go"
--
--   B. MySQL CLI
--      mysql -u root dx_engine < database/migrations/003_schema.sql
--
-- SAFE TO RE-RUN
--   Every statement uses CREATE TABLE IF NOT EXISTS and INSERT IGNORE guards.
--   Running this script twice will not produce errors or lose data.
--
-- MODEL ↔ COLUMN ALIGNMENT TABLE
-- ┌───────────────────────────────┬───────────────────────────────┬──────────────────┐
-- │ Table . Column                │ PHP Model (DXEngine\App\...)  │ fieldMap key     │
-- ├───────────────────────────────┼───────────────────────────────┼──────────────────┤
-- │ patients . id                 │ Models\PatientModel           │ (PK – readonly)  │
-- │ patients . first_name         │ Models\PatientModel           │ first_name       │
-- │ patients . last_name          │ Models\PatientModel           │ last_name        │
-- │ patients . date_of_birth      │ Models\PatientModel           │ date_of_birth    │
-- │ patients . gender             │ Models\PatientModel           │ gender           │
-- │ patients . contact_phone      │ Models\PatientModel           │ contact_phone    │
-- │ patients . contact_email      │ Models\PatientModel           │ contact_email    │
-- │ patients . address            │ Models\PatientModel           │ address          │
-- │ patients . created_at         │ Models\PatientModel           │ (readonly)       │
-- │ patients . updated_at         │ Models\PatientModel           │ (readonly)       │
-- ├───────────────────────────────┼───────────────────────────────┼──────────────────┤
-- │ departments . id              │ Models\DepartmentModel        │ (PK – readonly)  │
-- │ departments . code            │ Models\DepartmentModel        │ code             │
-- │ departments . name            │ Models\DepartmentModel        │ name             │
-- │ departments . is_active       │ Models\DepartmentModel        │ is_active        │
-- ├───────────────────────────────┼───────────────────────────────┼──────────────────┤
-- │ admissions . id               │ Models\AdmissionModel         │ (PK – readonly)  │
-- │ admissions . patient_id       │ Models\AdmissionModel         │ patient_id       │
-- │ admissions . department_id    │ Models\AdmissionModel         │ department_id    │
-- │ admissions . triage_level     │ Models\AdmissionModel         │ triage_level     │
-- │ admissions . chief_complaint  │ Models\AdmissionModel         │ chief_complaint  │
-- │ admissions . attending_physician│ Models\AdmissionModel       │ attending_physician│
-- │ admissions . status           │ Models\AdmissionModel         │ status           │
-- │ admissions . notes            │ Models\AdmissionModel         │ notes            │
-- │ admissions . admission_date   │ Models\AdmissionModel         │ (readonly)       │
-- │ admissions . created_at       │ Models\AdmissionModel         │ (readonly)       │
-- │ admissions . updated_at       │ Models\AdmissionModel         │ (readonly)       │
-- ├───────────────────────────────┼───────────────────────────────┼──────────────────┤
-- │ insurance_details . id        │ Models\InsuranceModel         │ (PK – readonly)  │
-- │ insurance_details . admission_id│ Models\InsuranceModel       │ admission_id     │
-- │ insurance_details . provider_name│ Models\InsuranceModel      │ provider_name    │
-- │ insurance_details . policy_number│ Models\InsuranceModel      │ policy_number    │
-- │ insurance_details . group_number │ Models\InsuranceModel      │ group_number     │
-- │ insurance_details . holder_name  │ Models\InsuranceModel      │ holder_name      │
-- │ insurance_details . holder_dob   │ Models\InsuranceModel      │ holder_dob       │
-- │ insurance_details . coverage_type│ Models\InsuranceModel      │ coverage_type    │
-- │ insurance_details . expiry_date  │ Models\InsuranceModel      │ expiry_date      │
-- │ insurance_details . created_at   │ Models\InsuranceModel      │ (readonly)       │
-- └───────────────────────────────┴───────────────────────────────┴──────────────────┘
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────────────────────
-- TABLE: departments
-- Must be created before admissions because admissions holds a FK to it.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `departments` (
    `id`        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `code`      VARCHAR(20)   NOT NULL COMMENT 'Short code, e.g. ED, ICU, ORTHO',
    `name`      VARCHAR(100)  NOT NULL COMMENT 'Human-readable name shown in the UI',
    `is_active` TINYINT(1)    NOT NULL DEFAULT 1 COMMENT '1 = shown in dropdown, 0 = hidden',
    PRIMARY KEY (`id`),
    UNIQUE KEY  `uq_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed 10 reference departments.
-- INSERT IGNORE is idempotent — safe to re-run without duplicating rows.
INSERT IGNORE INTO `departments` (`code`, `name`, `is_active`) VALUES
    ('ED',    'Emergency Department',      1),
    ('ICU',   'Intensive Care Unit',       1),
    ('ORTHO', 'Orthopaedics',              1),
    ('CARD',  'Cardiology',                1),
    ('NEURO', 'Neurology',                 1),
    ('PEDS',  'Paediatrics',               1),
    ('OB',    'Obstetrics & Gynaecology',  1),
    ('SURG',  'General Surgery',           1),
    ('PSYCH', 'Psychiatry',                1),
    ('ONCO',  'Oncology',                  1);

-- ─────────────────────────────────────────────────────────────────────────────
-- TABLE: patients
-- Maps to DXEngine\App\Models\PatientModel
-- ─────────────────────────────────────────────────────────────────────────────
-- The gender ENUM values must EXACTLY match the options array in
-- AdmissionDX::buildStepPatientInfo() and PatientModel's regex rule:
--   'male' | 'female' | 'other' | 'prefer_not'
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `patients` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `first_name`    VARCHAR(80)   NOT NULL COMMENT 'PatientModel: first_name',
    `last_name`     VARCHAR(80)   NOT NULL COMMENT 'PatientModel: last_name',
    `date_of_birth` DATE          NOT NULL COMMENT 'PatientModel: date_of_birth',
    `gender`        ENUM(
                        'male',
                        'female',
                        'other',
                        'prefer_not'
                    )             NOT NULL COMMENT 'PatientModel: gender',
    `contact_phone` VARCHAR(20)   NOT NULL COMMENT 'PatientModel: contact_phone',
    `contact_email` VARCHAR(120)  NULL     COMMENT 'PatientModel: contact_email',
    `address`       TEXT          NULL     COMMENT 'PatientModel: address',
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                           ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_name` (`last_name`, `first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- TABLE: admissions
-- Maps to DXEngine\App\Models\AdmissionModel
-- ─────────────────────────────────────────────────────────────────────────────
-- triage_level:  TINYINT values 1–5 enforced at PHP level by AdmissionModel
--                 validation_rules (regex:/^[1-5]$/).  The DB stores any TINYINT.
-- status ENUM:   matches AdmissionModel fieldMap regex rule exactly.
-- has_insurance: NOT a column — UI-only field stripped in AdmissionDX.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admissions` (
    `id`                   INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `patient_id`           INT UNSIGNED     NOT NULL COMMENT 'FK → patients.id',
    `department_id`        INT UNSIGNED     NOT NULL COMMENT 'FK → departments.id',
    `triage_level`         TINYINT UNSIGNED NOT NULL COMMENT '1=Immediate … 5=Non-Urgent',
    `chief_complaint`      VARCHAR(255)     NOT NULL COMMENT 'AdmissionModel: chief_complaint',
    `attending_physician`  VARCHAR(120)     NULL     COMMENT 'AdmissionModel: attending_physician',
    `status`               ENUM(
                               'pending',
                               'admitted',
                               'discharged',
                               'transferred'
                           )               NOT NULL DEFAULT 'pending' COMMENT 'AdmissionModel: status',
    `notes`                TEXT             NULL     COMMENT 'AdmissionModel: notes',
    `admission_date`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Auto-set on INSERT',
    `created_at`           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                     ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_adm_patient`
        FOREIGN KEY (`patient_id`)    REFERENCES `patients`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_adm_department`
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    KEY `idx_patient`    (`patient_id`),
    KEY `idx_department` (`department_id`),
    KEY `idx_status`     (`status`),
    KEY `idx_triage`     (`triage_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- TABLE: insurance_details
-- Maps to DXEngine\App\Models\InsuranceModel
-- ─────────────────────────────────────────────────────────────────────────────
-- Written only when the patient selects "Has Insurance" in Step 2.
-- The NOT NULL columns here are guarded at the PHP level by InsuranceModel's
-- required:true fieldMap flags and the validate() call in AdmissionDX.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `insurance_details` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `admission_id`  INT UNSIGNED  NOT NULL COMMENT 'FK → admissions.id',
    `provider_name` VARCHAR(120)  NOT NULL COMMENT 'InsuranceModel: provider_name',
    `policy_number` VARCHAR(60)   NOT NULL COMMENT 'InsuranceModel: policy_number',
    `group_number`  VARCHAR(60)   NULL     COMMENT 'InsuranceModel: group_number',
    `holder_name`   VARCHAR(120)  NOT NULL COMMENT 'InsuranceModel: holder_name',
    `holder_dob`    DATE          NOT NULL COMMENT 'InsuranceModel: holder_dob',
    `coverage_type` VARCHAR(60)   NULL     COMMENT 'InsuranceModel: coverage_type',
    `expiry_date`   DATE          NULL     COMMENT 'InsuranceModel: expiry_date',
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ins_admission`
        FOREIGN KEY (`admission_id`) REFERENCES `admissions`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    KEY `idx_ins_admission` (`admission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- VERIFICATION QUERIES
-- Run these in phpMyAdmin after executing this migration.
-- =============================================================================

-- 1. All four tables exist:
-- SELECT table_name FROM information_schema.tables
-- WHERE table_schema = DATABASE() ORDER BY table_name;
-- Expected: admissions, departments, insurance_details, patients

-- 2. Ten seed departments:
-- SELECT id, code, name FROM departments ORDER BY id;

-- 3. Schema snapshots (compare to PHP fieldMaps):
-- DESCRIBE patients;
-- DESCRIBE departments;
-- DESCRIBE admissions;
-- DESCRIBE insurance_details;
