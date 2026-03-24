-- =============================================================================
-- DX-Engine Workflow + RBAC Foundation (MySQL/MariaDB, idempotent)
-- =============================================================================
-- Purpose:
--   1) Align RBAC schema with current DX-Engine (MySQL/XAMPP compatible)
--   2) Add workflow/case/assignment/worklist tables for runtime routing
--   3) Keep schema integration-friendly for dashboard and portal reporting
--
-- Notes:
--   - Uses CREATE TABLE IF NOT EXISTS for safe re-runs
--   - Uses INSERT IGNORE for idempotent seeds
--   - Works with current admissions/patients models as separate domain data
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1) Identity + RBAC Core
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `dx_users` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(80)  NOT NULL,
    `email`         VARCHAR(190) NULL,
    `display_name`  VARCHAR(150) NULL,
    `password_hash` VARCHAR(255) NULL,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dx_users_username` (`username`),
    UNIQUE KEY `uq_dx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dx_groups` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_key`    VARCHAR(80)  NOT NULL,
    `group_name`   VARCHAR(150) NOT NULL,
    `description`  TEXT         NULL,
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dx_groups_key` (`group_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dx_user_groups` (
    `user_id`      INT UNSIGNED NOT NULL,
    `group_id`     INT UNSIGNED NOT NULL,
    `is_primary`   TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `group_id`),
    KEY `idx_dx_user_groups_group` (`group_id`),
    CONSTRAINT `fk_dx_user_groups_user`
        FOREIGN KEY (`user_id`) REFERENCES `dx_users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dx_user_groups_group`
        FOREIGN KEY (`group_id`) REFERENCES `dx_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dx_permissions` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `permission_key` VARCHAR(120) NOT NULL,
    `description`    VARCHAR(255) NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dx_permissions_key` (`permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dx_group_permissions` (
    `group_id`       INT UNSIGNED NOT NULL,
    `permission_id`  INT UNSIGNED NOT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`group_id`, `permission_id`),
    KEY `idx_dx_group_permissions_perm` (`permission_id`),
    CONSTRAINT `fk_dx_group_permissions_group`
        FOREIGN KEY (`group_id`) REFERENCES `dx_groups`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dx_group_permissions_perm`
        FOREIGN KEY (`permission_id`) REFERENCES `dx_permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2) Case Type + Stage + Routing Definitions
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `dx_case_types` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `case_type_key`   VARCHAR(80)  NOT NULL,
    `title`           VARCHAR(150) NOT NULL,
    `description`     TEXT         NULL,
    `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dx_case_types_key` (`case_type_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dx_case_stages` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `case_type_id`     INT UNSIGNED NOT NULL,
    `stage_key`        VARCHAR(100) NOT NULL,
    `title`            VARCHAR(150) NOT NULL,
    `sequence_no`      INT UNSIGNED NOT NULL DEFAULT 1,
    `is_terminal`      TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dx_case_stage_key` (`case_type_id`, `stage_key`),
    KEY `idx_dx_case_stages_sequence` (`case_type_id`, `sequence_no`),
    CONSTRAINT `fk_dx_case_stages_case_type`
        FOREIGN KEY (`case_type_id`) REFERENCES `dx_case_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dx_routing_rules` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `case_type_id`         INT UNSIGNED NOT NULL,
    `from_stage_key`       VARCHAR(100) NOT NULL,
    `action_key`           VARCHAR(80)  NOT NULL DEFAULT 'submit',
    `priority`             INT          NOT NULL DEFAULT 100,
    `condition_json`       JSON         NULL,
    `route_to_type`        ENUM('user','group','expression') NOT NULL DEFAULT 'group',
    `route_to_user_id`     INT UNSIGNED NULL,
    `route_to_group_id`    INT UNSIGNED NULL,
    `next_stage_key`       VARCHAR(100) NULL,
    `lock_case_on_claim`   TINYINT(1)   NOT NULL DEFAULT 1,
    `is_active`            TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_dx_routing_rules_lookup` (`case_type_id`, `from_stage_key`, `action_key`, `priority`),
    KEY `idx_dx_routing_rules_group` (`route_to_group_id`),
    KEY `idx_dx_routing_rules_user` (`route_to_user_id`),
    CONSTRAINT `fk_dx_routing_case_type`
        FOREIGN KEY (`case_type_id`) REFERENCES `dx_case_types`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dx_routing_group`
        FOREIGN KEY (`route_to_group_id`) REFERENCES `dx_groups`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_dx_routing_user`
        FOREIGN KEY (`route_to_user_id`) REFERENCES `dx_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3) Runtime Case Instances + Assignments + Events (worklist backbone)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `dx_case_instances` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `case_ref`              VARCHAR(40)     NOT NULL,
    `case_type_id`          INT UNSIGNED    NOT NULL,
    `business_key`          VARCHAR(120)    NULL,
    `status`                ENUM('draft','active','pending','on_hold','completed','rejected','cancelled') NOT NULL DEFAULT 'active',
    `current_stage_key`     VARCHAR(100)    NOT NULL,
    `initiator_user_id`     INT UNSIGNED    NULL,
    `current_assignee_user_id` INT UNSIGNED NULL,
    `current_assignee_group_id` INT UNSIGNED NULL,
    `is_locked`             TINYINT(1)      NOT NULL DEFAULT 0,
    `locked_by_user_id`     INT UNSIGNED    NULL,
    `locked_at`             DATETIME        NULL,
    `payload_json`          JSON            NULL,
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `completed_at`          DATETIME        NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dx_case_instances_ref` (`case_ref`),
    KEY `idx_dx_case_instances_type_status` (`case_type_id`, `status`),
    KEY `idx_dx_case_instances_stage` (`current_stage_key`),
    KEY `idx_dx_case_instances_assignee_user` (`current_assignee_user_id`),
    KEY `idx_dx_case_instances_assignee_group` (`current_assignee_group_id`),
    KEY `idx_dx_case_instances_created` (`created_at`),
    CONSTRAINT `fk_dx_case_instances_case_type`
        FOREIGN KEY (`case_type_id`) REFERENCES `dx_case_types`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_dx_case_instances_initiator`
        FOREIGN KEY (`initiator_user_id`) REFERENCES `dx_users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_dx_case_instances_assignee_user`
        FOREIGN KEY (`current_assignee_user_id`) REFERENCES `dx_users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_dx_case_instances_assignee_group`
        FOREIGN KEY (`current_assignee_group_id`) REFERENCES `dx_groups`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_dx_case_instances_locked_by`
        FOREIGN KEY (`locked_by_user_id`) REFERENCES `dx_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dx_assignments` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `case_instance_id`      BIGINT UNSIGNED NOT NULL,
    `stage_key`             VARCHAR(100)    NOT NULL,
    `status`                ENUM('ready','claimed','in_progress','completed','cancelled','reassigned') NOT NULL DEFAULT 'ready',
    `assigned_to_type`      ENUM('user','group') NOT NULL DEFAULT 'group',
    `assigned_user_id`      INT UNSIGNED    NULL,
    `assigned_group_id`     INT UNSIGNED    NULL,
    `claimed_by_user_id`    INT UNSIGNED    NULL,
    `claimed_at`            DATETIME        NULL,
    `due_at`                DATETIME        NULL,
    `priority`              INT             NOT NULL DEFAULT 100,
    `is_locked`             TINYINT(1)      NOT NULL DEFAULT 0,
    `lock_owner_user_id`    INT UNSIGNED    NULL,
    `lock_acquired_at`      DATETIME        NULL,
    `released_at`           DATETIME        NULL,
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_dx_assignments_case` (`case_instance_id`),
    KEY `idx_dx_assignments_status` (`status`),
    KEY `idx_dx_assignments_user` (`assigned_user_id`),
    KEY `idx_dx_assignments_group` (`assigned_group_id`),
    KEY `idx_dx_assignments_claimed_by` (`claimed_by_user_id`),
    KEY `idx_dx_assignments_stage` (`stage_key`),
    CONSTRAINT `fk_dx_assignments_case`
        FOREIGN KEY (`case_instance_id`) REFERENCES `dx_case_instances`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dx_assignments_user`
        FOREIGN KEY (`assigned_user_id`) REFERENCES `dx_users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_dx_assignments_group`
        FOREIGN KEY (`assigned_group_id`) REFERENCES `dx_groups`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_dx_assignments_claimed_by`
        FOREIGN KEY (`claimed_by_user_id`) REFERENCES `dx_users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_dx_assignments_lock_owner`
        FOREIGN KEY (`lock_owner_user_id`) REFERENCES `dx_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dx_case_events` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `case_instance_id`   BIGINT UNSIGNED NOT NULL,
    `assignment_id`      BIGINT UNSIGNED NULL,
    `event_type`         VARCHAR(80)     NOT NULL,
    `actor_user_id`      INT UNSIGNED    NULL,
    `details_json`       JSON            NULL,
    `created_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_dx_case_events_case` (`case_instance_id`),
    KEY `idx_dx_case_events_assignment` (`assignment_id`),
    KEY `idx_dx_case_events_actor` (`actor_user_id`),
    KEY `idx_dx_case_events_type` (`event_type`),
    CONSTRAINT `fk_dx_case_events_case`
        FOREIGN KEY (`case_instance_id`) REFERENCES `dx_case_instances`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dx_case_events_assignment`
        FOREIGN KEY (`assignment_id`) REFERENCES `dx_assignments`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_dx_case_events_actor`
        FOREIGN KEY (`actor_user_id`) REFERENCES `dx_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4) Aggregated metrics table (optional accelerator for dashboards)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `dx_case_metrics_daily` (
    `metric_date`         DATE         NOT NULL,
    `case_type_id`        INT UNSIGNED NOT NULL,
    `status`              VARCHAR(40)  NOT NULL,
    `case_count`          INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`metric_date`, `case_type_id`, `status`),
    KEY `idx_dx_case_metrics_case_type` (`case_type_id`),
    CONSTRAINT `fk_dx_case_metrics_case_type`
        FOREIGN KEY (`case_type_id`) REFERENCES `dx_case_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5) Seed minimal permissions + groups for admission flow
-- -----------------------------------------------------------------------------

INSERT IGNORE INTO `dx_permissions` (`permission_key`, `description`) VALUES
('case.create.admission', 'Create admission cases'),
('case.view.own', 'View own initiated cases'),
('case.view.group_queue', 'View group work queue'),
('case.claim.group_assignment', 'Claim assignment from group queue'),
('case.process.assigned', 'Process claimed assignments'),
('case.admin.configure_routing', 'Configure case routing and stages'),
('rbac.admin.manage_users', 'Manage users and group memberships');

INSERT IGNORE INTO `dx_groups` (`group_key`, `group_name`, `description`) VALUES
('students', 'Students', 'External/student initiators'),
('admissions_officers', 'Admissions Officers', 'Back-office admission processing team'),
('dx_admins', 'DX Administrators', 'RBAC and routing configuration administrators');

SET FOREIGN_KEY_CHECKS = 1;
