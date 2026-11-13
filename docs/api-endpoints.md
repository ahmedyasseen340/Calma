# Calma API endpoints

REST API of the Laravel backend (41 endpoints). The machine-readable version is [`api/openapi.yaml`](api/openapi.yaml)
(open it on https://editor.swagger.io) and a ready Postman collection is in [`api/psych-api.postman_collection.json`](api/psych-api.postman_collection.json).

## Conventions
- **Base URL (local):** `http://localhost:8000`, every path starts with `/api`.
- **Headers on every request:** `Accept: application/json`. After login also `Authorization: Bearer <token>`.
- **Body:** JSON, except where a file is uploaded (`multipart/form-data`): doctor registration, doctor profile photo, articles, audios and voice messages.
- **Updates use `POST`** (not PUT), because PHP does not read file uploads sent with PUT.
- **Roles:** `patient`, `doctor`, `admin`. A doctor can log in only after an admin approves the account.
- **Errors:** `401` not logged in, `403` wrong role (or doctor not approved yet), `404` not found / not yours, `422` validation (`{"message": "...", "errors": {"field": ["..."]}}`), `429` too many requests.

## Authentication

| Method | URL | Who | What it does |
|---|---|---|---|
| POST | `/api/auth/change-password` | Any logged-in user | Change my password |
| POST | `/api/auth/doctor/resubmit` | Public (a rejected doctor) | Rejected doctor: register the data again |
| POST | `/api/auth/forgot-password` | Public | Forgot password, screen 1: email the 6-digit code |
| POST | `/api/auth/login` | Public | Login |
| POST | `/api/auth/logout` | Any logged-in user | Logout (revokes the current token) |
| GET | `/api/auth/me` | Any logged-in user | Current user |
| POST | `/api/auth/register` | Public | Register a patient or a doctor |
| POST | `/api/auth/reset-password` | Public | Forgot password, screen 3: set the new password |
| POST | `/api/auth/verify-reset-code` | Public | Forgot password, screen 2: confirm the code |

## Admin: doctor approval

| Method | URL | Who | What it does |
|---|---|---|---|
| GET | `/api/admin/doctors` | Admin | List doctor profiles |
| POST | `/api/admin/doctors/{doctor}/approve` | Admin | Approve a doctor (status=active) |
| POST | `/api/admin/doctors/{doctor}/reject` | Admin | Reject a doctor (status=rejected) |

## Admin: content management

| Method | URL | Who | What it does |
|---|---|---|---|
| POST | `/api/admin/articles` | Admin | Create article |
| POST | `/api/admin/articles/{article}` | Admin | Update article (POST so uploads work) |
| DELETE | `/api/admin/articles/{article}` | Admin | Delete article |
| POST | `/api/admin/audios` | Admin | Upload audio |
| POST | `/api/admin/audios/{audio}` | Admin | Update audio |
| DELETE | `/api/admin/audios/{audio}` | Admin | Delete audio |
| POST | `/api/admin/categories` | Admin | Create category |
| POST | `/api/admin/categories/{category}` | Admin | Update category |
| DELETE | `/api/admin/categories/{category}` | Admin | Delete category |

## Content (read only)

| Method | URL | Who | What it does |
|---|---|---|---|
| GET | `/api/articles` | Any logged-in user | List articles (paginated) |
| GET | `/api/articles/{article}` | Any logged-in user | Get article |
| GET | `/api/audios` | Any logged-in user | List audios (paginated) |
| GET | `/api/audios/{audio}` | Any logged-in user | Get audio |
| GET | `/api/categories` | Any logged-in user | List categories |

## Posts

| Method | URL | Who | What it does |
|---|---|---|---|
| GET | `/api/posts` | Patient or doctor | List posts (newest first, paginated) |
| POST | `/api/posts` | Patient or doctor | Create a post (patient or doctor) |
| GET | `/api/posts/{post}` | Patient or doctor | Get one post |
| POST | `/api/posts/{post}` | The post's author | Edit my post |
| DELETE | `/api/posts/{post}` | The post's author | Delete my post |

## Patient

| Method | URL | Who | What it does |
|---|---|---|---|
| GET | `/api/patient/challenges` | Patient | List challenges |
| POST | `/api/patient/challenges/{challenge}/complete` | Patient | Mark a challenge as completed |
| GET | `/api/patient/chat` | Patient | Chat history (newest first, paginated) |
| POST | `/api/patient/chat` | Patient | Send a message to the chatbot |
| POST | `/api/patient/chat/voice` | Patient | Send a voice message (tone-of-voice analysis) |
| GET | `/api/patient/chat/{message}/audio` | Patient | Play back one of my own recordings |
| GET | `/api/patient/mood-logs` | Patient | Mood logs (paginated) |
| POST | `/api/patient/mood-logs` | Patient | Log mood |
| POST | `/api/patient/profile` | Patient | Edit my own data (patient) |

## Doctor

| Method | URL | Who | What it does |
|---|---|---|---|
| POST | `/api/doctor/profile` | Doctor | Edit my own data (doctor) |

## Main request bodies

### Register a patient: `POST /api/auth/register` (JSON)
```json
{
  "first_name": "Ahmed", "last_name": "Ali",
  "email": "ahmed@example.com",
  "password": "password123", "password_confirmation": "password123",
  "role": "patient",
  "dob": "2000-05-20",
  "gender": "male",
  "marital_status": "single"
}
```
- `dob`: the patient must be **older than 16 years**, otherwise `422`.
- `gender`: `male` or `female`. `marital_status`: `single` or `married`.
- The response contains `user.patient` with a calculated `age`, and a `token`.

### Register a doctor: `POST /api/auth/register` (multipart/form-data)
| Field | Required | Notes |
|---|---|---|
| first_name, last_name, email, password, password_confirmation | yes | `role` = `doctor` |
| dob | yes | date in the past |
| specialization | yes | |
| phone | yes | 8 to 15 digits, may start with `+` |
| photo | yes | profile picture, image up to 4 MB (public) |
| id_card_img, certificate_img | yes | images up to 4 MB (private) |
| clinic_url | no | link to the clinic |
| bio | no | **max 300 characters** |

The response has no token: the status is `pending` until an admin approves. Login answers `403` with `status: pending` (or `status: rejected` + `can_resubmit: true`, see below).

### Edit my own data
Send **only the fields you want to change**. Fields that are not allowed are ignored. The answer contains the updated `user`.

| | Can edit | Cannot edit |
|---|---|---|
| **Patient:** `POST /api/patient/profile` | `first_name`, `last_name`, `dob` (still **older than 16**), `marital_status` | `email`, `gender` |
| **Doctor:** `POST /api/doctor/profile` (multipart when sending a photo) | `first_name`, `last_name`, `phone`, `clinic_url` (send `null` to clear), `bio` (**max 300 characters**), `photo`, `specialization` | `email`, `dob`, ID card, certificate, `status` |

- **Doctor + specialization:** if the specialization really changes, the account goes back to `pending`. The answer has `requires_approval: true`, the doctor is logged out, and cannot log in until an admin approves again. **Any other change keeps the doctor `active`.**
- A new photo replaces (and deletes) the old one.
- Change password: `POST /api/auth/change-password` with `{"current_password", "password", "password_confirmation"}`. The other devices are logged out.

### Rejected doctor: register the data again
1. The admin rejects a doctor. When that doctor tries to log in, the answer is `403` with `{"status": "rejected", "can_resubmit": true}`. The app shows a button **"إعادة تسجيل البيانات"**.
2. The button opens a form with the registration data (first and last name, dob, specialization, phone, photo, ID card, certificate, optional clinic link and bio) **plus the doctor's email and password** (to prove who they are).
3. `POST /api/auth/doctor/resubmit` (multipart/form-data) sends everything. The profile becomes `pending` again (old files are deleted) and shows up in the admin's pending list. Only a **rejected** doctor can use it, anyone else gets `403`.

### Chat: `POST /api/patient/chat` (JSON) and `POST /api/patient/chat/voice` (multipart)
```json
{ "message": "أنا حاسس بقلق النهارده" }
```
Voice: send the recording in the `audio` field (mp3, wav, m4a, aac, ogg, webm, 3gp, amr, up to 10 MB). Response:
```json
{
  "chat": {
    "id": 12, "message": "أنا حاسس إني تعبان قوي", "reply": "أنا معاك، احكيلي أكتر",
    "is_danger": false, "has_audio": true,
    "emotion": "sad", "emotion_score": 0.87, "created_at": "2026-11-01T20:15:00.000000Z"
  },
  "ai_available": true
}
```
`emotion` is one of `happy`, `sad`, `angry`, `anxious`, `neutral` (or `null`). It is an estimate from the tone of voice, not a diagnosis.

### Forgot password: 3 screens
| Screen | What the user does | Request | Answers |
|---|---|---|---|
| **1** | types the email and presses *Send code* | `POST /api/auth/forgot-password` `{"email"}` | `200` always (same answer whether or not the email exists). The app opens screen 2. |
| **2** | types the 6 digits and presses *Confirm* | `POST /api/auth/verify-reset-code` `{"email", "code"}` | wrong email or wrong/expired code: `422` with **"الإيميل أو الكود غير صحيح."** (the user stays on screen 2). Right code: `200 {"valid": true}` and the app opens screen 3 (keep the email and code in memory). |
| **3** | types the new password and its confirmation, presses *Save* | `POST /api/auth/reset-password` `{"email", "code", "password", "password_confirmation"}` | the two are different: `422` on `password` with **"كلمة المرور الجديدة وتأكيدها لازم يكونوا متطابقين."** Success: `200`, and the app opens the **login screen**. |

- The code is 6 digits, valid for 15 minutes, and 5 wrong guesses (on screen 2 or 3) cancel it.
- Verifying the code does **not** use it up; it is used up when the password is changed. After a reset, all old login tokens stop working.
- In development the email is written to `storage/logs/laravel.log` (`MAIL_MAILER=log`).

### Posts: `POST /api/posts`
```json
{ "content": "أنا حاسس بتحسن النهارده" }
```
Edit your post with `POST /api/posts/{id}` (`{"content": "..."}`) and delete it with `DELETE /api/posts/{id}`; only the author can (others get `403`).
Each post shows its author's `first_name`, `last_name`, `role`. For doctors it also shows `specialization`, `phone`, `clinic_url`, `bio` and `photo_url`.
