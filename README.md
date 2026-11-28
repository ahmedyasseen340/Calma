# 🧠 Calma – Mental Health Support Platform

Graduation Project – SH.A. Academy

Calma is a mental health support platform available as a **mobile app** (patients) and a **web dashboard** (doctors & admins). It combines an AI chatbot that understands text and **voice messages** (including the emotion in the patient's tone of voice), licensed doctors, mood tracking, and a supportive community.

## ✨ Key Features

- Three roles (Patient / Doctor / Admin) with token-based authentication; every doctor is reviewed and approved by an admin before they can log in
- AI chatbot in Modern Standard & Egyptian Arabic, by **text or voice message**
- **Tone-of-voice analysis:** a voice message is transcribed, and the emotion in the voice is detected (happy, sad, angry, anxious, neutral)
- Crisis flag: the AI marks high-risk messages (`is_danger`) so the app can react
- Mood tracker (1 to 5) with a **positive-challenge pop-up** when the mood is low (1 or 2), and weekly / monthly **mood reports** (averages, moving average, comparison with the previous month)
- Daily challenges (breathing, walking, reading)
- Educational content: articles collected automatically from RSS feeds every day and organized by category (anxiety, sleep, depression), plus calming audio
- A patient browses the **approved doctors** and contacts them by phone or clinic link
- Admin dashboard: statistics, review of each doctor's file (ID card and certificate), approve / reject / delete doctors, delete patients, moderate posts
- **Community awareness space:** approved doctors publish awareness posts and tips; patients read them and share their experiences **anonymously** (no name is ever shown); both can edit or delete their own posts; the admin moderates and can delete any post
- **Patient profile:** date of birth (must be older than 16), gender, marital status
- **Doctor profile:** photo, short bio (max 300 characters), specialization, phone and clinic link
- **Edit my profile:** a patient edits name, date of birth and marital status; a doctor edits name, phone, clinic link, bio, photo and specialization (a new specialization needs the admin's approval again). Email is not editable. Both can change their password
- **Rejected doctors** can register their data again with one button and be reviewed again
- **Delete my account:** patients and doctors can permanently delete their account and all their data (with the password)
- Forgot password in 3 screens: email, 6-digit code, new password + confirmation, then back to login

## 🏗️ Tech Stack

| Layer | Technology |
|---|---|
| Mobile App | Flutter |
| Web Dashboard | React, TailwindCSS |
| Backend | PHP / Laravel, Sanctum (API tokens) |
| Database | PostgreSQL, hosted on Supabase |
| AI Services | Python, FastAPI, Whisper (speech-to-text), Librosa (tone of voice) |
| LLM | OpenAI GPT-4o or Llama 3, LangChain |
| Email | Laravel Mail (SMTP) |

## 📁 Repository Structure

| Folder | Description |
|---|---|
| `backend/` | Laravel REST API |
| `mobile-app/` | Flutter patient app |
| `frontend/` | React dashboard for doctors/admins |
| `ai-services/` | Python AI microservices (chatbot, voice, crisis classifier) |
| `data-analysis/` | Mood data analysis and progress charts |
| `testing/` | Test cases, bug reports, and test results |
| `docs/` | Architecture, API endpoints, ERD, SQL schema |
| `chapters/` | Graduation project documentation chapters |
| `ui-ux-frontend-mobile/` | UI/UX designs for the web and mobile apps |
| `assets/` | Audio files |

## 🗄️ Database

PostgreSQL database hosted on Supabase, 14 tables:

| Table | Purpose |
|---|---|
| `users` | Every account: `first_name`, `last_name`, `email`, `password`, `role` (patient, doctor, admin) |
| `patients` | Patient data: `dob`, `gender` (male, female), `marital_status` (single, married) |
| `doctor_profiles` | Doctor data: `specialization`, `dob`, `phone`, `clinic_url`, `photo`, `bio`, ID card, certificate, `status` (pending, active, rejected) |
| `chat_messages` | Chat with the bot: `message`, `reply`, `is_danger`, and for voice messages `audio_path`, `emotion`, `emotion_score` |
| `mood_logs` | Mood score 1 to 5, optionally linked to a challenge |
| `challenges` | Daily challenges (breathing, walking, reading) |
| `patient_challenges` | Which patient completed which challenge, and when |
| `categories`, `articles`, `article_category` | Articles and their categories (many-to-many) |
| `audios` | Calming audio files |
| `posts` | Community posts written by patients and doctors |
| `password_reset_tokens`, `personal_access_tokens` | Laravel tables: reset codes and login tokens |

Relationships: a user has one patient profile or one doctor profile; a patient has many chat messages, mood logs and completed challenges; a user has many posts; an article has many categories.

The full schema is in [`docs/database/schema.sql`](docs/database/schema.sql).

## 🔌 API Overview

All paths start with `/api`. Send `Accept: application/json`, and `Authorization: Bearer <token>` after login.

| Area | Endpoints | Who |
|---|---|---|
| Auth | `POST /auth/register`, `POST /auth/login`, `GET /auth/me`, `POST /auth/logout` | Public / logged-in |
| Delete account | `POST /auth/delete-account` | Patient or doctor |
| Password | `POST /auth/forgot-password`, `POST /auth/verify-reset-code`, `POST /auth/reset-password`, `POST /auth/change-password` | Public / logged-in |
| Rejected doctor | `POST /auth/doctor/resubmit` | Public (rejected doctor) |
| Edit my profile | `POST /patient/profile`, `POST /doctor/profile` | Patient / Doctor |
| Admin dashboard | `GET /admin/stats`, `GET /admin/patients`, `DELETE /admin/patients/{id}` | Admin |
| Doctor review | `GET /admin/doctors`, `GET /admin/doctors/{id}` (+ `/documents/id_card`, `/documents/certificate`), `POST` or `PATCH` `/approve` and `/reject`, `DELETE /admin/doctors/{id}` | Admin |
| Moderation | `GET /admin/posts`, `DELETE /admin/posts/{id}` | Admin |
| Content management | `POST` / `DELETE` on `/admin/categories`, `/admin/articles`, `/admin/audios` | Admin |
| Content | `GET /categories`, `GET /articles`, `GET /articles/{id}`, `GET /audios`, `GET /audios/{id}` | Any logged-in user |
| Chat | `GET /patient/chat`, `POST /patient/chat`, `POST /patient/chat/voice`, `GET /patient/chat/{id}/audio` | Patient |
| Mood | `GET /patient/mood-logs`, `POST /patient/mood-logs` (answer has a `challenge` when the mood is 1 or 2), `GET /patient/mood-logs/report` | Patient |
| Doctors | `GET /doctors`, `GET /doctors/{id}` (approved doctors only) | Patient |
| Challenges | `GET /patient/challenges`, `POST /patient/challenges/{id}/complete` | Patient |
| Posts | `GET /posts?type=doctor|patient`, `GET /posts/{id}`, `POST /posts`, `POST /posts/{id}` (edit mine), `DELETE /posts/{id}` (delete mine). **Patient posts are anonymous.** | Patient or doctor |

### Business rules
- A patient must be **older than 16 years** at registration and when editing the date of birth.
- A doctor's bio is **at most 300 characters**. Photo, phone, ID card and certificate are required.
- A doctor cannot log in until an admin approves the account, and is refused by the API whenever the account is not `active` (rejected, or pending after changing the specialization).
- A patient post never reveals its author (`author: null`): the author is stored only for moderation and for editing or deleting their own post.
- A low mood (1 or 2) returns a random positive challenge.
- Approved doctors only appear in the patients' doctors list, with phone and clinic link; date of birth, status and documents are never shown.
- Users edit only their own data and their own posts.
- A patient cannot edit email or gender. A doctor cannot edit email, date of birth, ID card, certificate or status.
- A doctor who changes the **specialization** goes back to `pending` and is logged out until an admin approves again. Any other doctor edit keeps the account `active`.
- A **rejected** doctor can send all the data again (`pending` again, reviewed again).
- Changing the password requires the current password and logs out the other devices.
- Deleting the account requires the password, is permanent, and removes all the user's data including voice recordings and a doctor's documents.
- The reset code is 6 digits, valid for 15 minutes, works once, and 5 wrong guesses cancel it.
- The ID card, certificate and voice recordings are private files; only the doctor photo, article covers and audio files are public.

## 🤖 AI Services Contract

The backend calls the Python services (base URL in `CHATBOT_URL`). If the AI is down, the patient's message is still saved and a fallback reply is returned.

**`POST /chat`** (JSON)
```json
{ "message": "...", "history": [ { "role": "user", "content": "..." } ] }
```
returns `{ "reply": "...", "is_danger": false }`

**`POST /voice`** (multipart: `audio` file + `history` as a JSON string) returns
```json
{ "transcript": "...", "reply": "...", "is_danger": false, "emotion": "sad", "emotion_score": 0.87 }
```
`emotion` is one of `happy`, `sad`, `angry`, `anxious`, `neutral`. It is an estimate from the voice, not a medical diagnosis.

## 🚀 Getting Started

### Prerequisites
PHP 8.2+ (with the `pdo_pgsql` extension enabled), Composer, a Supabase project (PostgreSQL), Node.js 18+, Flutter SDK, Python 3.10+

### Backend
The whole team uses **one shared PostgreSQL database on Supabase**. Get the connection details from the team lead (never from a screenshot or the repo) and put them in `backend/.env` (see `.env.example`), then:
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```
Run the tests with `php artisan test`.

**Rules for the shared database**
- Only **one person** runs `php artisan migrate` (when the schema changes). Everybody else just runs `php artisan serve`.
- **Never** run `php artisan migrate:fresh` on the shared database: it deletes everyone's data.
- Never commit `.env` or paste the database password in chat or screenshots.
- The security script [`docs/database/supabase-security.sql`](docs/database/supabase-security.sql) must be run once in the Supabase SQL Editor.
- Uploaded files (voice recordings, doctor documents, photos) are stored on the machine that runs the backend until the backend is deployed on a server with shared file storage.

### Frontend (React)
```bash
cd frontend
npm install
npm run dev
```

### Mobile App
```bash
cd mobile-app
flutter pub get
flutter run
```

### AI Services
```bash
cd ai-services
python -m venv venv
source venv/bin/activate   # Windows: venv\Scripts\activate
pip install -r requirements.txt
# Each service (chatbot, voice, crisis-classifier) has its own
# entry point - see the README inside each folder.
```

## ☁️ Deployment

The API runs on a server (Render, Docker) with the database on Supabase PostgreSQL and the files on Supabase Storage, so the web and mobile apps use one stable address. Step by step: [`docs/deployment.md`](docs/deployment.md) (`render.yaml` is the Render blueprint).

## 🔐 Environment Variables

Copy `.env.example` to `.env` and fill in: database credentials, admin account, AI service URL and key, OpenAI key, mail settings. **Never commit real keys.**

## 🗂️ Detailed Project Structure

```
Calma/
├── docs/                              # Technical documentation
│   ├── architecture.md
│   ├── api-endpoints.md
│   └── database/
│       └── schema.sql                 # Database schema
├── backend/                           # Laravel REST API
│   ├── app/
│   │   ├── Http/Controllers/          # Auth, Admin, Community, Content, Patient, Profile
│   │   ├── Http/Middleware/
│   │   ├── Mail/
│   │   ├── Models/
│   │   └── Services/
│   ├── config/
│   ├── database/                      # migrations, seeders, factories
│   ├── routes/
│   │   └── api.php                    # API endpoints
│   └── tests/
├── frontend/                          # React + TailwindCSS web dashboard
├── mobile-app/                        # Flutter patient application
├── ai-services/                       # Python AI microservices
│   ├── chatbot/                       # LLM chatbot
│   ├── voice/                         # Speech-to-text + tone analysis (Whisper/Librosa)
│   ├── crisis-classifier/             # High-risk message detection
│   └── requirements.txt
├── data-analysis/                     # Mood data analysis & progress charts
├── testing/                           # Test cases & bug reports
├── chapters/                          # Graduation project documentation chapters
├── ui-ux-frontend-mobile/             # UI/UX designs
├── assets/                            # Audio files
├── .env.example                       # Environment variables template
└── README.md                          # Main project documentation
```

## 👥 Team

1. Ahmed Yasseen Ibrahim Mohamed
2. Alaa Abdelrahman Muhamed Abdelrahman
3. Arwa Nasr Fathy Mohmamed
4. Kareem Shreef Mahmoud
5. Mahmoud Ateya Tolba
6. Mennatullah Ahmed Mohamed Khater
7. Mariem Tamer Ibrahim El naggar
8. Ahmed Salama El sayed Abdel Khalik

## ⚠️ Disclaimer

Calma is an academic project and does not replace professional medical care. In an emergency, contact your local emergency number or mental health hotline.
