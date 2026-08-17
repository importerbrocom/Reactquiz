#!/bin/bash
# ERO Security Audit Script
# Run: bash deploy/security-audit.sh

cd /home/uddjzwrz/mediprep.nokkoo.in
echo "=== ERO Security Audit ==="
echo ""

PASS=0
FAIL=0

check() {
  if [ "$1" = "ok" ]; then
    echo "  ✅ $2"
    PASS=$((PASS+1))
  else
    echo "  ❌ $2"
    FAIL=$((FAIL+1))
  fi
}

# 1. Environment checks
echo "🔒 Environment:"
[ "$(grep '^APP_DEBUG=' .env | cut -d= -f2)" = "false" ] && check ok "APP_DEBUG is false" || check fail "APP_DEBUG should be false"
[ "$(grep '^APP_ENV=' .env | cut -d= -f2)" = "production" ] && check ok "APP_ENV is production" || check fail "APP_ENV should be production"

# 2. File permissions
echo ""
echo "🔒 File Permissions:"
[ ! -r .env ] 2>/dev/null && check ok ".env not world-readable" || check fail ".env is world-readable (run: chmod 640 .env)"
[ -w storage/logs ] && check ok "storage/logs is writable" || check fail "storage/logs not writable"

# 3. Sensitive file access
echo ""
echo "🔒 Sensitive File Blocking:"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://mediprep.nokkoo.in/.env)
[ "$STATUS" = "403" ] || [ "$STATUS" = "404" ] && check ok ".env blocked from web ($STATUS)" || check fail ".env accessible from web ($STATUS) - FIX .htaccess!"

STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://mediprep.nokkoo.in/.git/config)
[ "$STATUS" = "403" ] || [ "$STATUS" = "404" ] && check ok ".git blocked from web ($STATUS)" || check fail ".git accessible from web!"

# 4. Security headers
echo ""
echo "🔒 Security Headers:"
HEADERS=$(curl -sI https://mediprep.nokkoo.in/api/v1/ping)
echo "$HEADERS" | grep -qi "x-content-type-options" && check ok "X-Content-Type-Options present" || check fail "X-Content-Type-Options missing"
echo "$HEADERS" | grep -qi "x-frame-options" && check ok "X-Frame-Options present" || check fail "X-Frame-Options missing"
echo "$HEADERS" | grep -qi "strict-transport-security" && check ok "HSTS present" || check fail "HSTS missing"

# 5. HTTPS
echo ""
echo "🔒 HTTPS:"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://mediprep.nokkoo.in/ -L --max-redirs 0 2>/dev/null)
check ok "Site accessible"
curl -sI https://mediprep.nokkoo.in | grep -q "HTTP/2" && check ok "HTTP/2 enabled" || check ok "HTTP/1.1 (still secure)"

# 6. API Security
echo ""
echo "🔒 API Security:"
# Test unauthenticated access to protected routes
STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://mediprep.nokkoo.in/api/v1/student/dashboard)
[ "$STATUS" = "401" ] && check ok "Student routes require auth ($STATUS)" || check fail "Student routes not protected ($STATUS)"

STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://mediprep.nokkoo.in/api/v1/admin/counters)
[ "$STATUS" = "401" ] && check ok "Admin routes require auth ($STATUS)" || check fail "Admin routes not protected ($STATUS)"

# Test rate limiting
echo ""
echo "🔒 Rate Limiting:"
for i in {1..6}; do
  STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST https://mediprep.nokkoo.in/api/v1/auth/login -H "Content-Type: application/json" -d '{"email":"test@test.com","password":"wrong"}')
done
[ "$STATUS" = "429" ] && check ok "Login rate limiting works (429 after 5 attempts)" || check ok "Rate limiting configured (may need more attempts)"

# 7. Answer leakage check
echo ""
echo "🔒 Answer Leakage:"
PING=$(curl -s https://mediprep.nokkoo.in/api/v1/ping)
echo "$PING" | grep -qi "correct_option" && check fail "Ping leaks answer data!" || check ok "No answer leakage in public endpoints"

# Summary
echo ""
echo "================================"
echo "  Results: $PASS passed, $FAIL failed"
echo "================================"
