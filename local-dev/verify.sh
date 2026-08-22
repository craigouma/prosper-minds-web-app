#!/usr/bin/env bash
# End-to-end acceptance run for the Phase 1 fixes, against the LOCAL stack only.
#
#   docker compose up -d
#   php -S 127.0.0.1:8080 -t public_html      &
#   php -S 127.0.0.1:8081 -t cpd.prosper-minds.com &
#   local-dev/verify.sh
#
# Every address used is @example.test (or Craig's own inbox, which still only
# reaches the Mailpit container). Nothing here can reach production.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

MAIN=http://127.0.0.1:8080
CPD=http://127.0.0.1:8081
MAILPIT=http://127.0.0.1:8025

DB_MAIN=(docker exec pm-db-main mariadb --skip-ssl -upm_local -ppm_local_pw kidsmone_Prosperminds_website -N -B -e)
DB_CPD=(docker exec pm-db-cpd mariadb --skip-ssl -ucpd_local -pcpd_local_pw kidsmone_cpd_events -N -B -e)

# Reset both databases to the untouched dumps first. The assertions below count
# rows, so the run has to start from a known state — and rebuilding from the
# dumps every time is also the proof that the environment is reproducible.
# Set SKIP_RESET=1 to reuse the current containers.
if [ "${SKIP_RESET:-0}" != "1" ]; then
  echo "Resetting the local stack from the SQL dumps..."
  rm -f public_html/storage/logs/invoice-backlog-sent.log
  docker compose down -v >/dev/null 2>&1
  docker compose up -d >/dev/null 2>&1
  for _ in $(seq 1 60); do
    a="$(docker inspect -f '{{.State.Health.Status}}' pm-db-main 2>/dev/null)"
    b="$(docker inspect -f '{{.State.Health.Status}}' pm-db-cpd 2>/dev/null)"
    [ "$a" = healthy ] && [ "$b" = healthy ] && break
    sleep 2
  done
  echo
fi

pass=0; fail=0
check() { # check <label> <expected> <actual>
  if [ "$2" = "$3" ]; then printf '  PASS  %-58s %s\n' "$1" "$3"; pass=$((pass+1));
  else printf '  FAIL  %-58s expected=%s actual=%s\n' "$1" "$2" "$3"; fail=$((fail+1)); fi
}
mailcount() { curl -s "$MAILPIT/api/v1/messages?limit=200" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo (int)($d["total"]??0);'; }
clearmail() { curl -s -X DELETE "$MAILPIT/api/v1/messages" >/dev/null; }
json_success() { printf '%s' "$1" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo var_export($d["success"]??null,true);'; }

echo "=== 2. PHPMailer vendor package ==="
check "PHPMailer.php lints" "0" "$(php -l public_html/vendor/phpmailer/phpmailer/src/PHPMailer.php >/dev/null 2>&1; echo $?)"
check "SMTP.php lints"      "0" "$(php -l public_html/vendor/phpmailer/phpmailer/src/SMTP.php >/dev/null 2>&1; echo $?)"
check "SMTP.php not empty"  "yes" "$([ -s public_html/vendor/phpmailer/phpmailer/src/SMTP.php ] && echo yes || echo no)"
check "PHPMailer VERSION"   "7.0.2" "$(cd public_html && php -r 'require "vendor/autoload.php"; echo (new PHPMailer\PHPMailer\PHPMailer(true))::VERSION;')"

echo
echo "=== 3. Registration response decoupled from email outcome ==="
clearmail
OUT="$(TOKEN_MODE=valid ./local-dev/test-register-main.sh happy@example.test)"
check "happy path returns success"    "true" "$(json_success "$OUT")"
check "happy path emails caught"      "5"    "$(mailcount)"
check "no failed_notifications rows"  "0"    "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM failed_notifications" 2>/dev/null || echo 0)"

# Break the mail path by pointing at a dead port, then restore.
cp public_html/.env /tmp/verify-env.bak
sed -i '' 's/^SMTP_PORT=.*/SMTP_PORT=1099/' public_html/.env
OUT="$(TOKEN_MODE=valid ./local-dev/test-register-main.sh brokensmtp@example.test)"
cp /tmp/verify-env.bak public_html/.env
check "dead SMTP still returns success" "true" "$(json_success "$OUT")"
check "row persisted despite mail fail" "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM event_registrations WHERE email='brokensmtp@example.test'")"
check "5 failures recorded"             "5" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM failed_notifications WHERE error_message LIKE '%Could not connect%'")"

# Reproduce the exact August corruption, then restore.
V=public_html/vendor/phpmailer/phpmailer/src/PHPMailer.php
cp "$V" /tmp/verify-phpmailer.bak
head -c 130680 /tmp/verify-phpmailer.bak > "$V"
OUT="$(TOKEN_MODE=valid ./local-dev/test-register-main.sh corruptvendor@example.test)"
cp /tmp/verify-phpmailer.bak "$V"
check "corrupt vendor still returns success" "true" "$(json_success "$OUT")"
check "row persisted despite ParseError" "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM event_registrations WHERE email='corruptvendor@example.test'")"
check "ParseError recorded as unsent"    "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM failed_notifications WHERE error_message LIKE '%Unterminated comment%'")"
check "vendor file restored intact"      "0" "$(php -l "$V" >/dev/null 2>&1; echo $?)"

echo
echo "=== 4. CSRF, main site ==="
for mode in missing forged nosession; do
  OUT="$(TOKEN_MODE=$mode ./local-dev/test-register-main.sh "csrf-$mode@example.test")"
  check "main: $mode token rejected" "false" "$(json_success "$OUT")"
