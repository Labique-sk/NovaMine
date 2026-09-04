#!/bin/bash

# Start the queue worker
exec php artisan queue:work --tries=3 --timeout=120 --sleep=3 --max-jobs=1000
