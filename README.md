# 🧠 Calma – Mental Health Support Platform

Graduation Project – SH.A. Academy

Calma is a mental health support platform available as a **mobile app** (patients) and a **web dashboard** (therapists & admins). It combines an AI chatbot, licensed doctors, and multimodal emotion analysis to make psychological support easier to access.

## ✨ Key Features

- Two-role system (Patient / Doctor) plus Admin, with token-based auth and admin approval for doctors
- AI chatbot in Modern Standard & Egyptian Arabic (PHQ-9 / GAD-7 based)
- **Voice messages:** the patient talks to the chatbot, and the system transcribes the speech and analyzes the **tone of voice** (happy, sad, angry, anxious, neutral)
- Confidential Private Venting mode (no data stored)
- Crisis Safety Net: detects high-risk statements, shows hotlines, alerts the doctor
- Facial emotion detection (DeepFace/OpenCV) and voice tone analysis (Whisper/Librosa)
- Multimodal Mood Index: `0.5 × Text + 0.3 × Vision + 0.2 × Voice`
- AI report handover + electronic prescription
- Prescription OCR + pill reminders + nearby pharmacy finder
- Online & in-person booking with Paymob/Fawry payments and scheduled reminders
- Mood tracker, daily challenges, weekly status summary
- **Community posts:** patients and doctors can write and read posts
- **Doctor profiles:** photo, short bio (max 300 characters), phone and clinic link
- **Patient profile:** date of birth (must be older than 16), gender, marital status
- Forgot password with a 6-digit code sent by email
- Interactive 3D brain model (Three.js), calming sounds, personalized articles

## ✅ Backend status

What the Laravel API already does today, and what is still planned.

| Area | Status |
|---|---|
| Register / login / logout, roles (patient, doctor, admin), Sanctum tokens | ✅ Done |
| Admin approves or rejects doctors | ✅ Done |
| Patient profile (age check > 16, gender, marital status) | ✅ Done |
| Doctor profile (phone, clinic link, photo, bio ≤ 300, ID card, certificate) | ✅ Done |
| Text chat with the AI service (`is_danger` flag saved) | ✅ Done (needs `ai-services`) |
| Voice messages with transcript + tone-of-voice emotion | ✅ Done (needs `ai-services/voice`) |
| Mood logs, daily challenges | ✅ Done |
| Articles, categories, audios | ✅ Done |
| Community posts (patients and doctors) | ✅ Done |
| Forgot / reset password by email code | ✅ Done |
| Private Venting mode, crisis hotlines + doctor alerts | ⏳ Planned |
| Multimodal Mood Index, weekly summaries | ⏳ Planned (`data-analysis/`) |
| AI report handover, electronic prescription, OCR, pill reminders | ⏳ Planned |
| Booking, payments (Paymob/Fawry), notifications (FCM), pharmacy finder | ⏳ Planned |

## 🗄️ Database

14 tables. Full SQL: [`docs/database/schema.sql`](docs/database/schema.sql) · Mapping: [`docs/database/Calma_schema_mapping.svg`](docs/database/Calma_schema_mapping.svg)

![Calma ERD](docs/database/erd_light_mode.png)

Dark version, SVG and PDF are in [`docs/database/`](docs/database/).

## 🏗️ Tech Stack

| Layer | Technology |
|---|---|
| Mobile App | Flutter |
| Web Dashboard | React, TailwindCSS |
| Backend | PHP / Laravel, Sanctum (API tokens), MySQL |
| AI Services | Python, FastAPI, DeepFace, OpenCV, Whisper, Librosa, EasyOCR |
| LLM | OpenAI GPT-4o or Llama 3, LangChain |
| Scheduling | Laravel Scheduler + Queues |
| 3D | Three.js (.glb) |
| Notifications | Firebase Cloud Messaging |
| Payments | Paymob / Fawry |
| Maps | Google Maps Places API |

## 📁 Repository Structure

| Folder | Description |
|---|---|
| `backend/` | Laravel REST API |
| `mobile-app/` | Flutter patient app |
| `frontend/` | React dashboard for doctors/admins |
| `ai-services/` | Python AI microservices |
| `data-analysis/` | Mood Index, weekly summaries, and progress charts |
| `testing/` | Test cases, bug reports, and test results |
| `docs/` | Architecture, API endpoints, ERD, SQL schema, AI contract |
| `chapters/` | Graduation project documentation chapters |
| `ui-ux-frontend-mobile/` | UI/UX designs for the web and mobile apps |
| `assets/` | 3D models and audio files |

## 🚀 Getting Started

### Prerequisites
PHP 8.2+, Composer, MySQL (XAMPP is fine), Node.js 18+, Flutter SDK, Python 3.10+

### Backend
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
php artisan test
```
Create the database `calma` first. Details and first-time setup: [`backend/README.md`](backend/README.md).

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
# Each service (chatbot, vision, voice, ocr, crisis-classifier) has its own
# entry point - see the README inside each folder.
```
The backend talks to it as described in [`docs/ai-services-contract.md`](docs/ai-services-contract.md).

## 🔐 Environment Variables

Copy `.env.example` to `.env` and fill in: DB credentials, OpenAI key, Firebase, Paymob/Fawry, Google Maps, mail. **Never commit real keys.**

## 📚 Documentation

- API: [`docs/api-endpoints.md`](docs/api-endpoints.md), OpenAPI and Postman in [`docs/api/`](docs/api/)
- Database: [`docs/database/`](docs/database/)
- AI services contract: [`docs/ai-services-contract.md`](docs/ai-services-contract.md)
- Changes: [`docs/CHANGELOG.md`](docs/CHANGELOG.md)

## 🗂️ Detailed Project Structure

```
Calma/
├── docs/                              # Technical documentation
│   ├── architecture.md
│   ├── api-endpoints.md
│   ├── ai-services-contract.md
│   ├── CHANGELOG.md
│   ├── api/                           # openapi.yaml + Postman collection
│   └── database/
│       ├── schema.sql                 # Database schema
│       ├── Calma_schema_mapping.svg   # Schema mapping
│       └── erd_*.png / .svg / .pdf    # ERD (light and dark)
├── backend/                           # Laravel REST API
│   ├── app/
│   │   ├── Http/Controllers/          # Auth, Admin, Community, Content, Patient
│   │   ├── Http/Middleware/
│   │   ├── Mail/
│   │   ├── Models/
│   │   └── Services/
│   ├── config/
│   ├── database/                      # migrations, seeders, factories
│   ├── resources/views/emails/
│   ├── routes/
│   │   └── api.php                    # API endpoints
│   └── tests/
├── frontend/                          # React + TailwindCSS web dashboard
├── mobile-app/                        # Flutter patient application
├── ai-services/                       # Python AI microservices
│   ├── chatbot/                       # LLM (PHQ-9 / GAD-7)
│   ├── vision/                        # Facial emotion detection (DeepFace/OpenCV)
│   ├── voice/                         # Speech-to-text + tone analysis (Whisper/Librosa)
│   ├── ocr/                           # Prescription OCR
│   ├── crisis-classifier/             # Crisis Safety Net
│   └── requirements.txt
├── data-analysis/                     # Mood Index & weekly progress summaries
├── testing/                           # Test cases & bug reports
├── chapters/                          # Graduation project documentation chapters
├── ui-ux-frontend-mobile/             # UI/UX designs
├── assets/                            # 3D brain models (.glb) & audio files
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
