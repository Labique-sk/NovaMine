# NovaMine Render Deployment Guide

## Create the service

1. Open the [Render Dashboard](https://dashboard.render.com/).
2. Choose **New → Blueprint** and connect `Labique-sk/NovaMine`.
3. Review the PostgreSQL database and web service in `render.yaml`, then apply.

The blueprint generates an `APP_KEY` for a new service. Keep it stable after deployment because changing it invalidates sessions and encrypted data. Set `APP_URL` to your deployed URL and confirm `APP_DEBUG=false`.

## Configure live sourcing

Add `TAVILY_API_KEY` in the Render web service's Environment settings. The blueprint declares a secret input for new services. Render does not prompt for a new `sync: false` value when updating an existing Blueprint, so existing services need this key added manually. `TAVILY_SEARCH_RESULT_LIMIT` defaults to 10. Brave Search is an optional alternate provider; set `BRAVE_SEARCH_API_KEY` if you already have one.

GitHub public API discovery is available for technical roles. Set `GITHUB_TOKEN` in the service environment if you need higher API rate limits.

## Runtime and data

The blueprint creates PostgreSQL and links its connection variables to the web service. Deployment runs the Docker build, startup script, and database migrations. Startup applies migrations without dropping existing data; do not run `php artisan migrate:fresh` on a database with records to preserve.

The supplied blueprint uses `QUEUE_CONNECTION=sync`, so mining jobs run during the web request. For longer searches or higher volume, configure a persistent queue backend and a separate worker.

Candidate discovery produces source-backed leads, not verified applicants. Review each source before relying on details or contacting a person. Disable demo access before using real candidate data.

## Troubleshooting

- If live web searches return no results, confirm `TAVILY_API_KEY` (or the optional Brave key) is set and redeploy to refresh cached configuration.
- If the app cannot connect to PostgreSQL, confirm `novamine-db` is healthy and the `DB_*` variables are linked.
- If mining requests time out, move queue processing to a persistent backend and worker.

Back up PostgreSQL before schema changes. Candidate exports can provide an additional copy, but store them securely.