done
check "main: no rows from rejected posts" "0" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM event_registrations WHERE email LIKE 'csrf-%'")"

echo
echo "=== 4. CSRF, CPD ==="
OUT="$(TOKEN_MODE=valid ./local-dev/test-register-cpd.sh cpd-valid@example.test)"
check "cpd: valid token redirects to success" "yes" "$(printf '%s' "$OUT" | grep -q 'success.php' && echo yes || echo no)"
for mode in missing forged nosession; do
  OUT="$(TOKEN_MODE=$mode ./local-dev/test-register-cpd.sh "cpd-$mode@example.test")"
  check "cpd: $mode token bounced to form" "yes" "$(printf '%s' "$OUT" | grep -q 'registration_page.php.*error=' && echo yes || echo no)"
done
check "cpd: no rows from rejected posts" "0" "$("${DB_CPD[@]}" "SELECT COUNT(*) FROM registrations WHERE email LIKE 'cpd-missing%' OR email LIKE 'cpd-forged%' OR email LIKE 'cpd-nosession%'")"

echo
echo "=== 5. CPD sendEmail() hardening ==="
CV=cpd.prosper-minds.com/vendor/phpmailer/phpmailer/src/PHPMailer.php
cp "$CV" /tmp/verify-cpd-phpmailer.bak
head -c 130680 /tmp/verify-cpd-phpmailer.bak > "$CV"
OUT="$(TOKEN_MODE=valid ./local-dev/test-register-cpd.sh cpd-corrupt@example.test)"
cp /tmp/verify-cpd-phpmailer.bak "$CV"
check "cpd: corrupt vendor still redirects" "yes" "$(printf '%s' "$OUT" | grep -q 'success.php' && echo yes || echo no)"
check "cpd: row persisted" "1" "$("${DB_CPD[@]}" "SELECT COUNT(*) FROM registrations WHERE email='cpd-corrupt@example.test'")"
check "cpd vendor file restored intact" "0" "$(php -l "$CV" >/dev/null 2>&1; echo $?)"

echo
echo "=== 6. Credentials from environment ==="
check "main .env is gitignored" "yes" "$(git -C public_html check-ignore -q .env && echo yes || echo no)"
check "cpd  .env is gitignored" "yes" "$(git -C cpd.prosper-minds.com check-ignore -q .env && echo yes || echo no)"
check "main .env.example tracked" "yes" "$(git -C public_html ls-files --error-unmatch .env.example >/dev/null 2>&1 && echo yes || echo no)"
check "cpd  .env.example tracked" "yes" "$(git -C cpd.prosper-minds.com ls-files --error-unmatch .env.example >/dev/null 2>&1 && echo yes || echo no)"
check "no literal DB password in main config" "0" "$(grep -c "DB_PASS', '" public_html/includes/db-credentials.php)"
check "no literal DB password in cpd config"  "0" "$(grep -c "DB_PASS', '" cpd.prosper-minds.com/config.php)"
check "no literal SMTP password in cpd config" "0" "$(grep -c "mail->Password *= *'" cpd.prosper-minds.com/config.php)"
mv public_html/.env public_html/.env.hold
check "main fails loudly with no config" "yes" \
  "$(cd public_html && php -r 'require "includes/db-credentials.php";' 2>&1 | grep -q 'configuration is incomplete' && echo yes || echo no)"
mv public_html/.env.hold public_html/.env
check "main works from env vars alone" "4" \
  "$(cd public_html && PM_ENV_FILE=/nonexistent DB_HOST=127.0.0.1 DB_PORT=3307 DB_NAME=kidsmone_Prosperminds_website DB_USER=pm_local DB_PASS=pm_local_pw php -r 'require "includes/config.php"; echo $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();')"

echo
echo "=== 7. Invoice backlog recovery ==="
check "dry run finds all 36 rows" "36" \
  "$(cd public_html && php tools/send-invoice-backlog.php | grep -c '^  #')"
check "dry run sends nothing" "yes" \
  "$(cd public_html && php tools/send-invoice-backlog.php | grep -q 'Nothing was sent' && echo yes || echo no)"
check "dry run finds every PDF" "0" \
  "$(cd public_html && php tools/send-invoice-backlog.php | grep -c 'NO INVOICE PDF')"
check "production send refused without confirm" "2" \
  "$(cd public_html && php tools/send-invoice-backlog.php --send --mail-target=production >/dev/null 2>&1; echo $?)"
check "refuses over HTTP" "404" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/tools/send-invoice-backlog.php")"
clearmail
(cd public_html && php tools/send-invoice-backlog.php --send --ids=12,13 >/dev/null 2>&1)
check "local send delivers 4 messages" "4" "$(mailcount)"
check "re-run skips already sent" "2" \
  "$(cd public_html && php tools/send-invoice-backlog.php --send --ids=12,13 | grep -c 'SKIPPED')"
rm -f public_html/storage/logs/invoice-backlog-sent.log

echo
echo "=== Currency parsing regression ==="
check "From USD 599 Per Delegate -> USD" "USD" \
  "$(cd public_html && php -r 'require "includes/invoice.php"; echo parseEventPrice("From USD 599 Per Delegate")[0];')"

echo
printf '\n%s\npassed=%d failed=%d\n%s\n' "$(printf '=%.0s' {1..78})" "$pass" "$fail" "$(printf '=%.0s' {1..78})"
exit $((fail > 0 ? 1 : 0))
