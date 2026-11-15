# Changelog

Changes made to the backend and the database design, newest first.

## Database moved to PostgreSQL (Supabase)
- The team database is now **PostgreSQL on Supabase** (one shared database) instead of a local MySQL. `docs/database/schema.sql` is rewritten for PostgreSQL; the tables and columns are the same.
- New `docs/database/supabase-security.sql` turns on Row Level Security on every table so the Supabase public API cannot read or write them.
- **Emails are saved in lower case** (middleware + model + a one-time migration), because PostgreSQL treats `A@x.com` and `a@x.com` as different. Login, register, forgot password and the doctor re-submit are case-insensitive.
- `.env.example` and the READMEs describe the Supabase connection and the rules for a shared database.

## Delete my account
- `POST /api/auth/delete-account` (patient or doctor, needs the current password) deletes the account and all its data for good: profile, chat messages and voice recordings, mood logs, completed challenges, posts, and a doctor's ID card, certificate and photo. No database change (uses the existing `ON DELETE CASCADE`).

## Profile rules, rejected doctors, 3-screen forgot password
- **Patient** edits `first_name`, `last_name`, `dob` (still older than 16) and `marital_status`. Email and gender can no longer be edited.
- **Doctor** edits `first_name`, `last_name`, `phone`, `clinic_url`, `bio` (max 300), `photo` and `specialization`. Email cannot be edited. Changing the specialization sends the doctor back to `pending` (logged out until an admin approves again); any other change stays `active`.
- **Rejected doctor:** login answers `403` with `status: rejected` and `can_resubmit: true`. New `POST /api/auth/doctor/resubmit` lets the doctor send all the data again; the profile goes back to `pending`.
- **Forgot password** is now the 3 screens: email, then code (`POST /api/auth/verify-reset-code`, new), then new password + confirmation. Clear messages for a wrong email/code and for passwords that do not match.
- Removed the "email change with current password" option (the email is not editable any more). No database change.

## Edit or delete my posts, change specialization
- `POST /api/posts/{id}` edits and `DELETE /api/posts/{id}` removes a post. Only the author can; everyone else gets 403. No database change.
- A doctor can now change the `specialization`. The account then goes back to `pending`, the doctor is logged out, and an admin has to approve again.

## Edit own profile
- Patients (`POST /api/patient/profile`) and doctors (`POST /api/doctor/profile`) can edit their own data. Send only the fields to change.
- Changing the email needs the current password. New `POST /api/auth/change-password` (logs out the other devices).
- Role, doctor status and admin-verified data (specialization, date of birth, ID card, certificate) cannot be edited by the user.
- A new doctor photo replaces and deletes the old one. No database change.

## Doctor profile
- `contact_links` (JSON) split into two columns: `phone` and `clinic_url`.
- New `photo` (profile picture, public) and `bio` (max 300 characters) columns. Phone and photo are required when a doctor registers.
- Migration `update_doctor_profiles_contact_photo_bio` moves existing data to the new columns.

## Voice messages in the chatbot
- `POST /api/patient/chat/voice`: the patient records a message, the AI service transcribes it and detects the emotion in the tone of voice.
- New `chat_messages` columns: `audio_path`, `emotion` (`happy`, `sad`, `angry`, `anxious`, `neutral`), `emotion_score`.
- Recordings are stored privately and only the owner can play them back.

## Forgot password
- `POST /api/auth/forgot-password` and `POST /api/auth/reset-password` with a 6-digit code sent by email.
- Uses Laravel's built-in `password_reset_tokens` table (no new table).

## Posts
- New `posts` table. Patients and doctors can write and read posts.

## Patient data
- `patients` gets `dob`, `gender` (`male`, `female`) and `marital_status` (`single`, `married`).
- The patient must be older than 16 years at registration.

## Users
- `users.name` replaced by `first_name` and `last_name`.

## Initial backend
- Authentication with roles (patient, doctor, admin), admin approval of doctors.
- Text chat with the AI service, mood logs, daily challenges, articles, categories and audios.
