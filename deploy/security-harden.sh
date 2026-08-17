#!/bin/bash
# ERO Security Hardening
cd /home/uddjzwrz/mediprep.nokkoo.in

echo "=== ERO Security Hardening ==="

# 1. Lock down .env file
echo "→ Securing .env file..."
chmod 640 .env

# 2. Block .git directory access
echo "→ Blocking .git access..."
if [ -d .git ]; then
  cat > .git/.htaccess << 'EOF'
Order allow,deny
Deny from all
EOF
fi

# 3. Block storage directory from web
echo "→ Blocking storage access..."
cat > storage/.htaccess << 'EOF'
Order allow,deny
Deny from all
EOF

# 4. Block vendor directory from web
echo "→ Blocking vendor access..."
cat > vendor/.htaccess << 'EOF'
Order allow,deny
Deny from all
EOF

# 5. Remove debug/dev files from public access
echo "→ Removing debug artifacts..."
rm -f phpinfo.php test.php info.php debug.php 2>/dev/null

# 6. Ensure proper log rotation
echo "→ Setting up log management..."
# Clear old logs over 30 days
find storage/logs -name "*.log" -mtime +30 -delete 2>/dev/null

# 7. Set secure session config
echo "→ Verifying session security..."
php artisan config:show session 2>/dev/null | grep -q "secure_cookie.*true" && echo "  ✅ Secure cookies enabled" || echo "  ⚠️ Check SESSION_SECURE_COOKIE in .env"

# 8. Clear any cached sensitive data
echo "→ Clearing expired tokens..."
php artisan sanctum:prune-expired 2>/dev/null || echo "  (sanctum:prune-expired not available)"

echo ""
echo "=== Security Hardening Complete ==="
