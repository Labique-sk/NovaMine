# NovaMine

NovaMine is a candidate sourcing and recruitment workspace for discovering, organizing, and reviewing public professional profile leads.

**Live website:** [novamine-web.onrender.com](https://novamine-web.onrender.com/)

Search results are leads, not verified applicants. Review the source evidence and confirm details before relying on them or contacting anyone.

## What it does

- Searches public web results and public GitHub profiles for role-relevant leads.
- Optionally enriches candidate searches through People Data Labs when explicitly enabled and configured.
- Normalizes and deduplicates candidate records, retains source evidence, and calculates preliminary match scores.
- Provides candidate management, shortlists, mining history, analytics, CSV imports, and exports.
- Supports email/password accounts and optional Google and LinkedIn sign-in.
- Includes an administration view for user accounts and account status.

Search sources may return incomplete or stale information. NovaMine does not invent missing personal details or verify a person's identity, employment, availability, or consent. It does not bypass logins, paywalls, CAPTCHAs, robots restrictions, or anti-bot controls. Use official APIs and follow applicable privacy, data protection, retention, and opt-out requirements.

## Technology

- PHP 8.3+ and Laravel 13
- Blade, vanilla JavaScript, Tailwind CSS, and Vite
- SQLite for local development; PostgreSQL for hosted deployments
- Laravel database queue locally; Docker and Render deployment configuration

## Run locally

### Requirements

- PHP 8.3+ with Laravel-required extensions (`pdo`, `pdo_sqlite` or `pdo_pgsql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`; `gd` and `pcntl` where applicable)
- Composer 2
- SQLite or PostgreSQL
- Node.js and npm only when rebuilding frontend assets

### Install

```bash
git clone https://github.com/Labique-sk/NovaMine.git
cd NovaMine
composer install
cp .env.example .env
php artisan key:generate
mkdir -p database
touch database/database.sqlite
php artisan migrate
```

The example environment is set up for local SQLite and database-backed sessions, cache, and queues. Never commit `.env` or put API credentials in source control.

Prebuilt frontend assets are included. If you edit `resources/css/app.css` or `resources/js/app.js`, install frontend dependencies and rebuild:

```bash
npm install
npm run build
```

Start the web app:

```bash
php artisan serve
```

Then open [http://127.0.0.1:8000](http://127.0.0.1:8000).

For the local database queue, run a worker in a second terminal:

```bash
php artisan queue:work --tries=3 --timeout=120
```

If `QUEUE_CONNECTION=sync`, jobs run during the web request instead of being processed by a worker. For production, configure a persistent queue and worker for longer searches.

## Configure integrations

Set credentials as environment variables in `.env` locally and in the hosting provider's secret settings in production. Clear cached config after changing local settings with `php artisan config:clear`.

| Environment variable | Purpose |
| --- | --- |
| `TAVILY_API_KEY` | Public web search provider |
| `GITHUB_TOKEN` | Optional higher GitHub API rate limits; GitHub discovery supports technical roles |
| `PDL_ENABLED` and `PDL_API_KEY` | Explicitly enable the credit-based People Data Labs source |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_AUTH_REDIRECT_URI` | Optional Google sign-in |
| `LINKEDIN_CLIENT_ID`, `LINKEDIN_CLIENT_SECRET`, `LINKEDIN_REDIRECT_URI` | Optional LinkedIn sign-in |
| `BRAVE_SEARCH_API_KEY` | Optional alternate web search provider |

Configure OAuth redirect URLs in each provider console to exactly match the callback URLs configured for your deployment. Never share client secrets or API keys publicly.

The Tavily adapter searches public web results and stores source titles, URLs, and excerpts. GitHub results expose public profile information and public-repository languages, not verified employment history. People Data Labs is disabled unless `PDL_ENABLED=true` and a valid API key is configured; review its pricing and usage limits before enabling it. Synthetic demo candidates are disabled by default and must not be confused with real people.

## Deploy to Render

This repository includes `render.yaml`, a `Dockerfile`, and startup/worker scripts. See [DEPLOYMENT.md](DEPLOYMENT.md) for the deployment workflow and troubleshooting.

Before production use:

1. Set a unique, stable `APP_KEY`, `APP_URL`, and `APP_DEBUG=false`.
2. Add only the provider credentials you intend to use through Render's private environment settings.
3. Confirm PostgreSQL and queue settings, and use persistent storage/services appropriate for your data.
4. Back up the database before schema changes. Startup runs migrations; do not run `php artisan migrate:fresh` on a database with records you need.
5. Review demo access and protect candidate records and exports.

## Repository layout

```text
app/          Laravel controllers, models, jobs, policies, and services
config/       Application and integration configuration
database/     Migrations, factories, and seeders
public/       Web entry point and built frontend assets
resources/    Frontend source and Blade views
routes/       Web and API routes
tests/        Feature and unit tests
```

## Useful commands

```bash
php artisan test
php artisan optimize:clear
php artisan queue:work --tries=3 --timeout=120
npm run build
```

## License

No `LICENSE` file is currently included. Contact the repository owner for reuse or redistribution terms.
