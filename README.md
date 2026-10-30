# NovaMine 2.0

NovaMine is a multi-industry candidate sourcing and recruitment intelligence workspace. It discovers public professional profile leads, stores source evidence, normalizes records, validates available fields, removes duplicates, calculates preliminary match scores, and supports candidate management, shortlists, analytics, imports, and exports.

> Search results are leads, not verified applicants. Review source evidence and confirm candidate details before relying on them or contacting anyone.

## Technology stack

- PHP 8.3+
- Laravel 13
- SQLite for local development; PostgreSQL for hosted deployments
- Blade, vanilla JavaScript, Tailwind CSS, Vite
- Laravel database queue for local background processing
- Docker and Render deployment files

## Requirements

- PHP 8.3+ with Laravel-required extensions (`pdo`, `pdo_sqlite` or `pdo_pgsql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`; `gd` and `pcntl` where applicable)
- Composer 2
- Node.js and npm only if rebuilding frontend assets
- SQLite or PostgreSQL

## Quick start

### 1. Extract and enter the project

```bash
unzip NovaMine-2.0.zip
cd NovaMine-2.0
```

Use the actual extracted folder name if it differs.

### 2. Configure environment

A private `.env` file is not included in this archive. Create one from the safe template:

```bash
cp .env.example .env
```

Generate a fresh application key:

```bash
php artisan key:generate
```

The supplied `.env.example` is configured for SQLite and Laravel's database-backed sessions, cache, and queue. The local SQLite path defaults to `database/database.sqlite`.

### 3. Install dependencies

```bash
composer install
```

Prebuilt frontend assets are included. If you change frontend source files, rebuild them with:

```bash
npm install
npm run build
```

### 4. Prepare the database

```bash
mkdir -p database
touch database/database.sqlite
php artisan migrate
```

For demo data, review the seeders and then run:

```bash
php artisan db:seed
```

Demo accounts/data are for local testing only. Disable demo access before using the app with real candidate information.

**Data safety:** `php artisan migrate` applies pending migrations. Do not run `php artisan migrate:fresh` against a database containing records you need to preserve. Back up production databases before migrations.

### 5. Configure live public web discovery

NovaMine's general-purpose public web discovery uses the Brave Search API.

1. Create a Brave Search API subscription at [Brave Search API](https://brave.com/search/api/).
2. Add your key to `.env`:

```dotenv
BRAVE_SEARCH_API_KEY=your_brave_search_api_key
BRAVE_SEARCH_RESULT_LIMIT=10
```

3. Clear cached configuration:

```bash
php artisan config:clear
```

4. Start a search in NovaMine. Enter a job role; industry, location, experience, and skills are optional filters.

Without `BRAVE_SEARCH_API_KEY`, the Brave adapter returns no live web results. GitHub discovery can still return technical profiles.

### 6. Start the app and queue worker

Terminal 1:

```bash
php artisan serve
```

Open http://127.0.0.1:8000.

Terminal 2:

```bash
php artisan queue:work --tries=3 --timeout=120
```

Mining jobs are dispatched through Laravel's configured queue. The local `.env.example` uses the database queue. The jobs-table migration is included.

For production, configure a persistent queue backend and a separate worker process. If `QUEUE_CONNECTION=sync`, jobs run synchronously inside the web request.

## Candidate discovery sources

### Brave Web Search

- Searches public web results for professional profiles, portfolios, resumes, CVs, and biography pages across job families and industries.
- Stores the result title, public URL, source name, and search-result excerpt.
- Adds a requested skill only when that term appears in the result title or excerpt.
- Excludes selected professional-network and job-board domains from this adapter.

### GitHub Public API

- Used as an additional source for technical roles.
- Reads public GitHub profile fields and programming languages observed in public repositories.
- A GitHub profile does not confirm a person's job title, employment history, or availability.

Optionally configure a GitHub token to improve API rate limits:

```dotenv
GITHUB_TOKEN=your_github_token
```

### User Provided URL

The current adapter checks whether a supplied public URL is reachable. It does not extract a full profile from that URL.

### Demo dataset

Synthetic demo candidates are disabled by default. They are only returned when `include_demo=true` is explicitly passed to the mining API. Demo data must never be mistaken for real people.

## What NovaMine collects today

The Brave integration is a discovery-first implementation. Search-engine results may provide a profile title, URL, and excerpt, but frequently do not expose a complete profile. NovaMine does not invent missing names, email addresses, phone numbers, employers, locations, education, or years of experience.

If a search result does not identify a person clearly from its title, NovaMine skips it rather than inventing an identity. The source excerpt is retained on saved candidate records so recruiters can review the evidence.

The current integration does not bypass logins, paywalls, CAPTCHAs, robots restrictions, or anti-bot controls. It does not scrape LinkedIn, Naukri, Indeed, or Glassdoor. Use official APIs or obtain permission before integrating additional sites.

Search results can be stale, incomplete, or about someone other than the intended candidate. A reachable URL is not proof of identity, employment, or consent. Review all leads and comply with applicable privacy, data-protection, retention, and opt-out requirements.

## Docker

Build:

```bash
docker build -t novamine:latest .
```

Run:

```bash
docker run --rm -p 8000:8080 --name novamine-app novamine:latest
```

Supply secrets through environment variables or the hosting platform's secret manager. Do not bake `.env` or API credentials into a Docker image.

## Render deployment

The repository includes `render.yaml`, `Dockerfile`, `start.sh`, `worker.sh`, and deployment notes.

Before deploying:

1. Configure a fresh `APP_KEY` in Render (the blueprint uses a generated value).
2. Configure the production database and `APP_URL`.
3. Set `APP_DEBUG=false`.
4. Add `BRAVE_SEARCH_API_KEY` and optionally `GITHUB_TOKEN` as private environment variables.
5. Keep candidate data and exports protected; use persistent storage where required.
6. Review queue behaviour. The supplied Render blueprint currently uses `QUEUE_CONNECTION=sync`; that means queued work runs synchronously unless you configure a separate worker and persistent queue backend.
7. Back up the database before applying migrations.

The startup script runs migrations and seeding. Inspect the seeders and startup process before connecting it to a database containing valuable production records.

## Tests and useful commands

```bash
# Run automated tests
php artisan test

# Clear cached config/routes/views
php artisan optimize:clear

# Run a database queue worker
php artisan queue:work --tries=3 --timeout=120

# Rebuild frontend assets
npm install
npm run build
```

## Security checklist

- Keep `.env` private and out of Git.
- Use a unique `APP_KEY` per environment.
- Store search API keys and OAuth secrets in environment variables.
- Set `APP_DEBUG=false` in production and use HTTPS.
- Restrict access to candidate personal data and exports.
- Disable demo accounts before using real candidate data.
- Define retention and deletion processes for sourced candidate records.
- Back up the database before upgrades.

## License

Review the repository's `LICENSE` file before redistribution or deployment.
