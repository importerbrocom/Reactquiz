#!/bin/bash
# ERO Production Hardening Script
cd /home/uddjzwrz/mediprep.nokkoo.in

echo "=== ERO Production Hardening ==="

# 1. Cache Laravel config/routes/views for speed
echo "→ Caching config, routes, views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 2. Optimize autoloader
echo "→ Optimizing..."
php artisan optimize

# 3. Set permissions
echo "→ Setting permissions..."
chmod -R 775 storage bootstrap/cache

echo "=== Done! ==="
