# Deploying Calma: a stable address for the apps + shared file storage

**Goal:** the web dashboard and the Flutter app talk to **one online API** (not to somebody's laptop), and uploaded files
(voice recordings, doctor documents, photos, article covers, audios) live in one shared place.

| Part | Service | Why |
|---|---|---|
| Database | Supabase PostgreSQL (already running) | shared by the whole team |
| Files | **Supabase Storage** (S3 API) | the same project, no new account |
| API server | **Render** (Docker, free plan) | gives an `https://...onrender.com` address |
| "Forgot password" e-mail | **Brevo** (HTTPS API) | Render's free plan blocks SMTP ports |
| Daily article import | GitHub Actions schedule | the free plan has no cron |

> Free plans change. Before you start, check Render's page *Deploy for Free* and Supabase's pricing page.

## What the free plans mean (read once)
- **Render free web service:** it **goes to sleep after 15 minutes without traffic**; the next request wakes it up and takes up to about a minute. It has 750 free hours a month, and it can restart at any time (so nothing important may be stored on its disk: that is why files go to Supabase Storage).
- **Before the discussion / a demo:** open the API address a few minutes earlier, or let a free pinger (for example UptimeRobot) call `https://<your-api>/up` every 5 minutes.
- **Supabase free:** a project that is not used for about a week is paused; restore it from the dashboard before a demo.

---

## Step 1: Supabase Storage (10 min)
1. Supabase dashboard > **Storage** > **New bucket**.
   - `calma-private`: leave **Public bucket OFF**. (ID cards, certificates, voice recordings.)
   - `calma-public`: turn **Public bucket ON**. (Doctor photos, article covers, calming audios.)
2. Storage > **S3 Configuration** (or *Settings > S3 Connection*): turn the S3 protocol on and copy the **Endpoint** and the **Region**. The endpoint looks like `https://<project-ref>.storage.supabase.co/storage/v1/s3`.
3. Same page: **New access key**. Copy the **Access key ID** and the **Secret access key** now (the secret is shown once).
4. The public address of the public bucket is `https://<project-ref>.supabase.co/storage/v1/object/public/calma-public`.

These keys can read and write every bucket: treat them like the database password (never in Git, never in a screenshot).

## Step 2: Brevo for the reset-code e-mail (10 min)
1. Create a free account on brevo.com (300 e-mails a day, no card).
2. **Senders, Domains & Dedicated IPs > Senders > Add a sender**: use a real mailbox of the team and confirm the e-mail Brevo sends you. This address becomes `MAIL_FROM_ADDRESS`.
3. **SMTP & API > API Keys > Generate a new API key**. This is `BREVO_API_KEY`.

Without `BREVO_API_KEY` the backend uses the normal Laravel mailer (`MAIL_MAILER`), which is fine on a laptop (`log` writes the e-mail to `storage/logs/laravel.log`).

## Step 3: prepare the backend code (once, on the laptop that has the project)
```powershell
cd backend
composer require league/flysystem-aws-s3-v3 "^3.0" --with-all-dependencies
```
This adds the package that talks to the S3 API. **Commit `composer.json` and `composer.lock`** (the Docker build needs both).

Copy the new files into `backend/` (they are in the update package): `config/filesystems.php`, `config/mailapi.php`, `Dockerfile`, `.dockerignore`, `docker/`, and the new PHP files. At the **repository root** put `render.yaml` and `.github/`.

In `backend/bootstrap/app.php` make Laravel trust Render's proxy (otherwise generated links use `http://`):
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: '*');          // <- add this line
    $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
    ]);
})
```
Run `php artisan test` (the tests never touch the real buckets), then `git add .`, `git commit`, `git pull --rebase origin main`, `git push origin main`.

Generate the application key you will paste in Render:
```powershell
php artisan key:generate --show
```

## Step 4: create the server on Render (15 min)
1. render.com > sign up with GitHub > **New > Blueprint** > choose the `Calma` repository. Render reads `render.yaml`.
2. Render asks for the secret values. Use the table below. (Or create a **Web Service** by hand: *Docker*, root directory `backend`, health check path `/up`, and add the same variables.)
3. Wait for the first build (a few minutes). Open `https://<your-service>.onrender.com/up`: it must answer OK.
4. Set `APP_URL` to that exact `https://...onrender.com` address and redeploy once.

