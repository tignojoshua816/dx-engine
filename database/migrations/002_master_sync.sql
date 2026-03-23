-- =============================================================================
-- DX-Engine — Master Synchronisation Migration 002
-- =============================================================================
-- PURPOSE
--   This script is the single authoritative source of truth for the
--   dx_engine database schema.  It is 100% aligned with every PHP Model
--   fieldMap() definition in src/App/Models/*.php.
--
-- HOW TO RUN (choose one):
--
--   A. phpMyAdmin
--      1. Open http://localhost/phpmyadmin
--      2. Click "dx_engine" in the left sidebar.
--         (If the database does not yet exist, run the CREATE DATABASE line
--          at the bottom of this file first, then select it.)
--      3. Click the "SQL" tab → paste this file → click "Go".
--
--   B. MySQL / MariaDB CLI (XAMPP Shell)
--      cd C:\xampp\mysql\bin
--      mysql.exe -u root dx_engine < C:\xampp\htdocs\dx-engine\database\migrations\002_master_sync.sql
--
--   C. PHP CLI (from the dx-engine/ directory)
--      php -r "new PDO('mysql:host=localhost;dbname=dx_engine','root','')->exec(file_get_contents('database/migrations/002_master_sync.sql'));"
--
-- SAFE TO RE-RUN
--   Every DDL statement uses  CREATE TABLE IF NOT EXISTS.
--   Every seed DML statement uses  INSERT IGNORE.
--   Running this script more than once will not produce errors or lose data.
--
-- EXECUTION ORDER (critical — FK dependency chain)
--   1. departments       (no dependencies)
--   2. patients          (no dependencies)
--   3. admissions        (FK → departments, patients)
--   4. insurance_details (FK → admissions)
--
-- COLUMN ↔ MODEL ALIGNMENT TABLE
-- ┌──────────────────────────────────┬────────────────────────────┬────────────────────────┐
-- │ Table . Column                   │ PHP Model (namespace)      │ fieldMap key           │
-- ├──────────────────────────────────┼────────────────────────────┼────────────────────────┤
-- │ departments . id                 │ DepartmentModel            │ (PK — readonly)        │
-- │ departments . code               │ DepartmentModel            │ code                   │
-- │ departments . name               │ DepartmentModel            │ name                   │
-- │ departments . is_active          │ DepartmentModel            │ is_active              │
-- ├──────────────────────────────────┼────────────────────────────┼────────────────────────┤
-- │ patients . id                    │ PatientModel               │ (PK — readonly)        │
-- │ patients . first_name            │ PatientModel               │ first_name             │
-- │ patients . last_name             │ PatientModel               │ last_name              │
-- │ patients . date_of_birth         │ PatientModel               │ date_of_birth          │
-- │ patients . gender                │ PatientModel               │ gender                 │
-- │ patients . contact_phone         │ PatientModel               │ contact_phone          │
-- │ patients . contact_email         │ PatientModel               │ contact_email          │
-- │ patients . address               │ PatientModel               │ address                │
-- │ patients . created_at            │ PatientModel               │ created_at (readonly)  │
-- │ patients . updated_at            │ PatientModel               │ updated_at (readonly)  │
-- ├──────────────────────────────────┼────────────────────────────┼────────────────────────┤
-- │ admissions . id                  │ AdmissionModel             │ (PK — readonly)        │
-- │ admissions . patient_id          │ AdmissionModel             │ patient_id             │
-- │ admissions . department_id       │ AdmissionModel             │ department_id          │
-- │ admissions . triage_level        │ AdmissionModel             │ triage_level           │
-- │ admissions . chief_complaint     │ AdmissionModel             │ chief_complaint        │
-- │ admissions . attending_physician │ AdmissionModel             │ attending_physician    │
-- │ admissions . status              │ AdmissionModel             │ status                 │
-- │ admissions . notes               │ AdmissionModel             │ notes                  │
-- │ admissions . admission_date      │ AdmissionModel             │ admission_date (rdonly)│
-- │ admissions . created_at          │ AdmissionModel             │ created_at (readonly)  │
-- │ admissions . updated_at          │ AdmissionModel             │ updated_at (readonly)  │
-- ├──────────────────────────────────┼────────────────────────────┼────────────────────────┤
-- │ insurance_details . id           │ InsuranceModel             │ (PK — readonly)        │
-- │ insurance_details . admission_id │ InsuranceModel             │ admission_id           │
-- │ insurance_details . provider_name│ InsuranceModel             │ provider_name          │
-- │ insurance_details . policy_number│ InsuranceModel             │ policy_number          │
-- │ insurance_details . group_number │ InsuranceModel             │ group_number           │
-- │ insurance_details . holder_name  │ InsuranceModel             │ holder_name            │
-- │ insurance_details . holder_dob   │ InsuranceModel             │ holder_dob             │
-- │ insurance_details . coverage_type│ InsuranceModel             │ coverage_type          │
-- │ insurance_details . expiry_date  │ InsuranceModel             │ expiry_date            │
-- │ insurance_details . created_at   │ InsuranceModel             │ created_at (readonly)  │
-- └──────────────────────────────────┴────────────────────────────┴────────────────────────┘
-- =============================================================================

-- Create the schema if it does not exist (idempotent — safe to leave in).
-- Skip this line if you already selected the database in phpMyAdmin.
CREATE DATABASE IF NOT EXISTS `dx_engine`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `dx_engine`;

-- Temporarily disable FK checks so tables can be created in any order
-- and so this script can be re-run on a populated database without
-- constraint violations.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- TABLE 1 of 4: departments
-- Referenced by admissions.department_id — must exist before admissions.
-- Maps to: DXEngine\App\Models\DepartmentModel (src/App/Models/DepartmentModel.php)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `departments` (
    `id`        INT UNSIGNED  NOT NULL AUTO_INCREMENT
                              COMMENT 'Primary key',
    `code`      VARCHAR(20)   NOT NULL
                              COMMENT 'DepartmentModel: code — short identifier e.g. ED, ICU',
    `name`      VARCHAR(100)  NOT NULL
                              COMMENT 'DepartmentModel: name — label shown in the department SELECT dropdown',
    `is_active` TINYINT(1)    NOT NULL DEFAULT 1
                              COMMENT 'DepartmentModel: is_active — 1=shown in dropdown, 0=hidden',
    PRIMARY KEY (`id`),
    UNIQUE KEY  `uq_dept_code` (`code`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Lookup table for hospital departments. Consumed by AdmissionDX::preProcess().';

-- Seed 10 reference departments.
-- INSERT IGNORE skips rows whose `code` already exists (UNIQUE KEY constraint),
-- so this block is safe to re-run at any time without creating duplicates.
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


-- =============================================================================
-- TABLE 2 of 4: patients
-- Maps to: DXEngine\App\Models\PatientModel (src/App/Models/PatientModel.php)
--
-- gender ENUM values MUST exactly match:
--   1. AdmissionDX::buildStepPatientInfo() options array
--   2. PatientModel fieldMap regex rule: /^(male|female|other|prefer_not)$/
-- =============================================================================
CREATE TABLE IF NOT EXISTS `patients` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT
                                  COMMENT 'Primary key',
    `first_name`    VARCHAR(80)   NOT NULL
                                  COMMENT 'PatientModel: first_name — required, min 2, max 80',
    `last_name`     VARCHAR(80)   NOT NULL
                                  COMMENT 'PatientModel: last_name — required, min 2, max 80',
    `date_of_birth` DATE          NOT NULL
                                  COMMENT 'PatientModel: date_of_birth — required, format YYYY-MM-DD',
    `gender`        ENUM(
                        'male',
                        'female',
                        'other',
                        'prefer_not'
                    )             NOT NULL
                                  COMMENT 'PatientModel: gender — must match AdmissionDX SELECT options exactly',
    `contact_phone` VARCHAR(20)   NOT NULL
                                  COMMENT 'PatientModel: contact_phone — required, type=phone',
    `contact_email` VARCHAR(120)  NULL     DEFAULT NULL
                                  COMMENT 'PatientModel: contact_email — optional, type=email, max 120',
    `address`       TEXT          NULL
                                  COMMENT 'PatientModel: address — optional, type=text, max 500',
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  COMMENT 'PatientModel: created_at — readonly, auto-set on INSERT',
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                           ON UPDATE CURRENT_TIMESTAMP
                                  COMMENT 'PatientModel: updated_at — readonly, auto-refreshed on UPDATE',
    PRIMARY KEY (`id`),
    KEY `idx_patient_name` (`last_name`, `first_name`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Patient demographic records written by AdmissionDX Step 1.';


-- =============================================================================
-- TABLE 3 of 4: admissions
-- Maps to: DXEngine\App\Models\AdmissionModel (src/App/Models/AdmissionModel.php)
--
-- triage_level: TINYINT UNSIGNED. Valid range 1–5 enforced at PHP level by
--   AdmissionModel validation_rules: ['regex:/^[1-5]$/'].
--
-- status ENUM: 'pending' | 'admitted' | 'discharged' | 'transferred'
--   Default 'pending' matches AdmissionModel fieldMap default.
--
-- has_insurance: NOT a column here. It is a UI-only toggle field defined
--   in AdmissionDX::buildStepClinicalData(). It is stripped in
--   AdmissionDX::saveClinicalData() before the insert/update call.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `admissions` (
    `id`                   INT UNSIGNED     NOT NULL AUTO_INCREMENT
                                            COMMENT 'Primary key',
    `patient_id`           INT UNSIGNED     NOT NULL
                                            COMMENT 'AdmissionModel: patient_id — FK → patients.id',
    `department_id`        INT UNSIGNED     NOT NULL
                                            COMMENT 'AdmissionModel: department_id — FK → departments.id',
    `triage_level`         TINYINT UNSIGNED NOT NULL
                                            COMMENT 'AdmissionModel: triage_level — 1=Immediate 2=Emergent 3=Urgent 4=Less-Urgent 5=Non-Urgent',
    `chief_complaint`      VARCHAR(255)     NOT NULL
                                            COMMENT 'AdmissionModel: chief_complaint — required, min 3, max 255',
    `attending_physician`  VARCHAR(120)     NULL     DEFAULT NULL
                                            COMMENT 'AdmissionModel: attending_physician — optional, max 120',
    `status`               ENUM(
                               'pending',
                               'admitted',
                               'discharged',
                               'transferred'
                           )               NOT NULL DEFAULT 'pending'
                                            COMMENT 'AdmissionModel: status — default pending, matches fieldMap regex',
    `notes`                TEXT             NULL
                                            COMMENT 'AdmissionModel: notes — optional, max 2000',
    `admission_date`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            COMMENT 'AdmissionModel: admission_date — readonly, auto-set on INSERT',
    `created_at`           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            COMMENT 'AdmissionModel: created_at — readonly',
    `updated_at`           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                     ON UPDATE CURRENT_TIMESTAMP
                                            COMMENT 'AdmissionModel: updated_at — readonly',
    PRIMARY KEY (`id`),
    -- Foreign key constraints enforce referential integrity at the DB layer.
    -- ON DELETE RESTRICT prevents orphaned admissions when a patient/dept is deleted.
    -- ON UPDATE CASCADE propagates primary-key renames (rare but handled).
    CONSTRAINT `fk_adm_patient`
        FOREIGN KEY (`patient_id`)
        REFERENCES `patients` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_adm_department`
        FOREIGN KEY (`department_id`)
        REFERENCES `departments` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    KEY `idx_adm_patient`    (`patient_id`),
    KEY `idx_adm_department` (`department_id`),
    KEY `idx_adm_status`     (`status`),
    KEY `idx_adm_triage`     (`triage_level`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Patient admission records written by AdmissionDX Step 2.';


-- =============================================================================
-- TABLE 4 of 4: insurance_details
-- Maps to: DXEngine\App\Models\InsuranceModel (src/App/Models/InsuranceModel.php)
--
-- Written only when the patient selects "Has Insurance" (has_insurance = '1')
-- in AdmissionDX Step 2.  The NOT NULL constraints here are guarded at the
-- PHP level: InsuranceModel::validate() is called before insert(), rejecting
-- blank values with human-readable error messages before any SQL executes.
--
-- ON DELETE CASCADE: deleting an admission automatically removes its insurance
-- record — insurance without an admission is meaningless.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `insurance_details` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT
                                  COMMENT 'Primary key',
    `admission_id`  INT UNSIGNED  NOT NULL
                                  COMMENT 'InsuranceModel: admission_id — FK → admissions.id',
    `provider_name` VARCHAR(120)  NOT NULL
                                  COMMENT 'InsuranceModel: provider_name — required, min 2, max 120',
    `policy_number` VARCHAR(60)   NOT NULL
                                  COMMENT 'InsuranceModel: policy_number — required, min 2, max 60',
    `group_number`  VARCHAR(60)   NULL     DEFAULT NULL
                                  COMMENT 'InsuranceModel: group_number — optional, max 60',
    `holder_name`   VARCHAR(120)  NOT NULL
                                  COMMENT 'InsuranceModel: holder_name — required, min 2, max 120',
    `holder_dob`    DATE          NOT NULL
                                  COMMENT 'InsuranceModel: holder_dob — required, format YYYY-MM-DD',
    `coverage_type` VARCHAR(60)   NULL     DEFAULT NULL
                                  COMMENT 'InsuranceModel: coverage_type — optional, max 60',
    `expiry_date`   DATE          NULL     DEFAULT NULL
                                  COMMENT 'InsuranceModel: expiry_date — optional, format YYYY-MM-DD',
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  COMMENT 'InsuranceModel: created_at — readonly, auto-set on INSERT',
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ins_admission`
        FOREIGN KEY (`admission_id`)
        REFERENCES `admissions` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    KEY `idx_ins_admission` (`admission_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Insurance record for an admission. One row per admission (1:1 when present).';


-- Re-enable FK checks.
SET FOREIGN_KEY_CHECKS = 1;


-- =============================================================================
-- POST-RUN VERIFICATION QUERIES
-- =============================================================================
-- Run these in the phpMyAdmin "SQL" tab after executing this migration to
-- confirm that all tables exist and the schema matches the PHP field maps.
--
-- 1. Confirm all four tables exist in dx_engine:
--    SELECT table_name, table_rows, create_time
--    FROM information_schema.tables
--    WHERE table_schema = 'dx_engine'
--    ORDER BY table_name;
--    Expected rows: admissions, departments, insurance_details, patients
--
-- 2. Confirm 10 seed departments were inserted:
--    SELECT id, code, name, is_active FROM departments ORDER BY id;
--
-- 3. Compare patients columns to PatientModel::fieldMap():
--    DESCRIBE patients;
--
-- 4. Compare departments columns to DepartmentModel::fieldMap():
--    DESCRIBE departments;
--
-- 5. Compare admissions columns to AdmissionModel::fieldMap():
--    DESCRIBE admissions;
--
-- 6. Compare insurance_details columns to InsuranceModel::fieldMap():
--    DESCRIBE insurance_details;
--
-- 7. Verify foreign-key constraints are in place:
--    SELECT constraint_name, table_name, column_name,
--           referenced_table_name, referenced_column_name
--    FROM information_schema.key_column_usage
--    WHERE table_schema = 'dx_engine'
--      AND referenced_table_name IS NOT NULL
--    ORDER BY table_name, constraint_name;
--    Expected: fk_adm_department, fk_adm_patient, fk_ins_admission
--
-- 8. End-to-end smoke test — hit the API from your browser:
--    http://localhost/dx-engine/public/api/dx.php?dx=admission
--    http://localhost/dx-engine/public/api/dx.php?dx=admission_case
--    Both should return a JSON Metadata Bridge payload with status "ok".
-- =============================================================================
