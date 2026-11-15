-- ============================================================================
-- Calma: PostgreSQL schema (hosted on Supabase)
-- Matches the Laravel migrations in backend/database/migrations.
--
-- The Laravel migrations are the source of truth: run `php artisan migrate --seed`.
-- This file is the same schema as plain SQL, for documentation and quick review.
--
-- Laravel creates its "enum" columns on PostgreSQL as VARCHAR + a CHECK constraint, shown the same way here.
--
-- Business rules enforced by the API (not by SQL, because they depend on today's date):
--   * patients.dob          : the patient must be older than 16 years at registration
--   * doctor_profiles.bio   : at most 300 characters (also enforced by VARCHAR(300))
--   * mood_logs.mood_score  : 1 to 5
--   * users.email           : stored in lower case (PostgreSQL is case-sensitive, the API lower-cases it)
-- ============================================================================

-- ---------------------------------------------------------------- users ----
CREATE TABLE users (
  id                BIGSERIAL PRIMARY KEY,
  first_name        VARCHAR(255) NOT NULL,
  last_name         VARCHAR(255) NOT NULL,
  email             VARCHAR(255) NOT NULL,
  email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,   -- Laravel default column
  password          VARCHAR(255) NOT NULL,                 -- bcrypt hash
  role              VARCHAR(255) NOT NULL DEFAULT 'patient'
                    CHECK (role IN ('patient', 'doctor', 'admin')),
  remember_token    VARCHAR(100) NULL,                     -- Laravel default column
  created_at        TIMESTAMP(0) WITHOUT TIME ZONE NULL,
  updated_at        TIMESTAMP(0) WITHOUT TIME ZONE NULL,   -- Laravel default column
  CONSTRAINT users_email_unique UNIQUE (email)
);
CREATE INDEX users_role_index ON users (role);

-- ------------------------------------------------------ doctor_profiles ----
CREATE TABLE doctor_profiles (
  id              BIGSERIAL PRIMARY KEY,
  user_id         BIGINT NOT NULL,
  specialization  VARCHAR(255) NOT NULL,
  dob             DATE NOT NULL,
  id_card_img     VARCHAR(255) NOT NULL,                   -- private file
  certificate_img VARCHAR(255) NOT NULL,                   -- private file
  phone           VARCHAR(20)  NULL,                       -- required by the API for new doctors
  clinic_url      VARCHAR(255) NULL,                       -- link to the clinic
  photo           VARCHAR(255) NULL,                       -- profile picture (public), required by the API
  bio             VARCHAR(300) NULL,                       -- max 300 characters
  status          VARCHAR(255) NOT NULL DEFAULT 'pending'
                  CHECK (status IN ('pending', 'active', 'rejected')),
  CONSTRAINT doctor_profiles_user_id_unique UNIQUE (user_id),
  CONSTRAINT doctor_profiles_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- ------------------------------------------------------------- patients ----
CREATE TABLE patients (
  id             BIGSERIAL PRIMARY KEY,
  user_id        BIGINT NOT NULL,
  dob            DATE NOT NULL,                            -- age is calculated from this (must be > 16 years)
  gender         VARCHAR(255) NOT NULL CHECK (gender IN ('male', 'female')),
  marital_status VARCHAR(255) NOT NULL CHECK (marital_status IN ('single', 'married')),
  CONSTRAINT patients_user_id_unique UNIQUE (user_id),
  CONSTRAINT patients_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- ----------------------------------------------------------- challenges ----
CREATE TABLE challenges (
  id   BIGSERIAL PRIMARY KEY,
  text VARCHAR(255) NOT NULL,
  type VARCHAR(255) NOT NULL CHECK (type IN ('breathing', 'walking', 'reading'))
);

-- -------------------------------------------------------- chat_messages ----
CREATE TABLE chat_messages (
  id            BIGSERIAL PRIMARY KEY,
  patient_id    BIGINT NOT NULL,
  message       TEXT NOT NULL,                             -- for voice messages: the transcript
  reply         TEXT NULL,
  is_danger     BOOLEAN NOT NULL DEFAULT FALSE,
  audio_path    VARCHAR(255) NULL,                         -- private recording, NULL for text messages
  emotion       VARCHAR(255) NULL
                CHECK (emotion IN ('happy', 'sad', 'angry', 'anxious', 'neutral')),   -- from the tone of voice
  emotion_score NUMERIC(4,3) NULL,                         -- model confidence, 0.000 to 1.000
  created_at    TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chat_messages_patient_id_foreign FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE
);
CREATE INDEX chat_messages_patient_id_created_at_index ON chat_messages (patient_id, created_at);
CREATE INDEX chat_messages_is_danger_index ON chat_messages (is_danger);

-- ------------------------------------------------------------ mood_logs ----
CREATE TABLE mood_logs (
  id           BIGSERIAL PRIMARY KEY,
  patient_id   BIGINT NOT NULL,
  challenge_id BIGINT NULL,
  mood_score   SMALLINT NOT NULL,                          -- 1 to 5
  logged_at    TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT mood_logs_patient_id_foreign   FOREIGN KEY (patient_id)   REFERENCES patients (id)   ON DELETE CASCADE,
  CONSTRAINT mood_logs_challenge_id_foreign FOREIGN KEY (challenge_id) REFERENCES challenges (id) ON DELETE SET NULL
);
CREATE INDEX mood_logs_patient_id_logged_at_index ON mood_logs (patient_id, logged_at);

-- ------------------------------------------------- patient_challenges ----
CREATE TABLE patient_challenges (
  id           BIGSERIAL PRIMARY KEY,
  patient_id   BIGINT NOT NULL,
  challenge_id BIGINT NOT NULL,
  completed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT patient_challenges_patient_id_foreign   FOREIGN KEY (patient_id)   REFERENCES patients (id)   ON DELETE CASCADE,
  CONSTRAINT patient_challenges_challenge_id_foreign FOREIGN KEY (challenge_id) REFERENCES challenges (id) ON DELETE CASCADE
);
CREATE INDEX patient_challenges_patient_id_completed_at_index ON patient_challenges (patient_id, completed_at);

-- -------------------------------------------------------------- content ----
CREATE TABLE categories (
  id   BIGSERIAL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,                              -- anxiety, sleep, depression
  CONSTRAINT categories_name_unique UNIQUE (name)
);

CREATE TABLE articles (
  id      BIGSERIAL PRIMARY KEY,
  title   VARCHAR(255) NOT NULL,
  summary TEXT NOT NULL,
  url     VARCHAR(255) NOT NULL,
  cover   VARCHAR(255) NULL
);

CREATE TABLE article_category (
  article_id  BIGINT NOT NULL,
  category_id BIGINT NOT NULL,
  PRIMARY KEY (article_id, category_id),
  CONSTRAINT article_category_article_id_foreign  FOREIGN KEY (article_id)  REFERENCES articles (id)   ON DELETE CASCADE,
  CONSTRAINT article_category_category_id_foreign FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
);

CREATE TABLE audios (
  id        BIGSERIAL PRIMARY KEY,
  title     VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL
);

-- ---------------------------------------------------------------- posts ----
CREATE TABLE posts (
  id         BIGSERIAL PRIMARY KEY,
  user_id    BIGINT NOT NULL,                              -- a patient or a doctor
  content    TEXT NOT NULL,                                -- API limit: 5000 characters
  created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT posts_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);
CREATE INDEX posts_created_at_index ON posts (created_at);

-- --------------------------------- Laravel / Sanctum tables used by the API ----
-- Created by Laravel's own migrations. Listed here because the API depends on them.

-- Hashed 6-digit codes for "forgot password" (valid 15 minutes, one use).
CREATE TABLE password_reset_tokens (
  email      VARCHAR(255) PRIMARY KEY,
  token      VARCHAR(255) NOT NULL,
  created_at TIMESTAMP(0) WITHOUT TIME ZONE NULL
);

-- Login tokens (Sanctum).
CREATE TABLE personal_access_tokens (
  id             BIGSERIAL PRIMARY KEY,
  tokenable_type VARCHAR(255) NOT NULL,
  tokenable_id   BIGINT NOT NULL,
  name           VARCHAR(255) NOT NULL,
  token          VARCHAR(64)  NOT NULL,
  abilities      TEXT NULL,
  last_used_at   TIMESTAMP(0) WITHOUT TIME ZONE NULL,
  expires_at     TIMESTAMP(0) WITHOUT TIME ZONE NULL,
  created_at     TIMESTAMP(0) WITHOUT TIME ZONE NULL,
  updated_at     TIMESTAMP(0) WITHOUT TIME ZONE NULL,
  CONSTRAINT personal_access_tokens_token_unique UNIQUE (token)
);
CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index
  ON personal_access_tokens (tokenable_type, tokenable_id);