| Variable | Value |
|---|---|
| `APP_KEY` | the output of `php artisan key:generate --show` |
| `APP_URL` | `https://<your-service>.onrender.com` |
| `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD` | the Supabase *Session pooler* values (the same as in your local `.env`) |
| `SUPABASE_S3_ENDPOINT` | the endpoint from Step 1 |
| `SUPABASE_S3_KEY`, `SUPABASE_S3_SECRET` | the access key from Step 1 |
| `SUPABASE_S3_REGION` | the region from Step 1 (for example `eu-west-1`) |
| `SUPABASE_PUBLIC_URL` | `https://<project-ref>.supabase.co/storage/v1/object/public/calma-public` |
| `BREVO_API_KEY`, `MAIL_FROM_ADDRESS` | from Step 2 |
| `CHATBOT_URL`, `CHATBOT_API_KEY` | the AI team's service (it must be online too; empty = built-in stub reply) |
| `ARTICLE_FEEDS` | RSS addresses separated by commas (choose them with the team) |
| `ARTICLE_CRON_TOKEN` | a long random text (see Step 5) |

The database already has its tables (you ran the migrations from your laptop). If you add a migration later, run `php artisan migrate` **from one laptop**, or set `RUN_MIGRATIONS=true` on Render for one deploy and then back to `false`.

## Step 5: daily article import (5 min)
1. Choose a long random `ARTICLE_CRON_TOKEN` and put it on Render.
2. GitHub > the repository > **Settings > Secrets and variables > Actions**: add `API_URL` (`https://<your-service>.onrender.com`, no slash at the end) and `ARTICLE_CRON_TOKEN` (the same value).
3. GitHub > **Actions > Fetch articles daily > Run workflow** to try it now. It then runs every day at 06:00 UTC.

## Step 6: check that everything works
1. `GET https://<api>/up` is OK.
2. Register a **doctor** (Postman, multipart, with the photo, ID card and certificate) on the live address. In Supabase > Storage, the photo must be in `calma-public/doctors/photos` and the two documents in `calma-private/doctors/...`.
3. Open `photo_url` from the answer in a browser: the picture shows.
4. As admin, open the doctor's file and the ID card (`/api/admin/doctors/{id}/documents/id_card`): it shows (private bucket, only through the admin).
5. Send a voice message as a patient and play it back (`GET /api/patient/chat/{id}/audio`).
6. "Forgot password" with a real e-mail: the code arrives (check the spam folder the first time).
7. Give the apps the base address `https://<your-service>.onrender.com/api`.

## Troubleshooting
| Problem | Likely cause |
|---|---|
| Build fails at `composer install` | `composer.lock` is not committed, or the `league/flysystem-aws-s3-v3` package is missing from it |
| `No application encryption key` | `APP_KEY` is not set on Render |
| `could not find driver` / database connection error | `DB_*` values, or `DB_SSLMODE=require` is missing |
| `Class "Aws\S3\S3Client" not found` | Step 3 `composer require` was not committed |
| Upload fails with an S3 / 403 error | wrong key or secret, wrong bucket name, wrong region or endpoint, or `SUPABASE_PUBLIC_URL` points to another bucket |
| Links in answers start with `http://` | `trustProxies` line is missing or `APP_URL` is wrong |
| First request after a pause is slow | normal on the free plan (the service was asleep) |
| Reset code never arrives | `BREVO_API_KEY` missing, sender not verified in Brevo, or look in Render's logs for the Brevo error |

## Security checklist
- `.env`, the Supabase password, the S3 secret, the Brevo key and `ARTICLE_CRON_TOKEN` exist **only** in your `.env` and in Render's dashboard.
- `calma-private` stays **private**. The ID cards and the voice recordings are only served by the API to the right user.
- Row Level Security stays ON for the database tables (`docs/database/supabase-security.sql`).
- Files uploaded before this change stay on the laptop that stored them. Register the test accounts again after the switch.
