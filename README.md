# 🧠 Calma – Mental Health Support Platform

Graduation Project – SH.A. Academy

Calma is a mental health support platform available as a **mobile app** (patients) and a **web dashboard** (doctors & admins). It combines an AI chatbot that understands text and **voice messages** (including the emotion in the patient's tone of voice), licensed doctors, mood tracking, and a supportive community.

## ✨ Key Features

- Three roles (Patient / Doctor / Admin) with token-based authentication; every doctor is reviewed and approved by an admin before they can log in
- AI chatbot in Modern Standard & Egyptian Arabic, by **text or voice message**
- **Tone-of-voice analysis:** a voice message is transcribed, and the emotion in the voice is detected (happy, sad, angry, anxious, neutral)
- Crisis flag: the AI marks high-risk messages (`is_danger`) so the app can react
- Mood tracker (1 to 5) and daily challenges (breathing, walking, reading)
- Educational content: articles organized by category (anxiety, sleep, depression) and calming audio
- **Community posts:** patients and doctors can write and read posts
- **Patient profile:** date of birth (must be older than 16), gender, marital status
- **Doctor profile:** photo, short bio (max 300 characters), specialization, phone and clinic link
- Forgot password with a 6-digit code sent by email

## 🏗️ Tech Stack

| Layer | Technology |
|---|---|
| Mobile App | Flutter |
| Web Dashboard | React, TailwindCSS |
| Backend | PHP / Laravel, Sanctum (API tokens), MySQL |
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

MySQL database `calma`, 14 tables:

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

## 🔌 API Overview

All paths start with `/api`. Send `Accept: application/json`, and `Authorization: Bearer <token>` after login.

| Area | Endpoints | Who |
|---|---|---|
| Auth | `POST /auth/register`, `POST /auth/login`, `GET /auth/me`, `POST /auth/logout` | Public / logged-in |
| Password | `POST /auth/forgot-password`, `POST /auth/reset-password` | Public |
| Doctor approval | `GET /admin/doctors`, `POST /admin/doctors/{id}/approve`, `POST /admin/doctors/{id}/reject` | Admin |
| Content management | `POST` / `DELETE` on `/admin/categories`, `/admin/articles`, `/admin/audios` | Admin |
| Content | `GET /categories`, `GET /articles`, `GET /articles/{id}`, `GET /audios`, `GET /audios/{id}` | Any logged-in user |
| Chat | `GET /patient/chat`, `POST /patient/chat`, `POST /patient/chat/voice`, `GET /patient/chat/{id}/audio` | Patient |
| Mood | `GET /patient/mood-logs`, `POST /patient/mood-logs` | Patient |
| Challenges | `GET /patient/challenges`, `POST /patient/challenges/{id}/complete` | Patient |
| Posts | `GET /posts`, `GET /posts/{id}`, `POST /posts` | Patient or doctor |

### Business rules
- A patient must be **older than 16 years** at registration.
- A doctor's bio is **at most 300 characters**. Photo, phone, ID card and certificate are required.
- A doctor cannot log in until an admin approves the account.
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
PHP 8.2+, Composer, MySQL (XAMPP is fine), Node.js 18+, Flutter SDK, Python 3.10+

### Backend
Create an empty MySQL database named `calma` first (for example from phpMyAdmin), then:
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
│   │   ├── Http/Controllers/          # Auth, Admin, Community, Content, Patient
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
