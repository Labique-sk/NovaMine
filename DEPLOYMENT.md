# NovaMine Render Deployment Guide

## Files Changed for Deployment

1. **Created**: `render.yaml` - Render service configuration
2. **Modified**: `.gitignore` - Removed `/public/build` to allow commit of built assets
3. **Modified**: `database/seeders/DatabaseSeeder.php` - Made seeding idempotent to prevent duplicate users

## Render Setup Steps

### 1. Create New Web Service on Render

1. Go to [Render Dashboard](https://dashboard.render.com/)
2. Click "New +" → "Web Service"
3. Connect your GitHub repository: `Labique-sk/NovaMine`
4. Render will automatically detect the `render.yaml` file
5. Review the detected services and click "Apply"

### 2. Generate APP_KEY Locally (IMPORTANT)

Before deploying, generate a persistent APP_KEY:
```bash
php artisan key:generate
```

Copy the generated APP_KEY from your `.env` file. This key will be used in both web service and queue worker.

### 3. Set APP_KEY in Render Dashboard (CRITICAL)

After generating your APP_KEY locally:

1. Go to your web service in Render dashboard
2. Navigate to "Environment" tab
3. Add environment variable: `APP_KEY` with the value you generated
4. Do the same for the queue worker service (use the exact same value)

**IMPORTANT**: Never regenerate APP_KEY after initial deployment. This will break all existing sessions and encrypted data.

### 4. Manual Environment Variables Setup

If Render doesn't auto-detect all variables from render.yaml, manually set these in the web service environment:

**Web Service (novamine-web):**
```
APP_ENV=production
APP_DEBUG=false
APP_KEY=<set manually, generate once with: php artisan key:generate>
APP_URL=https://your-app-name.onrender.com
DB_CONNECTION=pgsql
DB_HOST=<from novamine-db service>
DB_PORT=<from novamine-db service>
DB_DATABASE=novamine
DB_USERNAME=novamine_user
DB_PASSWORD=<from novamine-db service>
CACHE_DRIVER=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
SESSION_SECURE_COOKIE=true
LOG_CHANNEL=stderr
LOG_LEVEL=warning
```

**Worker Service (novamine-queue):**
```
APP_ENV=production
APP_DEBUG=false
APP_KEY=<same as web service - use the exact same value>
DB_CONNECTION=pgsql
DB_HOST=<from novamine-db service>
DB_PORT=<from novamine-db service>
DB_DATABASE=novamine
DB_USERNAME=novamine_user
DB_PASSWORD=<from novamine-db service>
CACHE_DRIVER=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
LOG_CHANNEL=stderr
LOG_LEVEL=warning
```

### 5. Database Configuration

The PostgreSQL database service (`novamine-db`) will be created automatically. Render will provide:
- Host, port, database name, username, and password
- These are automatically linked via `fromService` in render.yaml

### 6. Deployment Process

Render will automatically:
1. Create PostgreSQL database service
2. Build web service with:
   - Composer install (production dependencies)
   - Run database migrations
   - Seed demo user (if not exists)
   - Create storage link
   - Cache config and routes
3. Start web service with `php artisan serve`
4. Build and start queue worker

### 7. First Access

1. Wait for deployment to complete (check Render logs)
2. Access your app at: `https://your-app-name.onrender.com`
3. Login with demo credentials:
   - Email: `demo@novamine.ai`
   - Password: `demo1234`

### 8. Post-Deployment Configuration

Update `APP_URL` in Render environment variables to match your actual Render URL.

## Service Architecture

- **PostgreSQL Database**: Stores all application data (users, candidates, mining jobs, etc.)
- **Web Service**: Handles HTTP requests, serves the Laravel application
- **Queue Worker**: Processes background mining jobs (`ProcessMiningJob`)

## Important Notes

- **No migrate:fresh**: Uses safe `migrate --force` to preserve existing data
- **Idempotent Seeding**: Demo user only created if doesn't exist
- **Persistent APP_KEY**: Must be set manually once, never regenerated (breaks sessions/encryption)
- **Secure Sessions**: HTTPS-only cookies enabled
- **Logging**: Logs sent to stderr for Render monitoring
- **Queue Configuration**: Worker configured with 3 retries, 120s timeout
- **Built Assets**: Frontend assets committed to repository (npm not available in build)

## Troubleshooting

### Build Failures
- Check Render build logs for specific errors
- Ensure PostgreSQL service starts before web service
- Verify Composer dependencies are compatible

### Database Connection Issues
- Ensure database service is running
- Check environment variables are correctly linked
- Verify database credentials from Render dashboard

### Queue Worker Issues
- Check worker logs in Render dashboard
- Ensure DB_CONNECTION matches web service
- Verify queue tables exist in database

### Asset Issues
- Frontend assets are pre-built and committed
- If changes needed to frontend, rebuild locally and commit assets

## Monitoring

- **Web Service**: Monitor via Render dashboard
- **Queue Worker**: Monitor job processing in logs
- **Database**: Monitor via PostgreSQL service metrics
- **Logs**: All logs sent to stderr for Render's log viewer

## Scaling

- **Web Service**: Can scale vertically (upgrade plan) or horizontally (add instances)
- **Queue Worker**: Can add multiple workers for high-volume job processing
- **Database**: Can upgrade PostgreSQL plan for better performance

## Security Considerations

- APP_KEY must be set manually once and never changed (breaks sessions/encryption)
- Database credentials managed by Render
- HTTPS enforced with secure cookies
- Debug mode disabled in production
- Environment variables not exposed in logs

## Backups

- Render automatically backs up PostgreSQL databases
- Consider implementing regular export routines for candidate data
- Google Sheets export feature provides additional backup option
