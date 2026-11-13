-- ============================================================================
-- Calma: MySQL schema (matches the Laravel migrations in backend/database/migrations)
--
-- The Laravel migrations are the source of truth: run `php artisan migrate --seed`.
-- This file is the same schema as plain SQL, for documentation and quick review.
-- Database: calma   Charset: utf8mb4 (Arabic + emoji)
--
-- Business rules enforced by the API (not by SQL, because they depend on today's date):
--   * patients.dob       : the patient must be older than 16 years at registration
--   * doctor_profiles.bio: at most 300 characters (also enforced by the VARCHAR(300) column)
--   * mood_logs.mood_score: 1 to 5
-- ============================================================================

CREATE DATABASE IF NOT EXISTS calma CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE calma;

-- ---------------------------------------------------------------- users ----
CREATE TABLE users (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name        VARCHAR(255) NOT NULL,
  last_name         VARCHAR(255) NOT NULL,
  email             VARCHAR(255) NOT NULL,
  email_verified_at TIMESTAMP NULL,                       -- Laravel default column
  password          VARCHAR(255) NOT NULL,                -- bcrypt hash
  role              ENUM('patient','doctor','admin') NOT NULL DEFAULT 'patient',
  remember_token    VARCHAR(100) NULL,                    -- Laravel default column
  created_at        TIMESTAMP NULL,
  updated_at        TIMESTAMP NULL,                       -- Laravel default column
  PRIMARY KEY (id),
  UNIQUE KEY users_email_unique (email),
  KEY users_role_index (role)
) ENGINE=InnoDB;

-- ------------------------------------------------------ doctor_profiles ----
CREATE TABLE doctor_profiles (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,
  specialization  VARCHAR(255) NOT NULL,
  dob             DATE NOT NULL,
  id_card_img     VARCHAR(255) NOT NULL,                  -- private file
  certificate_img VARCHAR(255) NOT NULL,                  -- private file
  phone           VARCHAR(20)  NULL,                      -- required by the API for new doctors
  clinic_url      VARCHAR(255) NULL,                      -- link to the clinic
  photo           VARCHAR(255) NULL,                      -- profile picture (public disk), required by the API
  bio             VARCHAR(300) NULL,                      -- max 300 characters
  status          ENUM('pending','active','rejected') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (id),
  UNIQUE KEY doctor_profiles_user_id_unique (user_id),
  CONSTRAINT doctor_profiles_user_id_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------- patients ----
CREATE TABLE patients (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED NOT NULL,
  dob            DATE NOT NULL,                           -- age is calculated from this (must be > 16 years)
  gender         ENUM('male','female') NOT NULL,
  marital_status ENUM('single','married') NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY patients_user_id_unique (user_id),
  CONSTRAINT patients_user_id_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------- challenges ----
CREATE TABLE challenges (
  id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  text VARCHAR(255) NOT NULL,
  type ENUM('breathing','walking','reading') NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- -------------------------------------------------------- chat_messages ----
CREATE TABLE chat_messages (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  patient_id    BIGINT UNSIGNED NOT NULL,
  message       TEXT NOT NULL,                            -- for voice messages: the transcript
  reply         TEXT NULL,
  is_danger     TINYINT(1) NOT NULL DEFAULT 0,
  audio_path    VARCHAR(255) NULL,                        -- private recording, NULL for text messages
  emotion       ENUM('happy','sad','angry','anxious','neutral') NULL,   -- from the tone of voice
  emotion_score DECIMAL(4,3) NULL,                        -- model confidence, 0.000 to 1.000
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY chat_messages_patient_id_created_at_index (patient_id, created_at),
  KEY chat_messages_is_danger_index (is_danger),
  CONSTRAINT chat_messages_patient_id_fk FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------ mood_logs ----
CREATE TABLE mood_logs (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  patient_id   BIGINT UNSIGNED NOT NULL,
  challenge_id BIGINT UNSIGNED NULL,
  mood_score   TINYINT UNSIGNED NOT NULL,                 -- 1 to 5
  logged_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY mood_logs_patient_id_logged_at_index (patient_id, logged_at),
  CONSTRAINT mood_logs_patient_id_fk   FOREIGN KEY (patient_id)   REFERENCES patients (id)   ON DELETE CASCADE,
  CONSTRAINT mood_logs_challenge_id_fk FOREIGN KEY (challenge_id) REFERENCES challenges (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------- patient_challenges ----
CREATE TABLE patient_challenges (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  patient_id   BIGINT UNSIGNED NOT NULL,
  challenge_id BIGINT UNSIGNED NOT NULL,
  completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY patient_challenges_patient_id_completed_at_index (patient_id, completed_at),
  CONSTRAINT patient_challenges_patient_id_fk   FOREIGN KEY (patient_id)   REFERENCES patients (id)   ON DELETE CASCADE,
  CONSTRAINT patient_challenges_challenge_id_fk FOREIGN KEY (challenge_id) REFERENCES challenges (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------- content ----
CREATE TABLE categories (
  id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,                             -- anxiety, sleep, depression
  PRIMARY KEY (id),
  UNIQUE KEY categories_name_unique (name)
) ENGINE=InnoDB;

CREATE TABLE articles (
  id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title   VARCHAR(255) NOT NULL,
  summary TEXT NOT NULL,
  url     VARCHAR(255) NOT NULL,
  cover   VARCHAR(255) NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE article_category (
  article_id  BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (article_id, category_id),
  CONSTRAINT article_category_article_id_fk  FOREIGN KEY (article_id)  REFERENCES articles (id)   ON DELETE CASCADE,
  CONSTRAINT article_category_category_id_fk FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audios (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title     VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- posts ----
CREATE TABLE posts (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,                    -- a patient or a doctor
  content    TEXT NOT NULL,                               -- API limit: 5000 characters
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY posts_created_at_index (created_at),
  CONSTRAINT posts_user_id_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- --------------------------------- Laravel / Sanctum tables used by the API ----
-- Created by Laravel's own migrations. Listed here because the API depends on them.

-- Hashed 6-digit codes for "forgot password" (valid 15 minutes, one use).
CREATE TABLE password_reset_tokens (
  email      VARCHAR(255) NOT NULL,
  token      VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL,
  PRIMARY KEY (email)
) ENGINE=InnoDB;

-- Login tokens (Sanctum).
CREATE TABLE personal_access_tokens (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tokenable_type VARCHAR(255) NOT NULL,
  tokenable_id   BIGINT UNSIGNED NOT NULL,
  name           VARCHAR(255) NOT NULL,
  token          VARCHAR(64)  NOT NULL,
  abilities      TEXT NULL,
  last_used_at   TIMESTAMP NULL,
  expires_at     TIMESTAMP NULL,
  created_at     TIMESTAMP NULL,
  updated_at     TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY personal_access_tokens_token_unique (token),
  KEY personal_access_tokens_tokenable_index (tokenable_type, tokenable_id)
) ENGINE=InnoDB;
