# Psych Support API: Laravel backend (ERD only)

Everything here maps to the tables in the ERD: users, doctor_profiles, patients, chat_messages,
mood_logs, challenges, patient_challenges (articles / categories / article_category / audios included).

## Setup (Laravel 11 / 12)
```bash
composer create-project laravel/laravel psych-api
cd psych-api
php artisan install:api          # installs Sanctum + routes/api.php
```
1. Copy `app/`, `config/`, `database/`, `routes/`, `tests/` from this pack into the project (overwrite `routes/api.php`).
2. Register the role middleware in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
    ]);
})
```
3. `.env`: database + admin account + (optional) AI service
```
ADMIN_EMAIL=admin@yourdomain.com
ADMIN_PASSWORD=use-a-strong-password
CHATBOT_URL=        # empty = built-in stub reply for development
CHATBOT_API_KEY=
```
4. `php artisan storage:link` (so article covers and audio files are reachable), then `php artisan migrate --seed` then `php artisan serve`, and `php artisan test`.

## Endpoints
| Method | URL | Who |
|---|---|---|
| POST | /api/auth/register | public (doctor uses multipart/form-data) |
| POST | /api/auth/login | public |
| GET | /api/auth/me | logged-in |
| POST | /api/auth/logout | logged-in |
| POST | /api/auth/delete-account | patient or doctor (`current_password`): deletes the account and all its data for good |
| POST | /api/auth/change-password | logged-in (`current_password`, `password`, `password_confirmation`) |
| POST | /api/auth/forgot-password | public, screen 1 (`email`) |
| POST | /api/auth/verify-reset-code | public, screen 2 (`email`, `code`) |
| POST | /api/auth/reset-password | public, screen 3 (`email`, `code`, `password`, `password_confirmation`) |
| POST | /api/auth/doctor/resubmit | a **rejected** doctor sends the data again (multipart; `email` + `password` + all the registration data) |
| GET | /api/admin/doctors?status=pending | admin |
| POST | /api/admin/doctors/{id}/approve | admin |
| POST | /api/admin/doctors/{id}/reject | admin |
| GET | /api/categories, /api/articles?category_id=, /api/articles/{id}, /api/audios, /api/audios/{id} | any logged-in user |
| POST / DELETE | /api/admin/categories, /api/admin/articles, /api/admin/audios | admin (create) |
| POST / DELETE | /api/admin/{categories,articles,audios}/{id} | admin (POST = update, DELETE = remove) |
| GET / POST | /api/posts, GET /api/posts/{id} | patient or doctor (write and read posts) |
| POST / DELETE | /api/posts/{id} | the author only (POST edits, DELETE removes my own post) |
| POST | /api/patient/profile | patient (edit own data: send only the fields to change) |
| POST | /api/doctor/profile | doctor (edit own data; multipart when sending a new photo) |
| GET / POST | /api/patient/chat | patient |
| POST | /api/patient/chat/voice | patient (multipart `audio`: recorded voice message) |
| GET | /api/patient/chat/{id}/audio | patient (plays back their own recording) |
| GET / POST | /api/patient/mood-logs | patient (`mood_score` 1-5, optional `challenge_id`) |
| GET | /api/patient/challenges?type= | patient |
| POST | /api/patient/challenges/{id}/complete | patient |

Articles take `title, summary, url, cover (image), category_ids[]`; audios take `title, file` (mp3/wav/m4a/aac/ogg, up to 20 MB). Both are multipart/form-data, and updates use POST so file uploads work.

Register takes `first_name` and `last_name` (the users table has no single `name` column; the migration renames the default `name` to `first_name`).

Send `Accept: application/json` and `Authorization: Bearer <token>`.

## Registration fields
| Field | Patient | Doctor |
|---|---|---|
| first_name, last_name, email, password (+confirmation), role | required | required |
| dob (date of birth) | required, **must be older than 16 years** | required, must be in the past |
| gender (`male` / `female`) | required | not used |
| marital_status (`single` / `married`) | required | not used |
| specialization, id_card_img, certificate_img | not used | required |
| phone (8 to 15 digits, may start with `+`) | not used | required |
| photo (profile picture, image up to 4 MB) | not used | required |
| clinic_url (link to the clinic) | not used | optional |
| bio (about the doctor) | not used | optional, **max 300 characters** |

- Age is **not stored** as a number: we store `dob` and the API returns a calculated `age` on the patient, so it is always correct.
- The age rule lives in `AuthController` (it depends on today's date, so it can't be a fixed database CHECK). To change the limit, edit `Patient::MIN_AGE`.

## Doctor profile
- `phone` and `clinic_url` are separate columns (they replace the old `contact_links` JSON). `php artisan migrate` converts existing doctors' data automatically, nothing is deleted.
- The profile `photo` is stored on the **public** disk (patients see it), so run `php artisan storage:link`. The ID card and certificate stay on the private disk.
- `phone` and `photo` are required by the API for new doctors; in the database they are nullable only so doctors who registered before this change keep working.

## Editing your own data
Send only the fields you want to change. Fields that are not allowed are ignored. `GET /api/auth/me` returns the current data.

| | Can edit | Cannot edit |
|---|---|---|
| **Patient** `POST /api/patient/profile` | `first_name`, `last_name`, `dob` (still **older than 16**), `marital_status` | `email`, `gender` |
| **Doctor** `POST /api/doctor/profile` (multipart when sending a photo) | `first_name`, `last_name`, `phone`, `clinic_url` (send null to clear), `bio` (**max 300 characters**), `photo`, `specialization` | `email`, `dob`, ID card, certificate, `status` |

- **Doctor + specialization:** if the specialization really changes, the account goes back to **`pending`**, the doctor is logged out, and cannot log in until an admin approves again (the answer has `requires_approval: true`). **Any other change keeps the doctor `active`.**
- A new photo replaces and deletes the old one.
- Change password: `POST /api/auth/change-password` (needs `current_password`; logs out the other devices).

## Delete my account
`POST /api/auth/delete-account` with `{"current_password": "..."}`, for a **patient** or a **doctor** (an admin cannot delete themselves).
- It is **permanent**. The app should show a confirmation message first, then ask for the password.
- Everything of the user is removed: the account, the patient or doctor profile, chat messages and **voice recordings**, mood logs, completed challenges, posts, and for doctors the ID card, certificate and photo.
- All login tokens stop working at once, and the same email can register again later.
- Other users' data is not touched. No database change: it uses the `ON DELETE CASCADE` links that already exist.

## Posts
- `POST /api/posts` with `{content}` (max 5000 chars), `GET /api/posts` (newest first, 15 per page), `GET /api/posts/{id}`.
- Open to patients and doctors only (admins and guests get 403 / 401).
- Each user can **edit (`POST /api/posts/{id}`) or delete (`DELETE /api/posts/{id}`) only their own posts**; anyone else gets 403.
- Each post returns its author's `first_name`, `last_name`, `role` (never the email). For doctors it also returns `specialization`, `phone`, `clinic_url`, `bio` and `photo_url`.

## If you already ran the old migrations
The `patients` table changed and `posts` is new, so reset the dev database:
```bash
php artisan migrate:fresh --seed
```
This drops every table and rebuilds them, so test accounts created earlier in Postman are gone (register them again).

## Forgot password (3 screens)
1. **Screen 1:** the user types the email and presses *Send code*: `POST /api/auth/forgot-password` `{email}`. A 6-digit code is emailed (valid 15 minutes). The answer is the same whether or not the email exists. The app opens screen 2.
2. **Screen 2:** the user types the 6 digits and presses *Confirm*: `POST /api/auth/verify-reset-code` `{email, code}`.
   - Wrong email or wrong/expired code: `422` with the message **"الإيميل أو الكود غير صحيح."** (one message for all cases, so nobody can find out which emails exist). The user stays on screen 2.
   - Right code: `200 {"valid": true}`. The app opens screen 3 and keeps the email and code in memory. The code is **not** used up yet.
3. **Screen 3:** two fields, *new password* and *confirm new password*, then: `POST /api/auth/reset-password` `{email, code, password, password_confirmation}`.
   - The two fields are different: `422` on `password` with **"كلمة المرور الجديدة وتأكيدها لازم يكونوا متطابقين."** (the code stays valid, the user just fixes it).
   - Success: `200`. The app opens the **login screen** and the user logs in with the email and the new password.
- The code works once, 5 wrong guesses (on screen 2 or 3) cancel it, and after a reset all old login tokens stop working.
- The hashed code lives in Laravel's built-in `password_reset_tokens` table: no new table.
- **While developing:** keep `MAIL_MAILER=log` in `.env` and the email (with the code) is written to `storage/logs/laravel.log`. For real emails use Mailtrap or Gmail SMTP in `.env`.

## Rejected doctor: register the data again
- When an admin rejects a doctor, a login attempt returns `403` with `{"status": "rejected", "can_resubmit": true}`. The app shows a button **"إعادة تسجيل البيانات"**.
- The button opens a form with the registration fields (names, dob, specialization, phone, photo, ID card, certificate, optional clinic link and bio) plus the doctor's **email and password** (to prove who they are). It sends `POST /api/auth/doctor/resubmit` (multipart).
- On success the account becomes `pending` again (old files are deleted), and it appears in the admin's pending list for a new review. Only **rejected** doctors can use it (others get `403`).

## Voice messages (tone-of-voice analysis)
- The patient records a voice message and sends it to `POST /api/patient/chat/voice` (field `audio`: mp3/wav/m4a/aac/ogg/webm/3gp/amr, up to 10 MB).
- The backend does **not** analyze sound itself: it forwards the recording to the AI team's service and saves what comes back in `chat_messages`:
  `message` = what was said (the transcript), `reply`, `is_danger`, `emotion`, `emotion_score`, and the recording (private disk).
- `emotion` is one of `happy`, `sad`, `angry`, `anxious`, `neutral` (anything else is stored as null). `emotion_score` is the model's confidence from 0 to 1.
- Recordings are private health data: they are stored on the private disk and only the patient who sent one can play it back (`GET /api/patient/chat/{id}/audio`).
- If the AI service is down the recording and a placeholder message are still saved (`ai_available: false`, no emotion).
- The result is an estimate from a model, not a diagnosis: show it to the patient with wording like "it sounded like...".
- Database: run `php artisan migrate` (a new migration adds `audio_path`, `emotion`, `emotion_score` to `chat_messages`, so nothing is deleted).

## Chat contract with the AI team
`POST {CHATBOT_URL}/chat` with `{message, history:[{role,content}]}` returns `{reply, is_danger}`.
For voice: `POST {CHATBOT_URL}/voice` (multipart) with the file in `audio` and `history` as a JSON string,
returning `{transcript, reply, is_danger, emotion, emotion_score}`.
The reply and `is_danger` are saved in `chat_messages`; if the AI is down the message is still saved
and the patient gets a fallback reply.

This pack was written without running PHP: run `php artisan migrate:fresh --seed` and `php artisan test` first.
