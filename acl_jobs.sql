-- ============================================================
-- ASL Jobs / Advenware Career Link
-- Database Schema: acl_jobs
-- ============================================================
-- Import this file into phpMyAdmin or via MySQL CLI:
--   mysql -u root -p acl_jobs < acl_jobs.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Database: acl_jobs
-- ============================================================
CREATE DATABASE IF NOT EXISTS `acl_jobs`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `acl_jobs`;

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`        VARCHAR(50)  NOT NULL,
  `email`           VARCHAR(255) NOT NULL,
  `password`        VARCHAR(255) NOT NULL,
  `role`            ENUM('job_seeker', 'employer', 'admin') NOT NULL DEFAULT 'job_seeker',
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),
  UNIQUE KEY `uk_users_username` (`username`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: companies
-- ============================================================
CREATE TABLE IF NOT EXISTS `companies` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `name`            VARCHAR(255) NOT NULL,
  `slug`            VARCHAR(255) NOT NULL,
  `description`     TEXT,
  `website`         VARCHAR(255),
  `email`           VARCHAR(255),
  `phone`           VARCHAR(50),
  `address`         TEXT,
  `logo`            VARCHAR(500),
  `industry`        VARCHAR(100),
  `size`            VARCHAR(50),
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_companies_slug` (`slug`),
  UNIQUE KEY `uk_companies_user_id` (`user_id`),
  KEY `idx_companies_name` (`name`),
  CONSTRAINT `fk_companies_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: profiles
-- ============================================================
CREATE TABLE IF NOT EXISTS `profiles` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`             INT UNSIGNED NOT NULL,
  `full_name`           VARCHAR(255) NOT NULL,
  `username`            VARCHAR(50),
  `email`               VARCHAR(255),
  `phone`               VARCHAR(50),
  `location`            VARCHAR(255),
  `bio`                 TEXT,
  `skills`              TEXT,
  `education`           TEXT,
  `experience`          TEXT,
  `professional_title`  VARCHAR(255),
  `years_of_experience` VARCHAR(50),
  `employment_type`     VARCHAR(50),
  `work_preference`     VARCHAR(50),
  `expected_salary`     VARCHAR(255),
  `career_objective`    TEXT,
  `profile_photo`       VARCHAR(500),
  `cv_url`              VARCHAR(500),
  `profile_completion`  INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_profiles_user_id` (`user_id`),
  KEY `idx_profiles_full_name` (`full_name`),
  CONSTRAINT `fk_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: jobs
-- ============================================================
CREATE TABLE IF NOT EXISTS `jobs` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT UNSIGNED NOT NULL,
  `title`           VARCHAR(255) NOT NULL,
  `description`     TEXT NOT NULL,
  `location`        VARCHAR(255) NOT NULL,
  `category`        VARCHAR(100),
  `employment_type` VARCHAR(50)  NOT NULL,
  `salary`          VARCHAR(255),
  `requirements`    TEXT,
  `status`          ENUM('active', 'closed', 'draft', 'archived') NOT NULL DEFAULT 'active',
  `is_featured`     TINYINT(1)   NOT NULL DEFAULT 0,
  `views_count`     INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jobs_company_id` (`company_id`),
  KEY `idx_jobs_status` (`status`),
  KEY `idx_jobs_featured` (`is_featured`),
  KEY `idx_jobs_category` (`category`),
  KEY `idx_jobs_location` (`location`),
  KEY `idx_jobs_employment_type` (`employment_type`),
  FULLTEXT KEY `ft_jobs_title_desc` (`title`, `description`),
  CONSTRAINT `fk_jobs_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: applications
-- ============================================================
CREATE TABLE IF NOT EXISTS `applications` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `job_id`          INT UNSIGNED NOT NULL,
  `status`          ENUM('pending', 'reviewing', 'shortlisted', 'interview', 'accepted', 'rejected', 'withdrawn') NOT NULL DEFAULT 'pending',
  `cover_letter`    TEXT,
  `cv_url`          VARCHAR(500),
  `applied_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_applications_user_job` (`user_id`, `job_id`),
  KEY `idx_applications_user_id` (`user_id`),
  KEY `idx_applications_job_id` (`job_id`),
  KEY `idx_applications_status` (`status`),
  KEY `idx_applications_applied_at` (`applied_at`),
  CONSTRAINT `fk_applications_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_applications_job` FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: saved_jobs
-- ============================================================
CREATE TABLE IF NOT EXISTS `saved_jobs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `job_id`      INT UNSIGNED NOT NULL,
  `saved_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_saved_jobs_user_job` (`user_id`, `job_id`),
  KEY `idx_saved_jobs_user_id` (`user_id`),
  KEY `idx_saved_jobs_job_id` (`job_id`),
  CONSTRAINT `fk_saved_jobs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_saved_jobs_job` FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: interviews
-- ============================================================
CREATE TABLE IF NOT EXISTS `interviews` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id`  INT UNSIGNED NOT NULL,
  `job_id`          INT UNSIGNED NOT NULL,
  `user_id`         INT UNSIGNED NOT NULL,
  `company_id`      INT UNSIGNED NOT NULL,
  `title`           VARCHAR(255) NOT NULL,
  `description`       TEXT,
  `scheduled_at`    DATETIME NOT NULL,
  `duration`        INT UNSIGNED NOT NULL DEFAULT 60,
  `location`        VARCHAR(255),
  `meeting_link`    VARCHAR(500),
  `status`          ENUM('scheduled', 'completed', 'cancelled', 'rescheduled') NOT NULL DEFAULT 'scheduled',
  `notes`           TEXT,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_interviews_application_id` (`application_id`),
  KEY `idx_interviews_job_id` (`job_id`),
  KEY `idx_interviews_user_id` (`user_id`),
  KEY `idx_interviews_company_id` (`company_id`),
  KEY `idx_interviews_scheduled_at` (`scheduled_at`),
  KEY `idx_interviews_status` (`status`),
  CONSTRAINT `fk_interviews_application` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_interviews_job` FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_interviews_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_interviews_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `type`            VARCHAR(100) NOT NULL,
  `title`           VARCHAR(255) NOT NULL,
  `message`         TEXT NOT NULL,
  `data`            JSON,
  `is_read`         TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_id` (`user_id`),
  KEY `idx_notifications_is_read` (`is_read`),
  KEY `idx_notifications_type` (`type`),
  KEY `idx_notifications_created_at` (`created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: auth_tokens (for token-based authentication)
-- ============================================================
CREATE TABLE IF NOT EXISTS `auth_tokens` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `token`           VARCHAR(500) NOT NULL,
  `token_hash`      VARCHAR(255) NOT NULL,
  `expires_at`      DATETIME NOT NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at`    TIMESTAMP    NULL,
  `is_revoked`      TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_auth_tokens_token_hash` (`token_hash`),
  KEY `idx_auth_tokens_user_id` (`user_id`),
  KEY `idx_auth_tokens_expires_at` (`expires_at`),
  KEY `idx_auth_tokens_is_revoked` (`is_revoked`),
  CONSTRAINT `fk_auth_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Seed Data
-- ============================================================

-- Default admin user (password: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `role`, `is_active`) VALUES
  ('admin', 'admin@acljobs.com', '$2y$10$vzRfci2p/R0F86sNkIybiO5/rkYKrbiHOPmGN1EYVD5yGNfdtmIku', 'admin', 1);

-- Note: The password hash above is a valid bcrypt hash for "admin123".
-- Use the register.php endpoint to create additional users with properly hashed passwords.
