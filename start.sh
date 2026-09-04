#!/bin/bash

# Run database migrations
php artisan migrate --force

# Seed database (idempotent)
php artisan db:seed --force

# Create storage link
php artisan storage:link

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Start the application
exec php artisan serve --host=0.0.0.0 --port=$PORT
