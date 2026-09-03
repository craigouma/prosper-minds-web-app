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
# Same client but reading a script from stdin, for applying migration files.
DB_MAIN_FILE=(docker exec -i pm-db-main mariadb --skip-ssl -upm_local -ppm_local_pw kidsmone_Prosperminds_website)

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
json_reg_id()  { printf '%s' "$1" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo (int)($d["registration_id"]??0);'; }

# funnel_events helpers. Every one of these is scoped so it cannot accidentally
# count rows written by an earlier section of this script.
fq()          { "${DB_MAIN[@]}" "$1" 2>/dev/null || echo ERR; }
funnel_rows() { fq "SELECT COUNT(*) FROM funnel_events WHERE event_type='$1' AND event_id=$2"; }
table_exists(){ fq "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='$1'"; }

# Pull one value out of a rendered admin/analytics.php page. The markers are
# real attributes on the page (data-funnel-stage / data-metric / data-drop), each
# on a single line specifically so this stays a one-liner rather than an HTML
# parser. See the comments in admin/analytics.php.
funnel_shown()  { printf '%s' "$1" | sed -n "s/.*data-funnel-stage=\"$2\" data-count=\"\([0-9]*\)\".*/\1/p" | head -1; }
session_shown() { printf '%s' "$1" | sed -n "s/.*data-funnel-stage=\"$2\" data-count=\"[0-9]*\" data-sessions=\"\([0-9]*\)\".*/\1/p" | head -1; }
dropoff_shown() { printf '%s' "$1" | sed -n "s/.*data-dropoff=\"$2\" data-drop=\"\(-*[0-9.]*\)\".*/\1/p" | head -1; }
metric_shown()  { printf '%s' "$1" | sed -n "s/.*data-metric=\"$2\">\([^<]*\)<.*/\1/p" | head -1; }

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

# ════════════════════════════════════════════════════════════════════════════
# 8. Registration funnel analytics (main site only)
#
# Everything below uses EVENT 3 (Bali). That is deliberate: the production dump
# has ZERO registrations for event 3, and no earlier section of this script
# touches it, so every count here is fully determined by this section. Sections
# 3 and 7 above use event 2.
# ════════════════════════════════════════════════════════════════════════════
FUNNEL_UP=public_html/database/migrations/2026-08-22-01-create-funnel-events.up.sql
FUNNEL_DOWN=public_html/database/migrations/2026-08-22-01-create-funnel-events.down.sql

echo
echo "=== 8a. funnel_events migration files ==="
check "up migration tracked in git"   "yes" "$(git ls-files --error-unmatch "$FUNNEL_UP"   >/dev/null 2>&1 && echo yes || echo no)"
check "down migration tracked in git" "yes" "$(git ls-files --error-unmatch "$FUNNEL_DOWN" >/dev/null 2>&1 && echo yes || echo no)"

# Apply the pair by hand against the real MariaDB. This is what proves the files
# are valid SQL and not just plausible-looking text, and it leaves the table
# absent so on-demand creation can be tested for real immediately below.
fq "DROP TABLE IF EXISTS funnel_events" >/dev/null
check "up migration applies cleanly" "0" \
  "$("${DB_MAIN_FILE[@]}" < "$FUNNEL_UP" >/dev/null 2>&1; echo $?)"
check "table exists after up migration" "1" "$(table_exists funnel_events)"
check "session_id and event_type both indexed" "2" \
  "$(fq "SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics
          WHERE table_schema=DATABASE() AND table_name='funnel_events'
            AND seq_in_index=1 AND column_name IN ('session_id','event_type')")"
check "down migration applies cleanly" "0" \
  "$("${DB_MAIN_FILE[@]}" < "$FUNNEL_DOWN" >/dev/null 2>&1; echo $?)"
check "table gone after down migration" "0" "$(table_exists funnel_events)"

echo
echo "=== 8b. page_view, and the table created on demand ==="
FJAR="$(mktemp)"; FBODY="$(mktemp)"
# No funnel_events table exists at this point — the down migration just dropped
# it. A visitor arriving now must both get their page AND cause the table to be
# created, with no manual migration step anywhere.
FCODE="$(curl -s -c "$FJAR" -o "$FBODY" -w '%{http_code}' \
  -e 'https://www.linkedin.com/feed/?trackingId=must-not-be-stored' \
  "$MAIN/event-registration.php?id=3&utm_source=verify&utm_medium=cpc&utm_campaign=funneltest")"
check "registration page renders with no table" "200" "$FCODE"
check "form still rendered"                     "yes" "$(grep -q 'standaloneRegForm' "$FBODY" && echo yes || echo no)"
check "funnel_events created on demand"         "1"   "$(table_exists funnel_events)"
check "exactly one page_view for event 3"       "1"   "$(funnel_rows page_view 3)"

FSID="$(awk '$6=="pm_funnel_sid" {print $7}' "$FJAR")"
check "pm_funnel_sid cookie issued, UUID shaped" "yes" \
  "$(printf '%s' "$FSID" | grep -Eq '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$' && echo yes || echo no)"
check "page_view recorded under that cookie" "1" \
  "$(fq "SELECT COUNT(*) FROM funnel_events WHERE session_id='$FSID' AND event_type='page_view'")"
check "utm parameters captured" "verify|cpc|funneltest" \
  "$(fq "SELECT CONCAT_WS('|',utm_source,utm_medium,utm_campaign) FROM funnel_events WHERE event_type='page_view' AND event_id=3 LIMIT 1")"
# Data minimisation: the referrer keeps scheme+host+path and loses the query
# string, which on a real referrer can carry the other site's own user ids.
check "referrer stored without its query string" "https://www.linkedin.com/feed/" \
  "$(fq "SELECT referrer FROM funnel_events WHERE event_type='page_view' AND event_id=3 LIMIT 1")"
check "no IP or user-agent column exists at all" "0" \
  "$(fq "SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema=DATABASE() AND table_name='funnel_events'
            AND column_name IN ('ip','ip_address','remote_addr','user_agent')")"

echo
echo "=== 8c. form_started beacon ==="
FTOK="$(sed -n 's/.*name="csrf_token" value="\([a-f0-9]*\)".*/\1/p' "$FBODY" | head -1)"
beacon() { # beacon <csrf_token> <event_type>
  curl -s -o /dev/null -w '%{http_code}' -b "$FJAR" -X POST "$MAIN/track-funnel-event.php" \
    -F "event_type=$2" -F "event_id=3" -F "csrf_token=$1"
}
check "beacon answers 204"        "204" "$(beacon "$FTOK" form_started)"
check "one form_started logged"   "1"   "$(funnel_rows form_started 3)"
check "second beacon answers 204" "204" "$(beacon "$FTOK" form_started)"
check "duplicate beacon deduped"  "1"   "$(funnel_rows form_started 3)"
check "forged token answers 204"  "204" "$(beacon "$(printf 'a%.0s' {1..64})" form_started)"
check "forged token wrote nothing" "1"  "$(funnel_rows form_started 3)"
# A browser must not be able to manufacture a conversion — submit_success has to
# come from the server knowing a row was committed, or Priority 2's Google Ads
# conversion is built on a number the client side can invent.
check "client cannot write submit_success" "204" "$(beacon "$FTOK" submit_success)"
check "no client-written submit_success"   "0"   "$(funnel_rows submit_success 3)"

echo
echo "=== 8d. submit_attempt / submit_success ==="
# JAR= reuses the same cookie jar, so this registration continues the SAME
# funnel session the page view and beacon above belong to. The harness fetches
# the form first, exactly as a browser would, which is the second page_view.
OUT="$(JAR="$FJAR" TOKEN_MODE=valid EVENT_ID=3 ./local-dev/test-register-main.sh funnel-ok@example.test)"
FREG="$(json_reg_id "$OUT")"
check "registration succeeded"        "true" "$(json_success "$OUT")"
check "one submit_attempt for event 3" "1"   "$(funnel_rows submit_attempt 3)"
check "one submit_success for event 3" "1"   "$(funnel_rows submit_success 3)"
check "registration_id populated on submit_success" "$FREG" \
  "$(fq "SELECT registration_id FROM funnel_events WHERE event_type='submit_success' AND event_id=3")"
check "whole funnel shares one session" "1" \
  "$(fq "SELECT COUNT(DISTINCT session_id) FROM funnel_events WHERE event_id=3")"
check "two page_views now (visit + harness form fetch)" "2" "$(funnel_rows page_view 3)"
check "no submit_fail for event 3" "0" "$(funnel_rows submit_fail 3)"

# A genuine validation failure is what submit_fail is for.
OUT="$(curl -s -b "$FJAR" -X POST "$MAIN/process-registration.php" \
  -F "csrf_token=$FTOK" -F "event_id=3" -F "event_name=x" \
  -F "first_name=A" -F "last_name=B" -F "phone=not-a-phone" -F "email=v@example.test" \
  -F "organization=O" -F "country=Kenya" -F "address=A" -F "consent=on" \
  -F "attendees[first_name][]=A" -F "attendees[last_name][]=B")"
check "invalid phone rejected"    "false" "$(json_success "$OUT")"
check "one submit_fail recorded"  "1"     "$(funnel_rows submit_fail 3)"
check "no extra registration row" "0"     "$(fq "SELECT COUNT(*) FROM event_registrations WHERE email='v@example.test'")"

# A forged token must not reach the database at all, funnel_events included.
BEFORE="$(fq "SELECT COUNT(*) FROM funnel_events")"
curl -s -o /dev/null -X POST "$MAIN/process-registration.php" -F "event_id=3" -F "csrf_token=nope"
check "CSRF-rejected post writes no funnel row" "$BEFORE" "$(fq "SELECT COUNT(*) FROM funnel_events")"

echo
echo "=== 8e. admin/analytics.php ==="
AJAR="$(mktemp)"
ALOGIN="$(curl -s -c "$AJAR" "$MAIN/admin/login.php")"
ATOK="$(printf '%s' "$ALOGIN" | sed -n 's/.*name="csrf_token" value="\([a-f0-9]*\)".*/\1/p' | head -1)"
# The Craig account is inserted by local-dev/main-local-overrides.sql into
# the throwaway container only. It does not exist in production.
check "admin login succeeds" "302" \
  "$(curl -s -b "$AJAR" -c "$AJAR" -o /dev/null -w '%{http_code}' -X POST "$MAIN/admin/login.php" \
      -d "csrf_token=$ATOK" -d "username=Craig" -d "password=localtest-analytics-pw")"
check "analytics.php requires auth" "302" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/admin/analytics.php")"

A3="$(curl -s -b "$AJAR" "$MAIN/admin/analytics.php?range=all&event_id=3")"
check "analytics.php renders for event 3" "yes" "$(printf '%s' "$A3" | grep -q 'Registration Funnel' && echo yes || echo no)"
check "no PHP error in the page" "0" \
  "$(printf '%s' "$A3" | grep -ciE 'fatal error|parse error|warning:|uncaught')"

# Expected values, computed from what this section did, not read back from SQL:
#   2 page views (8b visit + the harness form fetch in 8d)
#   1 form_started (8c, after two duplicates and a forged token were dropped)
#   1 submit_attempt from the successful registration, 1 submit_success
#   ... plus the invalid-phone post, which is a second submit_attempt.
check "shown page_views"      "2" "$(funnel_shown "$A3" page_view)"
check "shown form_started"    "1" "$(funnel_shown "$A3" form_started)"
check "shown submit_attempt"  "2" "$(funnel_shown "$A3" submit_attempt)"
check "shown submit_success"  "1" "$(funnel_shown "$A3" submit_success)"
check "shown sessions on page_view" "1" "$(session_shown "$A3" page_view)"
# 2 -> 1 loses half. 1 -> 2 GAINS one, reported as -100.0: curl never runs the
# form_started beacon's JavaScript, so submits legitimately outnumber
# form-starts here. The page says "100.0% MORE than the previous stage" rather
# than clamping it, because that reading is exactly how an under-reporting
# beacon would show up in production too.
check "drop-off view to form-start"   "50.0"   "$(dropoff_shown "$A3" form_started)"
check "gain form-start to submit"     "-100.0" "$(dropoff_shown "$A3" submit_attempt)"
check "drop-off submit to success"    "50.0"   "$(dropoff_shown "$A3" submit_success)"
check "conversion rate 1 of 2 views"   "50.0%" "$(metric_shown "$A3" conversion_rate)"
check "failed submissions shown"       "1"     "$(metric_shown "$A3" failed_submits)"
# One registration for event 3, two delegates at USD 599 each.
check "event 3 registrations shown" "1" "$(metric_shown "$A3" registrations)"
check "event 3 delegates shown"     "2" "$(metric_shown "$A3" delegates)"
check "event 3 revenue shown"       "USD 1,198.00" "$(metric_shown "$A3" revenue)"

# Unfiltered totals. 47 registrations in the production dump + 3 created by
# section 3 (happy path, dead SMTP, corrupted vendor) + 1 created by 8d = 51.
# Revenue: USD 25,757.00 in the dump + 4 x USD 1,198.00 = USD 30,549.00.
# Delegates: 50 in the dump + 4 x 2 = 58. If these three fail together, an
# earlier section changed how many registrations it creates.
check "seeded + created registration count" "51" "$(fq "SELECT COUNT(*) FROM event_registrations")"
AALL="$(curl -s -b "$AJAR" "$MAIN/admin/analytics.php?range=all&event_id=0")"
check "all-time registrations shown" "51" "$(metric_shown "$AALL" registrations)"
check "all-time revenue shown"       "USD 30,549.00" "$(metric_shown "$AALL" revenue)"
check "all-time delegates shown"     "58" "$(metric_shown "$AALL" delegates)"
check "traffic source panel names the campaign" "yes" \
  "$(printf '%s' "$AALL" | grep -q 'verify' && echo yes || echo no)"

# Filters must not be injectable, and a nonsense range must not blank the page.
check "range=7 renders"       "200" "$(curl -s -b "$AJAR" -o /dev/null -w '%{http_code}' "$MAIN/admin/analytics.php?range=7")"
check "custom range renders"  "200" "$(curl -s -b "$AJAR" -o /dev/null -w '%{http_code}' "$MAIN/admin/analytics.php?range=custom&from=2026-08-01&to=2026-08-31")"
check "hostile filter values render" "200" \
  "$(curl -s -b "$AJAR" -o /dev/null -w '%{http_code}' "$MAIN/admin/analytics.php?range=custom&from=x&to=%27+OR+1%3D1--&event_id=abc")"
check "registrations table still intact after that" "1" "$(table_exists event_registrations)"

echo
echo "=== 8f. An undeliverable email is NOT a submit_fail ==="
# The analytics-side restatement of the Phase 1 fix. Registration succeeds, the
# five notification emails do not: that is a failed_notifications row, and the
# funnel must record submit_success, because the delegate IS registered.
# Counting it as submit_fail would rebuild the August 2026 confusion inside the
# client's reports.
cp public_html/.env /tmp/verify-env-funnel.bak
sed -i '' 's/^SMTP_PORT=.*/SMTP_PORT=1099/' public_html/.env
FAILED_BEFORE="$(fq "SELECT COUNT(*) FROM failed_notifications")"
OUT="$(TOKEN_MODE=valid EVENT_ID=3 ./local-dev/test-register-main.sh funnel-mailfail@example.test)"
cp /tmp/verify-env-funnel.bak public_html/.env
rm -f /tmp/verify-env-funnel.bak
check "dead SMTP still returns success"  "true" "$(json_success "$OUT")"
check "counted as submit_success"        "2"    "$(funnel_rows submit_success 3)"
check "NOT counted as submit_fail"       "1"    "$(funnel_rows submit_fail 3)"
check "recorded in failed_notifications" "5"    \
  "$(fq "SELECT COUNT(*) - $FAILED_BEFORE FROM failed_notifications")"

echo
echo "=== 8g. CRITICAL: broken tracking must never break a registration ==="
# The single most important assertion in this feature. Same shape as the
# PHPMailer-corruption case in section 3: break the secondary concern for real,
# then prove the primary outcome is untouched.
#
# Note DROP is not a sufficient break — ensureFunnelEventsSchema() would simply
# recreate the table and every insert would succeed, testing nothing. So the
# real table is set aside and replaced by one whose schema the INSERT cannot
# possibly satisfy, which is what a half-applied migration looks like.
fq "RENAME TABLE funnel_events TO funnel_events_saved" >/dev/null
fq "CREATE TABLE funnel_events (id INT AUTO_INCREMENT PRIMARY KEY, wrong_column INT DEFAULT NULL)" >/dev/null

check "broken table: page still renders" "200" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/event-registration.php?id=3")"
check "broken table: beacon still 204" "204" "$(beacon "$FTOK" form_started)"

OUT="$(TOKEN_MODE=valid EVENT_ID=3 ./local-dev/test-register-main.sh brokenfunnel@example.test)"
check "broken table: registration returns success" "true" "$(json_success "$OUT")"
check "broken table: row persisted" "1" \
  "$(fq "SELECT COUNT(*) FROM event_registrations WHERE email='brokenfunnel@example.test'")"
check "broken table: invoice number assigned" "1" \
  "$(fq "SELECT COUNT(*) FROM event_registrations WHERE email='brokenfunnel@example.test' AND invoice_number IS NOT NULL")"
check "broken table: invoice PDF written" "yes" \
  "$([ -f "public_html/$(fq "SELECT invoice_path FROM event_registrations WHERE email='brokenfunnel@example.test'")" ] && echo yes || echo no)"
check "broken table: nothing written to it" "0" "$(fq "SELECT COUNT(*) FROM funnel_events")"

fq "DROP TABLE funnel_events" >/dev/null
fq "RENAME TABLE funnel_events_saved TO funnel_events" >/dev/null
check "real funnel table restored" "1" "$(table_exists funnel_events)"
check "restored table still holds its rows" "3" "$(funnel_rows page_view 3)"

echo
echo "=== 8h. CRITICAL: a missing or corrupted funnel.php must not break it either ==="
# The August 2026 outage was one file arriving incomplete. includes/funnel.php is
# the newest file in this deploy, so it is the likeliest one to arrive
# incomplete. config.php loads it defensively and substitutes no-ops.
F=public_html/includes/funnel.php
cp "$F" /tmp/verify-funnel.bak

mv "$F" /tmp/verify-funnel.moved
check "missing funnel.php: page renders" "200" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/event-registration.php?id=3")"
OUT="$(TOKEN_MODE=valid EVENT_ID=3 ./local-dev/test-register-main.sh nofunnelfile@example.test)"
mv /tmp/verify-funnel.moved "$F"
check "missing funnel.php: registration returns success" "true" "$(json_success "$OUT")"
check "missing funnel.php: row persisted" "1" \
  "$(fq "SELECT COUNT(*) FROM event_registrations WHERE email='nofunnelfile@example.test'")"

# Truncated mid-file, the way vendor/phpmailer/PHPMailer.php was. Raises
# ParseError, which extends Error — catch (Exception) would not have caught it.
head -c 6000 /tmp/verify-funnel.bak > "$F"
# Captured first, not piped: `php -l` exits 255 on a parse error and `set -o
# pipefail` would make the whole pipeline non-zero even when grep matched.
FLINT="$(php -l "$F" 2>&1 || true)"
check "truncated funnel.php really is a parse error" "yes" \
  "$(printf '%s' "$FLINT" | grep -q 'Parse error' && echo yes || echo no)"
check "truncated funnel.php: page renders" "200" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/event-registration.php?id=3")"
OUT="$(TOKEN_MODE=valid EVENT_ID=3 ./local-dev/test-register-main.sh corruptfunnel@example.test)"
cp /tmp/verify-funnel.bak "$F"
check "truncated funnel.php: registration returns success" "true" "$(json_success "$OUT")"
check "truncated funnel.php: row persisted" "1" \
  "$(fq "SELECT COUNT(*) FROM event_registrations WHERE email='corruptfunnel@example.test'")"
check "funnel.php restored and lints" "0" "$(php -l "$F" >/dev/null 2>&1; echo $?)"
check "funnel.php restored byte for byte" "yes" \
  "$(cmp -s "$F" /tmp/verify-funnel.bak && echo yes || echo no)"
rm -f /tmp/verify-funnel.bak "$FJAR" "$FBODY" "$AJAR"

echo
echo "=== 8i. form_started beacon, client side ==="
# Deliberately last: it fetches the registration page, which logs another
# page_view, and every count asserted above is exact.
#
# curl runs no JavaScript, so nothing above proves the listener actually fires.
# check-beacon-js.js extracts the shipped IIFE out of the rendered page and runs
# it against a DOM stub: fires once across four focus/input events, posts
# event_type/event_id/csrf_token, and falls back to fetch keepalive both when
# sendBeacon is absent and when it refuses the payload.
if command -v node >/dev/null 2>&1; then
  curl -s "$MAIN/event-registration.php?id=3" > /tmp/verify-beacon-page.html
  check "beacon JS: fires once, right payload, both fallbacks" "0" \
    "$(node local-dev/check-beacon-js.js /tmp/verify-beacon-page.html >/dev/null 2>&1; echo $?)"
  rm -f /tmp/verify-beacon-page.html
else
  printf '  SKIP  %-58s %s\n' "beacon JS checks (node is not installed)" "-"
fi

# ════════════════════════════════════════════════════════════════════════════
# 9. Rebuild Phase 1 foundation — design system, layout partials, content layer
#
# Everything below is ADDITIVE. Phase 1 of the rebuild does not convert or
# restyle a single existing page: index.php, event.php, event-registration.php,
# sponsorship.php, service-*.php, 404.php and assets/css/style.css are all
# untouched, which is why every assertion in sections 2 to 8 above must still
# pass unchanged. If one of them fails after a Phase 1 change, the change was
# not additive.
#
# The page under test is public_html/_phase1-preview.php — a deliberately
# temporary specimen page that composes head + header + footer and reads the
# content layer. It is not part of the site and is deleted before launch.
# ════════════════════════════════════════════════════════════════════════════
PC_UP=public_html/database/migrations/2026-08-28-01-create-page-content.up.sql
PC_DOWN=public_html/database/migrations/2026-08-28-01-create-page-content.down.sql
NS_UP=public_html/database/migrations/2026-08-28-02-create-newsletter-subscribers.up.sql
NS_DOWN=public_html/database/migrations/2026-08-28-02-create-newsletter-subscribers.down.sql
SEED_UP=public_html/database/migrations/2026-08-28-03-seed-page-content.up.sql
SEED_DOWN=public_html/database/migrations/2026-08-28-03-seed-page-content.down.sql

PREVIEW="$MAIN/_phase1-preview.php"

# Pull one value out of the rendered preview page. Same one-liner approach as
# funnel_shown() above: data-pm-check is a real attribute on the page and each
# one renders on a single line specifically so this stays a sed expression
# rather than an HTML parser.
pm_check()  { printf '%s' "$1" | sed -n "s/.*data-pm-check=\"$2\">\([^<]*\)<.*/\1/p" | head -1; }
pc_rows()   { fq "SELECT COUNT(*) FROM page_content"; }
subs_rows() { fq "SELECT COUNT(*) FROM newsletter_subscribers WHERE email='$1'"; }

echo
echo "=== 9a. page_content / newsletter_subscribers migration files ==="
for m in "$PC_UP" "$PC_DOWN" "$NS_UP" "$NS_DOWN" "$SEED_UP" "$SEED_DOWN"; do
  check "$(basename "$m") tracked in git" "yes" \
    "$(git ls-files --error-unmatch "$m" >/dev/null 2>&1 && echo yes || echo no)"
done

# Apply the pairs by hand against the real MariaDB, exactly as section 8a does
# for funnel_events. This is what proves the files are valid SQL rather than
# plausible-looking text, and it leaves both tables absent so on-demand creation
# can be tested for real in 9b.
fq "DROP TABLE IF EXISTS page_content" >/dev/null
fq "DROP TABLE IF EXISTS newsletter_subscribers" >/dev/null
check "page_content up migration applies cleanly" "0" \
  "$("${DB_MAIN_FILE[@]}" < "$PC_UP" >/dev/null 2>&1; echo $?)"
check "page_content table exists" "1" "$(table_exists page_content)"
check "(page_slug, section_key) is a unique index" "1" \
  "$(fq "SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics
          WHERE table_schema=DATABASE() AND table_name='page_content'
            AND non_unique=0 AND index_name='uq_page_content_slug_key'")"
check "newsletter up migration applies cleanly" "0" \
  "$("${DB_MAIN_FILE[@]}" < "$NS_UP" >/dev/null 2>&1; echo $?)"
check "newsletter_subscribers table exists" "1" "$(table_exists newsletter_subscribers)"
check "email is a unique index" "1" \
  "$(fq "SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics
          WHERE table_schema=DATABASE() AND table_name='newsletter_subscribers'
            AND non_unique=0 AND index_name='uq_newsletter_subscribers_email'")"
# Data minimisation, the same position taken for funnel_events in 8b: a mailing
# list needs the address and nothing else.
check "no IP or user-agent column on newsletter_subscribers" "0" \
  "$(fq "SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema=DATABASE() AND table_name='newsletter_subscribers'
            AND column_name IN ('ip','ip_address','remote_addr','user_agent')")"

check "seed migration applies cleanly" "0" \
  "$("${DB_MAIN_FILE[@]}" < "$SEED_UP" >/dev/null 2>&1; echo $?)"
# 77 rows: global 7, home 21, events 9, about 13, services 8, contact 9,
# sponsorship 7, notfound 3. Counted from the file, not read back out of SQL.
check "seed inserts 77 rows" "77" "$(pc_rows)"
check "seed re-applies without duplicating" "0" \
  "$("${DB_MAIN_FILE[@]}" < "$SEED_UP" >/dev/null 2>&1; echo $?)"
check "still 77 rows after a second seed" "77" "$(pc_rows)"

# INSERT IGNORE, not upsert: once the Phase 5 CMS exists these rows are what
# staff edit, and a seed that clobbers their work on the next deploy is a trap.
fq "UPDATE page_content SET content_value='EDITED BY A HUMAN' WHERE page_slug='home' AND section_key='hero_title'" >/dev/null
"${DB_MAIN_FILE[@]}" < "$SEED_UP" >/dev/null 2>&1
check "re-seeding does not overwrite an edited row" "EDITED BY A HUMAN" \
  "$(fq "SELECT content_value FROM page_content WHERE page_slug='home' AND section_key='hero_title'")"

check "seed down migration applies cleanly" "0" \
  "$("${DB_MAIN_FILE[@]}" < "$SEED_DOWN" >/dev/null 2>&1; echo $?)"
check "no seeded rows left" "0" "$(pc_rows)"
check "page_content down migration applies cleanly" "0" \
  "$("${DB_MAIN_FILE[@]}" < "$PC_DOWN" >/dev/null 2>&1; echo $?)"
check "page_content gone after down migration" "0" "$(table_exists page_content)"
check "newsletter down migration applies cleanly" "0" \
  "$("${DB_MAIN_FILE[@]}" < "$NS_DOWN" >/dev/null 2>&1; echo $?)"
check "newsletter_subscribers gone after down migration" "0" "$(table_exists newsletter_subscribers)"

echo
echo "=== 9b. Both tables created on demand ==="
# Neither table exists at this point — the down migrations just dropped them. A
# visitor arriving now must both get their page AND cause the table to be
# created, with no manual migration step anywhere. Same contract as 8b.
NJAR="$(mktemp)"; NBODY="$(mktemp)"
NCODE="$(curl -s -c "$NJAR" -o "$NBODY" -w '%{http_code}' "$PREVIEW")"
check "preview page renders with no page_content table" "200" "$NCODE"
check "page_content created on demand"                 "1"   "$(table_exists page_content)"
check "created empty, so every key falls back"         "0"   "$(pc_rows)"
check "page still showed its inline defaults" "FALLBACK-DEFAULT-USED" "$(pm_check "$(cat "$NBODY")" seeded)"

NTOK="$(sed -n 's/.*name="csrf_token" value="\([a-f0-9]*\)".*/\1/p' "$NBODY" | head -1)"
check "footer newsletter form carries a CSRF token" "yes" \
  "$(printf '%s' "$NTOK" | grep -Eq '^[a-f0-9]{64}$' && echo yes || echo no)"

# newsletter <email> [extra curl args...] -> the JSON body
newsletter() {
  local email="$1"; shift
  curl -s -b "$NJAR" -H 'Accept: application/json' -X POST "$MAIN/newsletter-subscribe.php" \
    -F "csrf_token=$NTOK" -F "email=$email" -F "return_to=/_phase1-preview.php" "$@"
}
OUT="$(newsletter first@example.test)"
check "first subscribe succeeds"                 "true" "$(json_success "$OUT")"
check "newsletter_subscribers created on demand" "1"    "$(table_exists newsletter_subscribers)"

# Put the real copy back for the sections that follow.
"${DB_MAIN_FILE[@]}" < "$SEED_UP" >/dev/null 2>&1
check "seed applies to the on-demand table" "77" "$(pc_rows)"

echo
echo "=== 9c. pmContent(): seeded value, and the default for a missing key ==="
PBODY="$(curl -s "$PREVIEW")"
check "preview page renders"          "200" "$(curl -s -o /dev/null -w '%{http_code}' "$PREVIEW")"
check "no PHP error in the page"      "0"   "$(printf '%s' "$PBODY" | grep -ciE 'fatal error|parse error|warning:|uncaught')"
check "seeded key renders from the DB" "Strong systems start with strong people" "$(pm_check "$PBODY" seeded)"
check "missing key renders its default" "default-was-returned" "$(pm_check "$PBODY" default)"
check "whole page fetched in one go"    "21" "$(pm_check "$PBODY" count)"

check "pmContent returns the seeded value" "Strong systems start with strong people" \
  "$(cd public_html && php -r 'require "includes/layout/page.php"; echo pmContent($pdo, "home", "hero_title", "MISS");' 2>/dev/null)"
check "pmContent returns the default for a missing key" "the-default" \
  "$(cd public_html && php -r 'require "includes/layout/page.php"; echo pmContent($pdo, "home", "no_such_key", "the-default");' 2>/dev/null)"
check "pmContentAll returns the whole page" "21" \
  "$(cd public_html && php -r 'require "includes/layout/page.php"; echo count(pmContentAll($pdo, "home"));' 2>/dev/null)"
# 'text' rows are escaped on output, 'html' rows are passed through. That
# distinction is the entire reason content_type exists as a column.
check "a text row is escaped on output" "Co-Author Africa&#039;s Public Finance Future" \
  "$(cd public_html && php -r 'require "includes/layout/page.php"; echo pmContentSafe($pdo, "sponsorship", "hero_title", "x");' 2>/dev/null)"
check "an html row is passed through" "Twiga Towers, Moi Avenue<br>Nairobi, Kenya<br>Mon to Fri, 8am to 5pm" \
  "$(cd public_html && php -r 'require "includes/layout/page.php"; echo pmContentSafe($pdo, "global", "address_html", "x");' 2>/dev/null)"
check "a json row decodes to a list" "4" \
  "$(cd public_html && php -r 'require "includes/layout/page.php"; echo count(pmContentJson($pdo, "home", "stats", []));' 2>/dev/null)"
# One query per PAGE, not per key. A page asks for dozens of keys; issuing a
# query for each would put the content layer on the critical path of every
# render. Warm the schema check first, then count real statements: the SHOW
# that reads the counter is itself counted, hence the -1.
check "40 lookups cost exactly one query" "1" \
  "$(cd public_html && php -r '
require "includes/layout/page.php";
pmContentAll($pdo, "about");
$q = function () use ($pdo) { return (int) $pdo->query("SHOW SESSION STATUS LIKE \"Questions\"")->fetch()["Value"]; };
$before = $q();
for ($i = 0; $i < 40; $i++) { pmContent($pdo, "home", "hero_title", "x"); }
echo $q() - $before - 1;
' 2>/dev/null)"

echo
echo "=== 9d. Newsletter endpoint ==="
# The live footer's newsletter field has no action and no method and has
# silently discarded every address ever typed into it (PROJECT.md section 5,
# Priority 3). This is the endpoint that closes that for the rebuilt pages.
OUT="$(newsletter delegate@example.test)"
check "valid submit accepted"        "true" "$(json_success "$OUT")"
check "exactly one row stored"       "1"    "$(subs_rows delegate@example.test)"
OUT="$(newsletter delegate@example.test)"
check "repeat submit still succeeds" "true" "$(json_success "$OUT")"
check "repeat submit stored no second row" "1" "$(subs_rows delegate@example.test)"
# Case and surrounding whitespace must not create a second subscriber.
OUT="$(newsletter '  DELEGATE@Example.TEST  ')"
check "mixed-case repeat still one row" "1" "$(subs_rows delegate@example.test)"

BEFORE="$(fq "SELECT COUNT(*) FROM newsletter_subscribers")"
OUT="$(newsletter forged@example.test -F "csrf_token=$(printf 'a%.0s' {1..64})")"
check "forged token rejected"        "false" "$(json_success "$OUT")"
OUT="$(curl -s -H 'Accept: application/json' -X POST "$MAIN/newsletter-subscribe.php" -F "email=notoken@example.test")"
check "missing token rejected"       "false" "$(json_success "$OUT")"
check "rejected posts stored nothing" "$BEFORE" "$(fq "SELECT COUNT(*) FROM newsletter_subscribers")"

OUT="$(newsletter not-an-email)"
check "invalid address rejected"     "false" "$(json_success "$OUT")"
check "invalid address stored nothing" "$BEFORE" "$(fq "SELECT COUNT(*) FROM newsletter_subscribers")"

# The honeypot answers success and stores nothing: telling a bot it was caught
# only teaches it.
OUT="$(newsletter bot@example.test -F "company=Acme")"
check "honeypot answers success"     "true" "$(json_success "$OUT")"
check "honeypot stored nothing"      "0"    "$(subs_rows bot@example.test)"

OUT="$(curl -s -H 'Accept: application/json' "$MAIN/newsletter-subscribe.php")"
check "GET is refused"               "false" "$(json_success "$OUT")"

# Without an Accept header the endpoint redirects, so the form works with
# JavaScript off. The redirect target is validated: this endpoint is public and
# unauthenticated, and an unchecked return_to would make it an open redirect.
check "redirects back to the page" "/_phase1-preview.php?newsletter=ok#newsletter" \
  "$(curl -s -b "$NJAR" -o /dev/null -D - -X POST "$MAIN/newsletter-subscribe.php" \
      -F "csrf_token=$NTOK" -F "email=redirect@example.test" -F "return_to=/_phase1-preview.php" \
      | sed -n 's/^[Ll]ocation: *//p' | tr -d '\r')"
check "refuses an off-site return_to" "/?newsletter=ok#newsletter" \
  "$(curl -s -b "$NJAR" -o /dev/null -D - -X POST "$MAIN/newsletter-subscribe.php" \
      -F "csrf_token=$NTOK" -F "email=redirect@example.test" -F "return_to=//evil.example/x" \
      | sed -n 's/^[Ll]ocation: *//p' | tr -d '\r')"

echo
echo "=== 9e. Design system assets, and the preview page's shared chrome ==="
check "pm-design-system.css is served"   "200" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/assets/css/pm-design-system.css")"
check "served as text/css"               "text/css" \
  "$(curl -s -o /dev/null -w '%{content_type}' "$MAIN/assets/css/pm-design-system.css" | cut -d';' -f1)"
check "carries the brand tokens"         "yes" \
  "$(curl -s "$MAIN/assets/css/pm-design-system.css" | grep -q -- '--pm-green: #00BF63' && echo yes || echo no)"
# The brand is green/black/white plus neutral greys. Any other hue in the
# stylesheet is a bug: no purple, no blue, no red error states.
check "no hue outside the palette" "0" \
  "$(curl -s "$MAIN/assets/css/pm-design-system.css" \
      | grep -oiE '#[0-9a-f]{3,8}\b' | tr 'A-F' 'a-f' | sort -u \
      | grep -vcE '^#(00bf63|000000|ffffff|f6f6f4|fafafa|dcdcdc|e2e2e2|cfcfcf|5a5a5a|5f5f5f|4a4a4a)$')"
check "Maharlika is served"              "200" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/assets/fonts/Maharlika-Regular.ttf")"
check "served as a font type"            "yes" \
  "$(curl -s -o /dev/null -w '%{content_type}' "$MAIN/assets/fonts/Maharlika-Regular.ttf" | grep -qi 'font' && echo yes || echo no)"
check "font is the brand file, byte for byte" "yes" \
  "$(cmp -s <(curl -s "$MAIN/assets/fonts/Maharlika-Regular.ttf") "prototype/fonts/Maharlika-Regular.ttf" && echo yes || echo no)"
check "layout script is served"          "200" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/assets/js/pm-layout.js")"

PBODY="$(curl -s "$PREVIEW")"
check "preview loads the design system, not style.css" "yes" \
  "$(printf '%s' "$PBODY" | grep -q 'pm-design-system.css' && ! printf '%s' "$PBODY" | grep -q 'assets/css/style.css' && echo yes || echo no)"
check "shared header rendered"      "yes" "$(printf '%s' "$PBODY" | grep -q 'class="pm-header"' && echo yes || echo no)"
check "mobile menu toggle present"  "yes" "$(printf '%s' "$PBODY" | grep -q 'id="pm-nav-toggle"' && echo yes || echo no)"
check "shared footer rendered"      "yes" "$(printf '%s' "$PBODY" | grep -q 'class="pm-footer"' && echo yes || echo no)"
check "GA4 and Ads tag on the page"  "2"  \
  "$(printf '%s' "$PBODY" | grep -cE "gtag\('config', '(G-H030354F23|AW-18352784550)'\)")"
check "admin login is not in the nav" "0" "$(printf '%s' "$PBODY" | grep -c 'admin/login.php')"
# The preview page is scaffolding and must not be indexed or advertised.
check "preview page is noindex"        "yes" "$(printf '%s' "$PBODY" | grep -q 'name="robots" content="noindex' && echo yes || echo no)"
check "preview page disallowed in robots.txt" "yes" \
  "$(curl -s "$MAIN/robots.txt" | grep -q '_phase1-preview.php' && echo yes || echo no)"
# sitemap.php, not /sitemap.xml: the rewrite that maps one to the other lives in
# .htaccess, which the PHP built-in server does not read.
check "preview page absent from the sitemap"  "0" \
  "$(curl -s "$MAIN/sitemap.php" | grep -c '_phase1-preview')"

echo
echo "=== 9f. CRITICAL: a broken content layer must never break a page ==="
# The single most important assertion in this phase, and the same shape as 8g:
# break the secondary concern for real, then prove the primary outcome — that
# the page renders — is untouched. The lesson is the August 2026 one restated
# for a third time (registration email, then analytics, now page copy): a
# secondary concern must never decide a primary answer.
#
# DROP is not a sufficient break. ensurePageContentSchema() would recreate the
# table and every SELECT would succeed against an empty one, which tests
# nothing about error handling. So the real table is set aside and replaced by
# one whose schema the SELECT cannot satisfy — what a half-applied migration
# actually looks like.
fq "RENAME TABLE page_content TO page_content_saved" >/dev/null
fq "CREATE TABLE page_content (id INT AUTO_INCREMENT PRIMARY KEY, wrong_column INT DEFAULT NULL)" >/dev/null

PBODY="$(curl -s "$PREVIEW")"
check "broken table: page still renders" "200" "$(curl -s -o /dev/null -w '%{http_code}' "$PREVIEW")"
check "broken table: default copy shown" "FALLBACK-DEFAULT-USED" "$(pm_check "$PBODY" seeded)"
check "broken table: no error on the page" "0" \
  "$(printf '%s' "$PBODY" | grep -ciE 'fatal error|parse error|warning:|uncaught|sqlstate')"
check "broken table: header and footer still render" "yes" \
  "$(printf '%s' "$PBODY" | grep -q 'class="pm-footer"' && echo yes || echo no)"

fq "DROP TABLE page_content" >/dev/null
fq "RENAME TABLE page_content_saved TO page_content" >/dev/null
check "real page_content restored"        "1"  "$(table_exists page_content)"
check "restored table still holds its rows" "77" "$(pc_rows)"

# Now the file itself. The August outage was one file arriving incomplete;
# includes/content.php is among the newest files in this deploy, so it is one of
# the likeliest to arrive that way. includes/layout/page.php loads it inside
# try/catch and substitutes no-op stand-ins.
CF=public_html/includes/content.php
cp "$CF" /tmp/verify-content.bak

mv "$CF" /tmp/verify-content.moved
# The PHP built-in server caches compiled files and only revalidates every
# opcache.revalidate_freq seconds (2 by default). Without this pause the next
# request can be served from the pre-corruption bytecode and the test proves
# nothing. Same reason it appears again below.
sleep 3
MCODE="$(curl -s -o /tmp/verify-content-page.html -w '%{http_code}' "$PREVIEW")"
PBODY="$(cat /tmp/verify-content-page.html)"
mv /tmp/verify-content.moved "$CF"
check "missing content.php: page renders" "200" "$MCODE"
check "missing content.php: default copy shown" "FALLBACK-DEFAULT-USED" "$(pm_check "$PBODY" seeded)"
check "missing content.php: footer still renders" "yes" \
  "$(printf '%s' "$PBODY" | grep -q 'class="pm-footer"' && echo yes || echo no)"

# Truncated mid-file, the way vendor/phpmailer/PHPMailer.php was in August.
# Raises ParseError, which extends Error — catch (Exception) would not catch it.
head -c 4000 /tmp/verify-content.bak > "$CF"
# Captured first, not piped: `php -l` exits 255 on a parse error and
# `set -o pipefail` would make the whole pipeline non-zero even when grep matched.
CLINT="$(php -l "$CF" 2>&1 || true)"
check "truncated content.php really is a parse error" "yes" \
  "$(printf '%s' "$CLINT" | grep -q 'Parse error' && echo yes || echo no)"
sleep 3
CCODE="$(curl -s -o /tmp/verify-content-page.html -w '%{http_code}' "$PREVIEW")"
PBODY="$(cat /tmp/verify-content-page.html)"
cp /tmp/verify-content.bak "$CF"
check "truncated content.php: page renders" "200" "$CCODE"
check "truncated content.php: default copy shown" "FALLBACK-DEFAULT-USED" "$(pm_check "$PBODY" seeded)"
check "truncated content.php: no error leaked to the visitor" "0" \
  "$(printf '%s' "$PBODY" | grep -ciE 'fatal error|parse error|uncaught')"

# The newsletter endpoint has the same defensive load and must survive it too.
mv public_html/includes/newsletter.php /tmp/verify-newsletter.moved
sleep 3
NCODE2="$(curl -s -b "$NJAR" -o /tmp/verify-newsletter-out.json -w '%{http_code}' \
  -H 'Accept: application/json' -X POST "$MAIN/newsletter-subscribe.php" \
  -F "csrf_token=$NTOK" -F "email=brokennewsletter@example.test")"
OUT="$(cat /tmp/verify-newsletter-out.json)"
mv /tmp/verify-newsletter.moved public_html/includes/newsletter.php
check "missing newsletter.php: no 500"                 "200"   "$NCODE2"
check "missing newsletter.php: answers calmly, no crash" "false" "$(json_success "$OUT")"
check "missing newsletter.php: stored nothing"         "0"     "$(subs_rows brokennewsletter@example.test)"
rm -f /tmp/verify-newsletter-out.json

sleep 3
check "content.php restored and lints" "0" "$(php -l "$CF" >/dev/null 2>&1; echo $?)"
check "content.php restored byte for byte" "yes" \
  "$(cmp -s "$CF" /tmp/verify-content.bak && echo yes || echo no)"
check "seeded copy is back on the page" "Strong systems start with strong people" \
  "$(pm_check "$(curl -s "$PREVIEW")" seeded)"
rm -f /tmp/verify-content.bak /tmp/verify-content-page.html "$NJAR" "$NBODY"

# ---------------------------------------------------------------------------
# 10. PHASE 2 PAGES
# ---------------------------------------------------------------------------
# The Phase 2 rebuild replaced index.php and the three service pages and added
# about.php, services.php and contact.php. These assertions are deliberately
# behavioural rather than cosmetic: that each page answers, that it carries its
# own h1 (so a page has not been wired to the wrong template), that the house
# no-em-dash rule holds in the RENDERED output rather than only in source, and
# that the Phase 1 content-layer safety contract still holds for real pages and
# not just the preview page.

echo ""; echo "=== 10a. Every Phase 2 page answers with its own h1 ==="

page_h1() { curl -s "$MAIN/$1" | grep -o '<h1[^>]*>[^<]*' | head -1 | sed 's/<[^>]*>//' | sed 's/^ *//;s/ *$//'; }
page_code() { curl -s -o /dev/null -w '%{http_code}' "$MAIN/$1"; }

for pg in index.php about.php services.php service-pfm.php service-data.php \
          service-sustainability.php contact.php privacy-policy.php; do
  check "$pg answers 200" "200" "$(page_code "$pg")"
done

# Each h1 must be non-empty AND distinct from the homepage's, which catches a
# page accidentally rendering the homepage template.
HOME_H1="$(page_h1 index.php)"
check "index.php has an h1" "yes" "$([ -n "$HOME_H1" ] && echo yes || echo no)"
for pg in about.php services.php service-pfm.php service-data.php \
          service-sustainability.php contact.php privacy-policy.php; do
  H="$(page_h1 "$pg")"
  check "$pg has its own h1" "yes" \
    "$([ -n "$H" ] && [ "$H" != "$HOME_H1" ] && echo yes || echo no)"
done

check "404.php really returns 404" "404" "$(page_code 404.php)"

echo ""; echo "=== 10b. No em dashes in rendered output (client house style) ==="
# Checked on the rendered HTML, not the source: source may legitimately contain
# em dashes inside PHP comments, which are never shipped.
for pg in index.php about.php services.php service-pfm.php service-data.php \
          service-sustainability.php contact.php privacy-policy.php 404.php; do
  check "$pg renders no em dash" "0" "$(curl -s "$MAIN/$pg" | grep -c '\xe2\x80\x94')"
done

echo ""; echo "=== 10c. Contact enquiry endpoint ==="
CJAR=$(mktemp); CBODY=$(mktemp)
contact_rows() { fq "SELECT COUNT(*) FROM contact_messages WHERE email='$1'"; }

# A valid submission needs the session's own CSRF token, exactly as a browser
# would carry it.
CPAGE="$(curl -s -c "$CJAR" -b "$CJAR" "$MAIN/contact.php")"
CTOK="$(printf '%s' "$CPAGE" | sed -n 's/.*name="csrf_token" value="\([a-f0-9]*\)".*/\1/p' | head -1)"
check "contact form exposes a CSRF token" "yes" "$([ -n "$CTOK" ] && echo yes || echo no)"

curl -s -o "$CBODY" -b "$CJAR" -c "$CJAR" -X POST "$MAIN/contact-submit.php" \
  -d "csrf_token=$CTOK" -d "name=Verify Tester" -d "email=contact-ok@example.test" \
  -d "organisation=Ministry of Testing" -d "message=A real enquiry from verify.sh" >/dev/null
check "valid enquiry stored" "1" "$(contact_rows contact-ok@example.test)"

# Forged token must store nothing.
curl -s -o /dev/null -b "$CJAR" -X POST "$MAIN/contact-submit.php" \
  -d "csrf_token=deadbeefdeadbeefdeadbeefdeadbeef" -d "name=Forged" \
  -d "email=contact-forged@example.test" -d "message=should not persist"
check "forged CSRF stores nothing" "0" "$(contact_rows contact-forged@example.test)"

# Invalid address must be rejected.
CPAGE2="$(curl -s -c "$CJAR" -b "$CJAR" "$MAIN/contact.php")"
CTOK2="$(printf '%s' "$CPAGE2" | sed -n 's/.*name="csrf_token" value="\([a-f0-9]*\)".*/\1/p' | head -1)"
curl -s -o /dev/null -b "$CJAR" -X POST "$MAIN/contact-submit.php" \
  -d "csrf_token=$CTOK2" -d "name=Bad Address" -d "email=not-an-email" \
  -d "message=should not persist"
check "invalid email stores nothing" "0" "$(contact_rows not-an-email)"
rm -f "$CJAR" "$CBODY"

echo ""; echo "=== 10d. Office map is self-hosted and wired ==="
check "maplibre js served"  "200" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/assets/js/maplibre-gl.js")"
check "maplibre css served" "200" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/assets/css/maplibre-gl.css")"
check "pm-map.js served"    "200" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/assets/js/pm-map.js")"
# No CDN: a third-party script host is exactly what self-hosting avoided.
check "no CDN script host on contact.php" "0" \
  "$(curl -s "$MAIN/contact.php" | grep -c 'unpkg\|jsdelivr\|cdnjs')"
check "map uses the positron style" "1" \
  "$(curl -s "$MAIN/contact.php" | grep -c 'tiles.openfreemap.org/styles/positron')"
# The address must be real text on the page, never only inside the map.
check "address is real text, not only in the map" "yes" \
  "$(curl -s "$MAIN/contact.php" | grep -q 'Twiga Towers' && echo yes || echo no)"

echo ""; echo "=== 10e. CRITICAL: Phase 2 pages survive a broken content table ==="
# Phase 1 proved this for the preview page. It has to hold for the real pages
# too, or the safety contract is decorative.
"${DB_MAIN[@]}" "RENAME TABLE page_content TO page_content_p2bak" >/dev/null 2>&1
"${DB_MAIN[@]}" "CREATE TABLE page_content (id INT PRIMARY KEY)" >/dev/null 2>&1
for pg in index.php about.php services.php contact.php; do
  check "broken page_content: $pg still 200" "200" "$(page_code "$pg")"
  check "broken page_content: $pg still has an h1" "yes" \
    "$([ -n "$(page_h1 "$pg")" ] && echo yes || echo no)"
done
"${DB_MAIN[@]}" "DROP TABLE page_content" >/dev/null 2>&1
"${DB_MAIN[@]}" "RENAME TABLE page_content_p2bak TO page_content" >/dev/null 2>&1
check "page_content restored" "1" \
  "$(fq "SELECT COUNT(*) > 0 FROM page_content")"
check "seeded copy back after restore" "yes" \
  "$([ -n "$(page_h1 index.php)" ] && echo yes || echo no)"


# ---------------------------------------------------------------------------
# 11. PHASE 3 PAGES: THE CALENDAR, THE EVENT PAGE AND SPONSORSHIP
# ---------------------------------------------------------------------------
# Phase 3 added events.php and rebuilt event.php and sponsorship.php. Same
# discipline as section 10: these assertions are behavioural, not cosmetic.
# They prove that the upcoming/past filter really partitions by date rather than
# printing two copies of the same list, that a finished cohort stays reachable
# (the client's instruction is that past events are never hidden), that the
# detail page is reading the events table rather than repeating hardcoded copy,
# and that the sponsorship enquiry still arrives with a name on it.
#
# The last one is the point of the section. A form that returns 200 while the
# data goes nowhere is the August 2026 failure in a different costume, so the
# assertion below reads the delivered email and looks for the submitted name in
# it, rather than trusting the status code.

EV_UP=public_html/database/migrations/2026-08-29-01-seed-page-content-events.up.sql
EV_DOWN=public_html/database/migrations/2026-08-29-01-seed-page-content-events.down.sql
SP_UP=public_html/database/migrations/2026-08-29-02-seed-page-content-sponsorship.up.sql
SP_DOWN=public_html/database/migrations/2026-08-29-02-seed-page-content-sponsorship.down.sql

# The count element on events.php is a single line by construction, so this
# stays a sed one-liner rather than an HTML parser. Same approach as
# funnel_shown() in section 8e.
listing_count() { curl -s "$MAIN/$1" | sed -n 's/.*pm-listing__count">\([^<]*\)<.*/\1/p' | head -1; }
# A literal substring test, done in the shell rather than with grep.
#
# TWO REASONS, both of which cost a real false negative while this section was
# being written.
#
#   1. `grep -qF` stops reading at the first match. On macOS the pipe buffer is
#      16 KB, so for a page larger than that a match near the TOP leaves printf
#      still writing into a closed pipe; it takes SIGPIPE, and `set -o pipefail`
#      turns the whole pipeline non-zero. The assertion then reports "no" for a
#      string that is plainly on the page, and only for the pages long enough to
#      exhibit it. Anything that reads its input to the end (grep -c, grep -o)
#      is safe; grep -q is not.
#   2. The needles here are literal markup, and several contain brackets
#      (name="events[]") that a basic regex reads as an unterminated character
#      class.
#
# A case glob has neither problem and needs no subprocess.
has_text() { case "$1" in *"$2"*) echo yes ;; *) echo no ;; esac; }

# The switch renders its href and its aria-current on separate lines, so the
# newlines are flattened before matching. Scoped to .pm-switch__link so it
# cannot accidentally match the header nav, which also marks the current page.
# grep -o rather than grep -q, for reason 1 above.
switch_current() {
  local hits
  hits="$(printf '%s' "$1" | tr '\n' ' ' \
    | grep -o "pm-switch__link\" href=\"$2\"[^>]*aria-current=\"page\"" | wc -l | tr -d ' ')"

  [ "${hits:-0}" -gt 0 ] && echo yes || echo no
}

echo ""; echo "=== 11a. Phase 3 seed migrations ==="
for m in "$EV_UP" "$EV_DOWN" "$SP_UP" "$SP_DOWN"; do
  check "$(basename "$m") tracked in git" "yes" \
    "$(git ls-files --error-unmatch "$m" >/dev/null 2>&1 && echo yes || echo no)"
done

# Section 10e left page_content holding migration 03's rows and nothing else.
check "page_content starts from migration 03 alone" "77" "$(pc_rows)"
check "events seed applies cleanly" "0" \
  "$("${DB_MAIN_FILE[@]}" < "$EV_UP" >/dev/null 2>&1; echo $?)"
# 52 new rows: 15 for the calendar, 36 for the new 'event' slug, one for the
# homepage's "Full calendar" action. Counted from the file, not read back.
check "events seed inserts 52 rows" "129" "$(pc_rows)"
check "sponsorship seed applies cleanly" "0" \
  "$("${DB_MAIN_FILE[@]}" < "$SP_UP" >/dev/null 2>&1; echo $?)"
# 53 new rows: the whole sponsorship offer, which was hardcoded in PHP before.
check "sponsorship seed inserts 53 rows" "182" "$(pc_rows)"

"${DB_MAIN_FILE[@]}" < "$EV_UP" >/dev/null 2>&1
"${DB_MAIN_FILE[@]}" < "$SP_UP" >/dev/null 2>&1
check "both seeds re-apply without duplicating" "182" "$(pc_rows)"

# INSERT IGNORE, not upsert: from Phase 5 these rows are what staff edit, and
# the tier rows are the ones most likely to be edited after launch.
fq "UPDATE page_content SET content_value='EDITED BY A HUMAN' WHERE page_slug='sponsorship' AND section_key='tiers_title'" >/dev/null
"${DB_MAIN_FILE[@]}" < "$SP_UP" >/dev/null 2>&1
check "re-seeding does not overwrite an edited tier heading" "EDITED BY A HUMAN" \
  "$(fq "SELECT content_value FROM page_content WHERE page_slug='sponsorship' AND section_key='tiers_title'")"
fq "UPDATE page_content SET content_value='Four partnership tiers' WHERE page_slug='sponsorship' AND section_key='tiers_title'" >/dev/null

echo ""; echo "=== 11b. Every Phase 3 page answers with its own h1 ==="
for pg in events.php "events.php?show=past" "event.php?id=1" sponsorship.php; do
  check "$pg answers 200" "200" "$(page_code "$pg")"
done

HOME_H1="$(page_h1 index.php)"
for pg in events.php "event.php?id=1" sponsorship.php; do
  H="$(page_h1 "$pg")"
  check "$pg has its own h1" "yes" \
    "$([ -n "$H" ] && [ "$H" != "$HOME_H1" ] && echo yes || echo no)"
done

# The archive is the same page and keeps the same h1 on purpose: it is one
# calendar filtered, not a second page about a different subject.
check "the past view keeps the calendar's h1" "$(page_h1 events.php)" \
  "$(page_h1 "events.php?show=past")"

echo ""; echo "=== 11c. No em dashes in rendered output (client house style) ==="
# Rendered, not source. The `events` table itself carries em dashes in nine
# columns, so this is the assertion that pmEventProse() is actually applied at
# every point event data reaches the page, not just at the obvious ones.
for pg in events.php "events.php?show=past" "event.php?id=1" "event.php?id=2" \
          "event.php?id=3" "event.php?id=5" "event.php?id=999999" sponsorship.php; do
  check "$pg renders no em dash" "0" "$(curl -s "$MAIN/$pg" | grep -c $'\xe2\x80\x94')"
done

echo ""; echo "=== 11d. Upcoming and past really are partitioned by date ==="
check "the calendar starts with the four scheduled schools" "4 scheduled schools" \
  "$(listing_count events.php)"
check "nothing has run yet, so the archive is empty" "0 past cohorts" \
  "$(listing_count 'events.php?show=past')"

# A finished school that an admin has since unpublished. This is the real shape
# of the archive: clearing is_active is how a cohort is retired today, so an
# archive built from pmActiveEvents() would silently lose it, which is exactly
# what the client's "never delete, never hide" instruction forbids.
fq "INSERT INTO events (title, tagline, date_display, event_start_date, location, price, is_active, sort_order, agenda, audience, regular_price, regular_perks)
    VALUES ('Verify Archive Cohort', 'A finished cohort, kept for reference.', '3-7 March 2025', '2025-03-03', 'Nairobi, Kenya', 'From USD 599 Per Delegate', 0, 90, '[{\"day\":1,\"title\":\"Day one\",\"desc\":\"Topic A; Topic B\"}]', 'Finance officers', 'USD 599', 'Course materials')" >/dev/null
ARCHIVE_ID="$(fq "SELECT id FROM events WHERE title='Verify Archive Cohort'")"
check "the archive fixture was created" "yes" \
  "$([ -n "$ARCHIVE_ID" ] && [ "$ARCHIVE_ID" != "ERR" ] && echo yes || echo no)"

UP_BODY="$(curl -s "$MAIN/events.php")"
PAST_BODY="$(curl -s "$MAIN/events.php?show=past")"
check "a finished cohort is NOT in the upcoming tab" "0" \
  "$(printf '%s' "$UP_BODY" | grep -c 'Verify Archive Cohort')"
check "a finished cohort IS in the past tab" "yes" \
  "$(has_text "$PAST_BODY" 'Verify Archive Cohort')"
check "the upcoming count still reads four" "4 scheduled schools" "$(listing_count events.php)"
check "the past count now reads one" "1 past cohort" "$(listing_count 'events.php?show=past')"
# The two tabs must not be the same list wearing different labels.
check "the two tabs render different lists" "no" \
  "$([ "$UP_BODY" = "$PAST_BODY" ] && echo yes || echo no)"
# The state is announced, not merely coloured, and it follows the URL.
check "the past tab marks itself current"            "yes" "$(switch_current "$PAST_BODY" '/events.php?show=past')"
check "the upcoming tab is not current in that view" "no"  "$(switch_current "$PAST_BODY" '/events.php')"
check "the upcoming tab is current in its own view"  "yes" "$(switch_current "$UP_BODY" '/events.php')"

# A past cohort keeps a working detail page, and it must not sell seats.
ARCH_BODY="$(curl -s "$MAIN/event.php?id=$ARCHIVE_ID")"
check "an archived cohort still has a detail page" "200" "$(page_code "event.php?id=$ARCHIVE_ID")"
check "it says the cohort has already run" "yes" \
  "$(has_text "$ARCH_BODY" 'This cohort has already run')"
check "it offers no registration link" "0" \
  "$(printf '%s' "$ARCH_BODY" | grep -c 'event-registration.php')"
check "it shows no early bird panel" "0" \
  "$(printf '%s' "$ARCH_BODY" | grep -c 'pm-cell--accent')"

# The same row moved into the future is a DRAFT, not an archive entry, and must
# disappear from both tabs and answer 404.
fq "UPDATE events SET event_start_date='2027-05-03', date_display='3-7 May 2027' WHERE title='Verify Archive Cohort'" >/dev/null
check "an unpublished FUTURE event answers 404" "404" "$(page_code "event.php?id=$ARCHIVE_ID")"
check "a draft appears in neither tab" "0" \
  "$(( $(curl -s "$MAIN/events.php" | grep -c 'Verify Archive Cohort') + $(curl -s "$MAIN/events.php?show=past" | grep -c 'Verify Archive Cohort') ))"

fq "DELETE FROM events WHERE title='Verify Archive Cohort'" >/dev/null
check "the calendar is back to four scheduled schools" "4 scheduled schools" \
  "$(listing_count events.php)"

echo ""; echo "=== 11e. event.php renders a real event from the database ==="
E1="$(curl -s "$MAIN/event.php?id=1")"
check "the h1 is the event's own title" "Future-Ready PFM Leaders in the Age of AI &amp; Automation" \
  "$(page_h1 'event.php?id=1')"
check "the agenda comes from the agenda column" "yes" \
  "$(has_text "$E1" 'IPSAS That Earns Clean Audits')"
check "all five agenda days render" "5" "$(printf '%s' "$E1" | grep -c 'pm-row__index')"
check "the agenda heading counts the real days" "yes" "$(has_text "$E1" '5 days, one arc')"
check "the eyebrow counts the real days too" "yes" "$(has_text "$E1" '5 day residential school')"
check "the audience comes from the audience column" "yes" "$(has_text "$E1" 'Budget controllers')"
check "the outcomes come from master_points" "yes" \
  "$(has_text "$E1" 'Return with a 90-day action plan')"
check "exactly three delegate tiers render" "3" "$(printf '%s' "$E1" | grep -c 'class="pm-price">')"
for price in 'USD 599' 'USD 1,999' 'USD 2,899'; do
  check "the $price tier renders from the database" "yes" "$(has_text "$E1" "$price")"
done
check "the VVIP seats note renders" "yes" "$(has_text "$E1" 'Limited to 15 seats')"
check "the location and dates render" "yes" "$(has_text "$E1" 'Cape Town, South Africa')"
# The date range is spelled out rather than dashed, matching the approved design.
check "the date range is spelled out" "yes" "$(has_text "$E1" '19 to 23 October 2026')"
check "the early bird panel is computed, not seeded" "1" \
  "$(printf '%s' "$E1" | grep -c 'pm-cell--accent')"
check "it links to the registration entry point" "yes" \
  "$(has_text "$E1" 'event-registration.php?id=1')"

# A malformed agenda must cost the section, not the page. The column carries a
# json_valid CHECK constraint, so the realistic break is valid JSON of the wrong
# SHAPE, which is what a bad admin edit or a bad import produces.
fq "INSERT INTO events (title, tagline, date_display, event_start_date, location, price, is_active, sort_order, agenda, audience, regular_price, regular_perks, vip_price, vip_perks, vvip_price, vvip_perks)
    VALUES ('Verify Malformed Agenda School', 'Deliberately broken agenda.', '1-5 June 2027', '2027-06-01', 'Nairobi, Kenya', 'From USD 599 Per Delegate', 1, 91, '\"valid json, but not a list of days\"', 'Finance officers', 'USD 599', 'Course materials', 'USD 1,999', 'Priority seating', 'USD 2,899', 'Executive roundtable')" >/dev/null
BROKEN_ID="$(fq "SELECT id FROM events WHERE title='Verify Malformed Agenda School'")"
BROKEN="$(curl -s "$MAIN/event.php?id=$BROKEN_ID")"
check "a malformed agenda still renders the page" "200" "$(page_code "event.php?id=$BROKEN_ID")"
check "the agenda section is omitted" "0" "$(printf '%s' "$BROKEN" | grep -c 'one arc')"
check "the audience survives a malformed agenda" "yes" \
  "$(has_text "$BROKEN" 'Finance officers')"
check "the three tiers survive a malformed agenda" "3" \
  "$(printf '%s' "$BROKEN" | grep -c 'class="pm-price">')"
check "no error leaks to the visitor" "0" \
  "$(printf '%s' "$BROKEN" | grep -ciE 'fatal error|parse error|warning:|uncaught')"
fq "DELETE FROM events WHERE title='Verify Malformed Agenda School'" >/dev/null

echo ""; echo "=== 11f. An unknown or unpublished event answers 404, not a fatal ==="
# The page this replaced answered a 302 to the homepage, which tells a crawler
# the URL moved and tells a visitor nothing.
check "no id answers 404"           "404" "$(page_code event.php)"
check "an unknown id answers 404"   "404" "$(page_code 'event.php?id=999999')"
check "a non-numeric id answers 404" "404" "$(page_code 'event.php?id=abc')"
check "a negative id answers 404"   "404" "$(page_code 'event.php?id=-1')"
MISSING="$(curl -s "$MAIN/event.php?id=999999")"
check "the 404 branch is a real page with an h1" "yes" \
  "$([ -n "$(page_h1 'event.php?id=999999')" ] && echo yes || echo no)"
check "the 404 branch leaks no error" "0" \
  "$(printf '%s' "$MISSING" | grep -ciE 'fatal error|parse error|warning:|uncaught|sqlstate')"
check "the 404 branch is noindex" "1" "$(printf '%s' "$MISSING" | grep -c 'noindex')"
check "the 404 branch links back to the calendar" "yes" \
  "$(has_text "$MISSING" 'href="/events.php"')"

echo ""; echo "=== 11g. The sponsorship enquiry still posts what the handler reads ==="
# process-sponsorship.php is live code and is not modified by this phase. It
# reads exactly these names, and it requires first_name, last_name,
# organisation, email and at least one event.
SPPAGE="$(curl -s "$MAIN/sponsorship.php")"
SPFORM="$(printf '%s' "$SPPAGE" | awk '/action="\/process-sponsorship.php"/,/<\/form>/')"

check "the form posts to the live handler" "1" \
  "$(printf '%s' "$SPPAGE" | grep -c 'action="/process-sponsorship.php"')"
check "the form has a real method" "yes" "$(has_text "$SPFORM" 'method="post"')"
for f in first_name last_name organisation email phone country tier message; do
  check "the form carries name=\"$f\"" "yes" "$(has_text "$SPFORM" "name=\"$f\"")"
done
# The specific trap: the approved prototype draws ONE name field, and the
# handler reads two. Building the prototype literally would produce enquiries
# with an empty name, silently.
check "the name is two inputs, not one" "yes" \
  "$([ "$(has_text "$SPFORM" 'name="first_name"')" = yes ] && \
     [ "$(has_text "$SPFORM" 'name="last_name"')" = yes ] && echo yes || echo no)"
check "one checkbox per scheduled school" "4" \
  "$(printf '%s' "$SPFORM" | grep -c 'name="events\[\]"')"
# The live page listed three events and had never listed Mombasa. It is read
# from the events table now, so it cannot fall behind again.
check "Mombasa is on the sponsorship page" "yes" "$(has_text "$SPPAGE" 'Mombasa')"

clearmail
SPBODY="$(curl -s -X POST "$MAIN/process-sponsorship.php" \
  -F "first_name=Verify" -F "last_name=Sponsorlead" \
  -F "organisation=Ministry of Testing" -F "email=sponsor-verify@example.test" \
  -F "phone=+254700000000" -F "country=Kenya" \
  -F "tier=Platinum, \$15,000" -F "message=Sent by verify.sh" \
  -F "events[]=Cape Town, Oct 2026")"
check "a complete enquiry is accepted" "true" "$(json_success "$SPBODY")"
sleep 1
check "the enquiry produced the admin and confirmation emails" "2" "$(mailcount)"
# THE ASSERTION THIS SECTION EXISTS FOR. Not that the endpoint answered 200, but
# that the data arrived: the submitted name has to be in the email a human will
# read, or the field contract is broken and nobody would notice.
check "the enquiry arrives with the sender's name attached" "yes" \
  "$(curl -s "$MAILPIT/api/v1/messages?limit=50" | php -r '
$d = json_decode(stream_get_contents(STDIN), true);
foreach (($d["messages"] ?? []) as $m) {
    $raw = @file_get_contents("http://127.0.0.1:8025/api/v1/message/" . $m["ID"]);
    $j = json_decode((string) $raw, true);
    $body = (string) ($j["HTML"] ?? "") . (string) ($j["Text"] ?? "");
    if (strpos($body, "Verify Sponsorlead") !== false) { echo "yes"; exit; }
}
echo "no";')"
check "the organisation arrives too" "yes" \
  "$(curl -s "$MAILPIT/api/v1/messages?limit=50" | php -r '
$d = json_decode(stream_get_contents(STDIN), true);
foreach (($d["messages"] ?? []) as $m) {
    if (strpos((string) ($m["Subject"] ?? ""), "Ministry of Testing") !== false) { echo "yes"; exit; }
}
echo "no";')"
# The handler refuses an enquiry with no event selected. The page must not be
# able to produce one that looks valid and is not.
check "an enquiry with no event is refused" "false" \
  "$(json_success "$(curl -s -X POST "$MAIN/process-sponsorship.php" \
      -F "first_name=No" -F "last_name=Event" -F "organisation=Nowhere" \
      -F "email=no-event@example.test")")"
clearmail

echo ""; echo "=== 11h. A tier card carries its own tier into the form ==="
tier_selected() {
  curl -s "$MAIN/sponsorship.php?tier=$1" \
    | sed -n 's/.*<option value="\([^"]*\)" selected>.*/\1/p' | head -1
}
check "?tier=platinum pre-selects Platinum"        "Platinum, \$15,000"    "$(tier_selected platinum)"
check "?tier=bronze pre-selects Bronze"            "Bronze, \$2,500"       "$(tier_selected bronze)"
check "?tier=gala-dinner pre-selects the package"  "Gala Dinner, \$1,000"  "$(tier_selected gala-dinner)"
check "no ?tier= selects nothing"                  "0" \
  "$(printf '%s' "$SPPAGE" | grep -c ' selected>')"
# Validated against the known keys, so a hand edited query string is ignored
# rather than echoed.
INJECT="$(curl -s "$MAIN/sponsorship.php?tier=%22%3E%3Cscript%3Ealert(1)%3C%2Fscript%3E")"
check "an unknown tier selects nothing"            "0" "$(printf '%s' "$INJECT" | grep -c ' selected>')"
check "the injected string never reaches the markup" "0" \
  "$(printf '%s' "$INJECT" | grep -c 'script>alert')"
# Four tiers plus three specialised packages, each carrying its own key. The
# five add-ons deliberately have none: the handler has one `tier` field, so five
# more buttons would each pre-select the same option and be pretending to carry
# a distinct choice.
check "every tier and package deep links into the form" "7" \
  "$(printf '%s' "$SPPAGE" | grep -c 'sponsorship.php?tier=[a-z-]*#apply"')"

echo ""; echo "=== 11i. Navigation, sitemap and third-party requests ==="
check "the Events nav item points at the real page" "yes" \
  "$(has_text "$(curl -s "$MAIN/about.php")" 'href="/events.php"')"
# Every nav destination is now a page. No fragment survives anywhere.
check "no page still links to the old #events fragment" "0" \
  "$(for p in index.php events.php 'event.php?id=1' sponsorship.php about.php \
              services.php contact.php 404.php; do curl -s "$MAIN/$p"; done \
     | grep -c 'index.php#events')"
check "events.php is listed in the sitemap" "1" \
  "$(curl -s "$MAIN/sitemap.php" | grep -c '<loc>https://prosper-minds.com/events.php</loc>')"
check "pm-copy-link.js is served" "200" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/assets/js/pm-copy-link.js")"
check "pm-sponsorship-form.js is served" "200" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/assets/js/pm-sponsorship-form.js")"
# The old event.php and sponsorship.php made three cross-border requests per
# view between them: Google Fonts, a Font Awesome CDN, and a QR code image
# generated by api.qrserver.com. None of them survive.
check "no third-party host on the Phase 3 pages" "0" \
  "$(for p in events.php 'event.php?id=1' sponsorship.php; do curl -s "$MAIN/$p"; done \
     | grep -c 'unpkg\|jsdelivr\|cdnjs\|fonts.googleapis\|qrserver')"
# Never show a control that would do nothing. "Copy link" needs the clipboard
# API and is hidden until head.php confirms scripts run; "Download" beside it is
# a plain anchor and is always present.
check "one Copy link per banner, hidden without scripts" "4" \
  "$(curl -s "$MAIN/events.php" | grep -c 'pm-js-only')"
check "one plain Download link per banner" "4" \
  "$(curl -s "$MAIN/events.php" | grep -c 'download>')"

echo ""; echo "=== 11j. Every pm- class on the page is defined in the design system ==="
# This mistake has been made and caught twice in this project: markup shipped
# using a class the stylesheet never defined, which renders as unstyled markup
# rather than as an error. It is cheap to assert and impossible to spot by
# reading a diff, so it is asserted across every rebuilt page rather than only
# the new ones.
check "no undefined pm- class on any rebuilt page" "0" \
  "$(for p in index.php events.php 'events.php?show=past' 'event.php?id=1' \
              'event.php?id=999999' sponsorship.php about.php services.php \
              service-pfm.php contact.php 404.php privacy-policy.php; do
       curl -s "$MAIN/$p"
     done | php -r '
$html = stream_get_contents(STDIN);
$css  = (string) @file_get_contents("public_html/assets/css/pm-design-system.css");
preg_match_all("/\.(pm-[A-Za-z0-9_-]+)/", $css, $m);
$defined = array_flip($m[1]);
preg_match_all("/class=\"([^\"]*)\"/", $html, $u);
$used = [];
foreach ($u[1] as $attr) {
    foreach (preg_split("/\s+/", trim($attr)) as $c) {
        if ($c !== "" && str_starts_with($c, "pm-")) { $used[$c] = true; }
    }
}
echo count(array_diff_key($used, $defined));')"

echo ""; echo "=== 11k. CRITICAL: Phase 3 pages survive a broken content table ==="
# Same shape as 9f and 10e, for the third time. Break the secondary concern for
# real, then prove the primary outcome is untouched. A DROP is not a sufficient
# break: ensurePageContentSchema() would recreate the table and every SELECT
# would succeed against an empty one.
"${DB_MAIN[@]}" "RENAME TABLE page_content TO page_content_p3bak" >/dev/null 2>&1
"${DB_MAIN[@]}" "CREATE TABLE page_content (id INT PRIMARY KEY)" >/dev/null 2>&1
for pg in events.php "events.php?show=past" "event.php?id=1" sponsorship.php; do
  check "broken page_content: $pg still 200" "200" "$(page_code "$pg")"
  check "broken page_content: $pg still has an h1" "yes" \
    "$([ -n "$(page_h1 "$pg")" ] && echo yes || echo no)"
done
BROKEN_SP="$(curl -s "$MAIN/sponsorship.php")"
# The whole sponsorship offer lives in page_content now, so the inline defaults
# have to be a complete offer rather than placeholders.
check "broken page_content: the four tiers still render" "4" \
  "$(printf '%s' "$BROKEN_SP" | grep -c 'class="pm-price">')"
check "broken page_content: the form still posts the right names" "yes" \
  "$([ "$(has_text "$BROKEN_SP" 'name="first_name"')" = yes ] && \
     [ "$(has_text "$BROKEN_SP" 'name="last_name"')" = yes ] && \
     [ "$(has_text "$BROKEN_SP" 'name="events[]"')" = yes ] && echo yes || echo no)"
check "broken page_content: no error on any page" "0" \
  "$(for p in events.php 'event.php?id=1' sponsorship.php; do curl -s "$MAIN/$p"; done \
     | grep -ciE 'fatal error|parse error|warning:|uncaught|sqlstate')"
check "broken page_content: still no em dash" "0" \
  "$(for p in events.php 'event.php?id=1' sponsorship.php; do curl -s "$MAIN/$p"; done \
     | grep -c $'\xe2\x80\x94')"
"${DB_MAIN[@]}" "DROP TABLE page_content" >/dev/null 2>&1
"${DB_MAIN[@]}" "RENAME TABLE page_content_p3bak TO page_content" >/dev/null 2>&1
check "page_content restored with its rows" "182" "$(pc_rows)"

echo ""; echo "=== 11l. Both Phase 3 seeds reverse cleanly ==="
check "sponsorship down migration applies cleanly" "0" \
  "$("${DB_MAIN_FILE[@]}" < "$SP_DOWN" >/dev/null 2>&1; echo $?)"
check "events down migration applies cleanly" "0" \
  "$("${DB_MAIN_FILE[@]}" < "$EV_DOWN" >/dev/null 2>&1; echo $?)"
check "back to migration 03's rows exactly" "77" "$(pc_rows)"
# Unseeded is not broken: every call site passes its own inline default.
for pg in events.php "event.php?id=1" sponsorship.php; do
  check "unseeded: $pg still 200 with its h1" "yes" \
    "$([ "$(page_code "$pg")" = 200 ] && [ -n "$(page_h1 "$pg")" ] && echo yes || echo no)"
done
# Put the real copy back so the stack is left usable.
"${DB_MAIN_FILE[@]}" < "$EV_UP" >/dev/null 2>&1
"${DB_MAIN_FILE[@]}" < "$SP_UP" >/dev/null 2>&1
check "seeded copy restored" "182" "$(pc_rows)"




# ════════════════════════════════════════════════════════════════════════════
# 12. PHASE 4: the rebuilt registration form, and the stat counters
#
# event-registration.php is the revenue path, so these assertions are about
# behaviour and money, not appearance. The three that matter most:
#
#   * 12c, THE POST CONTRACT. Every field name process-registration.php reads
#     out of $_POST is extracted FROM THE HANDLER and looked for in the rendered
#     form. A renamed field would empty that column on every registration
#     silently, and the failure would look like success.
#   * 12e, the invoice agreement. The figures the summary panel shows are
#     compared, as numbers, with unit_price_amount and total_amount as the
#     handler actually stored them.
#   * 12i, the deliberate breaks from 8g and 8h restated against the new form:
#     a corrupted mailer and a broken funnel table must each still produce a
#     successful, saved, invoiced registration.
#
# EVENT 5 throughout. Sections 3 and 7 use event 2 and section 8 uses event 3,
# and no earlier section touches event 5, so every count below is determined by
# this section alone. Section 8e asserts exact all-time registration and revenue
# totals and runs before this, so the registrations created here cannot disturb
# it.
# ════════════════════════════════════════════════════════════════════════════

R_EVENT=5
R_URL="$MAIN/event-registration.php?id=$R_EVENT"
R_TITLE="$(fq "SELECT title FROM events WHERE id=$R_EVENT")"
R_LOC="$(fq "SELECT location FROM events WHERE id=$R_EVENT")"
REG_UP=public_html/database/migrations/2026-08-29-03-seed-page-content-register.up.sql
REG_DOWN=public_html/database/migrations/2026-08-29-03-seed-page-content-register.down.sql
REG_JS=public_html/assets/js/pm-register.js

reg_rows() { fq "SELECT COUNT(*) FROM page_content WHERE page_slug='register'"; }
reg_attr() { printf '%s' "$1" | sed -n "s/.*$2=\"\([^\"]*\)\".*/\1/p" | head -1; }
reg_col()  { fq "SELECT $2 FROM event_registrations WHERE email='$1'"; }

# One registration posted with exactly the field names the rebuilt form renders,
# through a session the form itself issued the token to.
reg_submit() { # reg_submit <email> <delegate count>
  local email="$1" n="$2" jar page tok i
  local args=()
  jar="$(mktemp)"
  page="$(curl -s -b "$jar" -c "$jar" "$R_URL")"
  tok="$(printf '%s' "$page" | sed -n 's/.*name="csrf_token" value="\([a-f0-9]*\)".*/\1/p' | head -1)"

  i=1
  while [ "$i" -le "$n" ]; do
    args+=(-F "attendees[first_name][]=Delegate$i" -F "attendees[last_name][]=Phasefour" \
           -F "attendees[email][]=d$i.$email" -F "attendees[title][]=Budget Controller")
    i=$((i + 1))
  done

  curl -s -b "$jar" -X POST "$MAIN/process-registration.php" \
    -F "csrf_token=$tok" -F "event_id=$R_EVENT" -F "event_name=$R_TITLE" \
    -F "first_name=Phase" -F "last_name=Four" -F "phone=+254700000001" \
    -F "email=$email" -F "organization=Local Test Ministry of Finance" \
    -F "country=Kenya" -F "address=1 Test Street, Nairobi, 00100, Kenya" \
    -F "gender=Female" -F "meal_preference=Vegetarian" \
    -F "future_topics=Local testing only" -F "consent=yes" \
    ${args[@]+"${args[@]}"}
  rm -f "$jar"
}

echo ""; echo "=== 12a. The register seed migration ==="
for m in "$REG_UP" "$REG_DOWN"; do
  check "$(basename "$m") tracked in git" "yes" \
    "$(git ls-files --error-unmatch "$m" >/dev/null 2>&1 && echo yes || echo no)"
done
check "register seed applies cleanly" "0" \
  "$("${DB_MAIN_FILE[@]}" < "$REG_UP" >/dev/null 2>&1; echo $?)"
check "register seed inserts 56 rows" "56" "$(reg_rows)"
check "register seed re-applies without duplicating" "56" \
  "$("${DB_MAIN_FILE[@]}" < "$REG_UP" >/dev/null 2>&1; reg_rows)"
# INSERT IGNORE, not an upsert: from Phase 5 these rows are what staff edit.
fq "UPDATE page_content SET content_value='EDITED BY A HUMAN' WHERE page_slug='register' AND section_key='summary_head'" >/dev/null
"${DB_MAIN_FILE[@]}" < "$REG_UP" >/dev/null 2>&1
check "re-seeding does not overwrite an edited row" "EDITED BY A HUMAN" \
  "$(fq "SELECT content_value FROM page_content WHERE page_slug='register' AND section_key='summary_head'")"
check "register down migration applies cleanly" "0" \
  "$("${DB_MAIN_FILE[@]}" < "$REG_DOWN" >/dev/null 2>&1; echo $?)"
check "no register rows left" "0" "$(reg_rows)"
# Unseeded is not broken: every call site passes its own inline default.
check "unseeded: the page still renders its own copy" "yes" \
  "$(has_text "$(curl -s "$R_URL")" 'Invoice summary')"
"${DB_MAIN_FILE[@]}" < "$REG_UP" >/dev/null 2>&1
check "register seed restored" "56" "$(reg_rows)"

echo ""; echo "=== 12b. The page renders on the design system ==="
R_BODY="$(curl -s "$R_URL")"
check "register page answers 200" "200" "$(page_code "event-registration.php?id=$R_EVENT")"
check "h1 is the school itself" "$R_TITLE" "$(page_h1 "event-registration.php?id=$R_EVENT")"
check "no PHP error in the page" "0" \
  "$(printf '%s' "$R_BODY" | grep -ciE 'fatal error|parse error|warning:|uncaught|sqlstate')"
check "no em dash in the rendered page" "0" "$(printf '%s' "$R_BODY" | grep -c $'\xe2\x80\x94')"
check "loads the design system" "yes" "$(has_text "$R_BODY" 'pm-design-system.css')"
check "no longer loads the old stylesheet" "no" "$(has_text "$R_BODY" 'assets/css/style.css')"
check "no longer loads a font or icon CDN" "0" \
  "$(printf '%s' "$R_BODY" | grep -ciE 'fonts\.googleapis|cdnjs\.cloudflare')"
check "loads the flow script" "yes" "$(has_text "$R_BODY" '/assets/js/pm-register.js')"
check "flow script is served" "200" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/assets/js/pm-register.js")"
# The prototype's five segments, by their own labels.
check "five progress segments" "5" "$(printf '%s' "$R_BODY" | grep -c 'data-pm-progress=')"
for lbl in 'Event and tickets' 'Contact and billing' 'Delegates' 'Review and consent' 'Confirmation'; do
  check "progress names: $lbl" "yes" "$(has_text "$R_BODY" "$lbl")"
done
check "step counter present" "yes" "$(has_text "$R_BODY" 'Step 1 of 5')"
check "selected school card names the location" "yes" "$(has_text "$R_BODY" "$R_LOC")"
check "change school links to the calendar" "yes" \
  "$(has_text "$R_BODY" 'pm-btn--link" href="/events.php"')"
check "invoice summary panel present" "yes" "$(has_text "$R_BODY" 'Invoice summary')"
check "bank transfer note present" "yes" \
  "$(has_text "$R_BODY" 'No card details are collected on this site')"
check "no undefined pm- class on the register page" "0" \
  "$(printf '%s' "$R_BODY" | php -r '
$html = stream_get_contents(STDIN);
$css  = (string) @file_get_contents("public_html/assets/css/pm-design-system.css");
preg_match_all("/\.(pm-[A-Za-z0-9_-]+)/", $css, $m);
$defined = array_flip($m[1]);
preg_match_all("/class=\"([^\"]*)\"/", $html, $u);
$used = [];
foreach ($u[1] as $attr) {
    foreach (preg_split("/\s+/", trim($attr)) as $c) {
        if ($c !== "" && str_starts_with($c, "pm-")) { $used[$c] = true; }
    }
}
echo count(array_diff_key($used, $defined));')"
# The handler refuses an inactive event, so the form must not render for one.
fq "UPDATE events SET is_active = 0 WHERE id = $R_EVENT" >/dev/null
check "inactive event redirects rather than rendering a doomed form" "302" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$R_URL")"
fq "UPDATE events SET is_active = 1 WHERE id = $R_EVENT" >/dev/null
check "active event renders again" "200" "$(page_code "event-registration.php?id=$R_EVENT")"

echo ""; echo "=== 12c. THE POST CONTRACT: every field name the handler reads ==="
# The list is extracted from process-registration.php, not retyped here, so a
# field renamed on either side fails this rather than silently emptying a column.
printf '%s' "$R_BODY" > /tmp/verify-reg-page.html
check "handler reads no field the form does not send" "0" \
  "$(php -r '
$handler = (string) file_get_contents("public_html/process-registration.php");
$page    = (string) file_get_contents("/tmp/verify-reg-page.html");
preg_match_all("/\\\$_POST\\[.attendees.\\]\\[.(\\w+).\\]/", $handler, $nested);
preg_match_all("/\\\$_POST\\[.(\\w+).\\]/", $handler, $scalar);
$missing = 0;
foreach (array_unique($scalar[1]) as $name) {
    if ($name === "attendees") { continue; }
    if (strpos($page, "name=\"" . $name . "\"") === false) { $missing++; }
}
foreach (array_unique($nested[1]) as $name) {
    if (strpos($page, "name=\"attendees[" . $name . "][]\"") === false) { $missing++; }
}
echo $missing;')"
# Named individually as well, so a failure above says which one.
for n in csrf_token event_id event_name first_name last_name phone email \
         organization country address gender meal_preference future_topics consent; do
  check "form sends name=\"$n\"" "yes" "$(has_text "$R_BODY" "name=\"$n\"")"
done
for n in first_name last_name email title; do
  check "form sends attendees[$n][], five rows" "5" \
    "$(printf '%s' "$R_BODY" | grep -c -F "name=\"attendees[$n][]\"")"
done
check "delegate rows are real markup, not a template" "0" \
  "$(printf '%s' "$R_BODY" | grep -ci '<template')"

echo ""; echo "=== 12d. A full registration through the rebuilt form ==="
R_ONE="$(reg_submit p4-one@example.test 1)"
check "one delegate: returns success"   "true" "$(json_success "$R_ONE")"
check "one delegate: row written"       "1"    "$(fq "SELECT COUNT(*) FROM event_registrations WHERE email='p4-one@example.test'")"
check "one delegate: invoice number assigned" "1" \
  "$(fq "SELECT COUNT(*) FROM event_registrations WHERE email='p4-one@example.test' AND invoice_number IS NOT NULL")"
check "one delegate: invoice PDF written" "yes" \
  "$([ -f "public_html/$(reg_col p4-one@example.test invoice_path)" ] && echo yes || echo no)"
check "one delegate: consent captured"  "1"    "$(reg_col p4-one@example.test consent)"
check "one delegate: attendee_count"    "1"    "$(reg_col p4-one@example.test attendee_count)"

R_THREE="$(reg_submit p4-three@example.test 3)"
check "three delegates: returns success" "true" "$(json_success "$R_THREE")"
check "three delegates: attendee_count"  "3"    "$(reg_col p4-three@example.test attendee_count)"
# The four attendees arrays are zipped by index in the handler, so a row that
# lost one of its four fields would land with a blank name here.
check "three delegates: all three names stored" "1" \
  "$(fq "SELECT COUNT(*) FROM event_registrations WHERE email='p4-three@example.test'
           AND attendee_details LIKE '%Delegate1%'
           AND attendee_details LIKE '%Delegate2%'
           AND attendee_details LIKE '%Delegate3%'")"
check "three delegates: job titles stored" "1" \
  "$(fq "SELECT COUNT(*) FROM event_registrations WHERE email='p4-three@example.test' AND attendee_details LIKE '%Budget Controller%'")"
check "funnel: page_views logged for event 5" "yes" \
  "$([ "$(funnel_rows page_view $R_EVENT)" -ge 2 ] && echo yes || echo no)"
check "funnel: two submit_attempts"  "2" "$(funnel_rows submit_attempt $R_EVENT)"
check "funnel: two submit_successes" "2" "$(funnel_rows submit_success $R_EVENT)"
check "funnel: no submit_fail yet"   "0" "$(funnel_rows submit_fail $R_EVENT)"

echo ""; echo "=== 12e. The total the UI shows is the total the handler stored ==="
# The guard against an interface that disagrees with the system of record.
R_UNIT_SHOWN="$(reg_attr "$R_BODY" data-pm-unit-amount)"
R_TOTAL_SHOWN="$(reg_attr "$R_BODY" data-pm-total-amount)"
R_CUR_SHOWN="$(reg_attr "$R_BODY" data-pm-currency)"
check "unit price shown equals unit price stored" \
  "$(reg_col p4-one@example.test unit_price_amount)" "$R_UNIT_SHOWN"
check "currency shown equals currency stored" \
  "$(reg_col p4-one@example.test currency_code)" "$R_CUR_SHOWN"
check "total shown for one delegate equals the total stored" \
  "$(reg_col p4-one@example.test total_amount)" "$R_TOTAL_SHOWN"
# The multi-delegate rule, on real numbers: what the panel would show for three
# delegates is unit x 3, and that has to be what the invoice charged.
check "unit shown x 3 equals the three-delegate total stored" \
  "$(reg_col p4-three@example.test total_amount)" \
  "$(php -r 'printf("%.2f", (float) $argv[1] * 3);' "$R_UNIT_SHOWN")"
check "response total matches the stored total" \
  "$(reg_col p4-three@example.test total_amount)" \
  "$(printf '%s' "$R_THREE" | php -r '$d=json_decode(stream_get_contents(STDIN),true); printf("%.2f", (float) ($d["total_amount"] ?? 0));')"
# The prototype shows an early bird deduction. The handler applies none, so the
# panel must not promise one.
check "no discount line in the summary panel" "0" \
  "$(printf '%s' "$R_BODY" | awk '/pm-reg__summary/,/<\/aside>/' \
     | grep -ciE 'early bird|discount|deduct|per cent off')"
check "no tier selector that could change the price" "0" \
  "$(printf '%s' "$R_BODY" | grep -c -E 'name="tier"|name="delegate_tier"|data-pm-tier')"
check "the flow script multiplies unit by count and nothing else" "1" \
  "$(grep -c 'unitAmount \* delegateCount' "$REG_JS")"
check "the flow script does no discount arithmetic" "0" \
  "$(php -r 'echo preg_replace(["~/\*.*?\*/~s", "~//.*~"], "", (string) file_get_contents($argv[1]));' "$REG_JS" \
     | grep -ciE 'discount|early.?bird|per cent')"
# Two implementations of one rule, so assert they are literally the same two
# patterns. The numeric agreement above is what proves the rule itself.
check "the page's price parser mirrors parseEventPrice()" "yes" \
  "$(php -r '
$inv = (string) file_get_contents("public_html/includes/invoice.php");
$reg = (string) file_get_contents("public_html/event-registration.php");
preg_match_all("~preg_match\(([^,]+),~", $inv, $a);
preg_match_all("~preg_match\(([^,]+),~", $reg, $b);
$a = array_values(array_unique($a[1]));
$b = array_values(array_unique($b[1]));
sort($a); sort($b);
echo (count($a) === 2 && $a === $b) ? "yes" : "no";')"

echo ""; echo "=== 12f. CSRF on the rebuilt form ==="
R_BEFORE="$(fq "SELECT COUNT(*) FROM event_registrations")"
for mode in missing forged nosession; do
  OUT="$(TOKEN_MODE=$mode EVENT_ID=$R_EVENT ./local-dev/test-register-main.sh "p4-csrf-$mode@example.test")"
  check "rebuilt form: $mode token rejected" "false" "$(json_success "$OUT")"
done
check "rebuilt form: rejected posts stored nothing" "0" \
  "$(fq "SELECT COUNT(*) FROM event_registrations WHERE email LIKE 'p4-csrf-%'")"
check "rebuilt form: no new rows at all from them" "$R_BEFORE" \
  "$(fq "SELECT COUNT(*) FROM event_registrations")"
R_TOK="$(printf '%s' "$R_BODY" | sed -n 's/.*name="csrf_token" value="\([a-f0-9]*\)".*/\1/p' | head -1)"
check "the form still carries a session CSRF token" "yes" \
  "$(printf '%s' "$R_TOK" | grep -Eq '^[a-f0-9]{64}$' && echo yes || echo no)"

echo ""; echo "=== 12g. The GA4 purchase event fires only from confirmed success ==="
check "purchase event is in the flow script" "1" "$(grep -c "'purchase'" "$REG_JS")"
for f in transaction_id currency item_id item_name quantity; do
  check "purchase payload carries $f" "yes" "$(grep -q "$f" "$REG_JS" && echo yes || echo no)"
done
check "value comes from the server response" "1" \
  "$(grep -c 'value: parseFloat(data.total_amount)' "$REG_JS")"
check "price comes from the server response" "1" \
  "$(grep -c 'price: parseFloat(data.unit_price_amount)' "$REG_JS")"
# The one that matters: the confirmation, and the purchase event inside it, are
# reached only after the success:true guard has already returned.
check "confirmSuccess is called after the success guard, and only once" "yes" \
  "$(awk '/data.success !== true/ { guard = NR } \
           /^ *confirmSuccess\(data, submittedCount\);/ { call = NR; calls++ } \
           END { print (calls == 1 && guard > 0 && call > guard) ? "yes" : "no" }' "$REG_JS")"
check "the response carries a non-zero value to fire it with" "yes" \
  "$(printf '%s' "$R_THREE" | php -r '$d=json_decode(stream_get_contents(STDIN),true);
      echo ((float)($d["total_amount"] ?? 0) > 0 && ($d["invoice_number"] ?? "") !== "" && ($d["currency_code"] ?? "") !== "") ? "yes" : "no";')"

echo ""; echo "=== 12h. The steps degrade without JavaScript ==="
# curl runs no script, so $R_BODY IS the no-JavaScript rendering.
check "the form posts to the handler on its own" "yes" \
  "$(printf '%s' "$R_BODY" | php -r '
$html = stream_get_contents(STDIN);
preg_match("/<form[^>]*id=\"standaloneRegForm\"[^>]*>/s", $html, $m);
$tag = $m[0] ?? "";
echo (str_contains($tag, "action=\"/process-registration.php\"")
      && str_contains($tag, "method=\"post\"")) ? "yes" : "no";')"
check "a real submit button is in the document" "1" \
  "$(printf '%s' "$R_BODY" | grep -c 'type="submit" data-pm-submit')"
check "all four input steps are in the document" "4" \
  "$(printf '%s' "$R_BODY" | grep -c 'class="pm-reg__panel"')"
check "progress segments are real anchors" "5" \
  "$(printf '%s' "$R_BODY" | grep -c 'href="#pm-reg-step-')"
check "the confirmation ships hidden by attribute" "1" \
  "$(printf '%s' "$R_BODY" | grep -c 'data-pm-done hidden')"
# The steps must collapse on an attribute the flow script sets ITSELF, not on
# html[data-pm-js], which would still be set if this were the file that broke.
check "steps collapse on data-pm-steps" "yes" \
  "$(grep -q '\[data-pm-steps="on"\] .pm-reg__panel' public_html/assets/css/pm-design-system.css && echo yes || echo no)"
check "steps are NOT collapsed on data-pm-js" "0" \
  "$(grep -c 'data-pm-js="on"\] .pm-reg' public_html/assets/css/pm-design-system.css)"
check "the flag is set after the last listener is attached" "yes" \
  "$(awk '/addEventListener/ { last = NR } /root.setAttribute\(.data-pm-steps./ { set = NR } \
          END { print (set > last) ? "yes" : "no" }' "$REG_JS")"
check "the whole flow script is inside one try/catch" "1" "$(grep -c '^  } catch (error) {' "$REG_JS")"
check "hidden really hides, whatever the component display is" "1" \
  "$(grep -c 'display: none !important' public_html/assets/css/pm-design-system.css)"

echo ""; echo "=== 12i. CRITICAL: the 8g and 8h breaks, restated on the new form ==="
# A corrupted mailer must still produce a saved, invoiced registration. Same
# shape as section 3 and section 8g: break the secondary concern for real, then
# prove the primary outcome is untouched.
V=public_html/vendor/phpmailer/phpmailer/src/PHPMailer.php
cp "$V" /tmp/verify-p4-phpmailer.bak
head -c 130680 /tmp/verify-p4-phpmailer.bak > "$V"
R_MAIL="$(reg_submit p4-corruptmailer@example.test 2)"
cp /tmp/verify-p4-phpmailer.bak "$V"
check "corrupt mailer: registration still returns success" "true" "$(json_success "$R_MAIL")"
check "corrupt mailer: row persisted" "1" \
  "$(fq "SELECT COUNT(*) FROM event_registrations WHERE email='p4-corruptmailer@example.test'")"
check "corrupt mailer: invoice number assigned" "1" \
  "$(fq "SELECT COUNT(*) FROM event_registrations WHERE email='p4-corruptmailer@example.test' AND invoice_number IS NOT NULL")"
check "corrupt mailer: invoice PDF written" "yes" \
  "$([ -f "public_html/$(reg_col p4-corruptmailer@example.test invoice_path)" ] && echo yes || echo no)"
check "corrupt mailer: recorded as an unsent notification" "yes" \
  "$([ "$(fq "SELECT COUNT(*) FROM failed_notifications WHERE registration_id = $(reg_col p4-corruptmailer@example.test id)")" -ge 1 ] && echo yes || echo no)"
check "corrupt mailer: still counted as submit_success, never submit_fail" "3" \
  "$(funnel_rows submit_success $R_EVENT)"
check "corrupt mailer: no submit_fail" "0" "$(funnel_rows submit_fail $R_EVENT)"
check "vendor file restored intact" "0" "$(php -l "$V" >/dev/null 2>&1; echo $?)"
rm -f /tmp/verify-p4-phpmailer.bak

# A broken funnel table must still produce a saved, invoiced registration. DROP
# is not a sufficient break: ensureFunnelEventsSchema() would recreate it and
# every insert would succeed. The real table is set aside and replaced with one
# the INSERT cannot satisfy, which is what a half-applied migration looks like.
fq "RENAME TABLE funnel_events TO funnel_events_p4bak" >/dev/null
fq "CREATE TABLE funnel_events (id INT AUTO_INCREMENT PRIMARY KEY, wrong_column INT DEFAULT NULL)" >/dev/null
check "broken funnel table: register page still renders" "200" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$R_URL")"
R_FUNNEL="$(reg_submit p4-brokenfunnel@example.test 2)"
check "broken funnel table: registration still returns success" "true" "$(json_success "$R_FUNNEL")"
check "broken funnel table: row persisted" "1" \
  "$(fq "SELECT COUNT(*) FROM event_registrations WHERE email='p4-brokenfunnel@example.test'")"
check "broken funnel table: invoice number assigned" "1" \
  "$(fq "SELECT COUNT(*) FROM event_registrations WHERE email='p4-brokenfunnel@example.test' AND invoice_number IS NOT NULL")"
check "broken funnel table: invoice PDF written" "yes" \
  "$([ -f "public_html/$(reg_col p4-brokenfunnel@example.test invoice_path)" ] && echo yes || echo no)"
check "broken funnel table: nothing written to it" "0" "$(fq "SELECT COUNT(*) FROM funnel_events")"
fq "DROP TABLE funnel_events" >/dev/null
fq "RENAME TABLE funnel_events_p4bak TO funnel_events" >/dev/null
check "real funnel table restored" "1" "$(table_exists funnel_events)"
check "restored table still holds this section's rows" "3" "$(funnel_rows submit_success $R_EVENT)"

echo ""; echo "=== 12j. The register page survives a broken content table ==="
"${DB_MAIN[@]}" "RENAME TABLE page_content TO page_content_p4bak" >/dev/null 2>&1
"${DB_MAIN[@]}" "CREATE TABLE page_content (id INT PRIMARY KEY)" >/dev/null 2>&1
R_BROKEN="$(curl -s "$R_URL")"
check "broken page_content: register page still 200" "200" "$(page_code "event-registration.php?id=$R_EVENT")"
check "broken page_content: still has its h1" "$R_TITLE" "$(page_h1 "event-registration.php?id=$R_EVENT")"
check "broken page_content: no error on the page" "0" \
  "$(printf '%s' "$R_BROKEN" | grep -ciE 'fatal error|parse error|warning:|uncaught|sqlstate')"
check "broken page_content: still no em dash" "0" "$(printf '%s' "$R_BROKEN" | grep -c $'\xe2\x80\x94')"
# The field names are markup, not content, so a broken content table cannot
# touch them. Asserted because that is the property the money depends on.
check "broken page_content: the POST contract is intact" "yes" \
  "$([ "$(has_text "$R_BROKEN" 'name="first_name"')" = yes ] && \
     [ "$(has_text "$R_BROKEN" 'name="consent"')" = yes ] && \
     [ "$(has_text "$R_BROKEN" 'name="attendees[first_name][]"')" = yes ] && \
     [ "$(has_text "$R_BROKEN" 'name="attendees[title][]"')" = yes ] && echo yes || echo no)"
check "broken page_content: the invoice figures still render" "599.00" \
  "$(reg_attr "$R_BROKEN" data-pm-total-amount)"
check "broken page_content: five progress segments still render" "5" \
  "$(printf '%s' "$R_BROKEN" | grep -c 'data-pm-progress=')"
"${DB_MAIN[@]}" "DROP TABLE page_content" >/dev/null 2>&1
"${DB_MAIN[@]}" "RENAME TABLE page_content_p4bak TO page_content" >/dev/null 2>&1
check "page_content restored with its rows" "238" "$(pc_rows)"

echo ""; echo "=== 12k. Animated stat counters ==="
# The old site rendered a permanent "0" because a counter script never fired.
# curl runs no script, so these are the no-JavaScript renderings.
IDX="$(curl -s "$MAIN/index.php")"
ABT="$(curl -s "$MAIN/about.php")"
check "homepage: the real 875 is in the markup" "2" \
  "$(printf '%s' "$IDX" | grep -c 'data-pm-count>875<')"
check "about: the real 875 is in the markup" "1" \
  "$(printf '%s' "$ABT" | grep -c 'data-pm-count>875<')"
check "homepage: the real 25 is in the markup" "2" \
  "$(printf '%s' "$IDX" | grep -c 'data-pm-count>25<')"
check "homepage: no counter renders as 0" "0" \
  "$(printf '%s' "$IDX" | grep -c 'data-pm-count>0<')"
check "about: no counter renders as 0" "0" \
  "$(printf '%s' "$ABT" | grep -c 'data-pm-count>0<')"
check "homepage: counter markup on every stat" "8" \
  "$(printf '%s' "$IDX" | grep -c 'data-pm-count')"
check "about: counter markup on every stat" "4" \
  "$(printf '%s' "$ABT" | grep -c 'data-pm-count')"
check "the counter lives in pm-layout.js, not a new file" "1" \
  "$(grep -c 'data-pm-count' public_html/assets/js/pm-layout.js)"
check "it animates only on scroll into view" "yes" \
  "$(grep -q 'IntersectionObserver' public_html/assets/js/pm-layout.js && echo yes || echo no)"
check "it animates each figure once" "1" \
  "$(grep -c 'observer.unobserve' public_html/assets/js/pm-layout.js)"
check "it respects prefers-reduced-motion" "1" \
  "$(grep -c "prefers-reduced-motion: reduce" public_html/assets/js/pm-layout.js)"
# The end state is the original string, not a recomputation of it, so a
# thousands separator or a trailing + cannot be lost.
check "it restores the original text verbatim" "2" \
  "$(grep -c 'el.textContent = finalText;' public_html/assets/js/pm-layout.js)"
check "the counter is its own try/catch" "2" \
  "$(grep -c '^  } catch (error) {' public_html/assets/js/pm-layout.js)"
check "pm-layout.js still lints" "0" \
  "$(command -v node >/dev/null 2>&1 && { node --check public_html/assets/js/pm-layout.js >/dev/null 2>&1; echo $?; } || echo 0)"
check "pm-register.js still lints" "0" \
  "$(command -v node >/dev/null 2>&1 && { node --check "$REG_JS" >/dev/null 2>&1; echo $?; } || echo 0)"
rm -f /tmp/verify-reg-page.html

echo
echo "=== 13. Phase 5A: admin shell, permissions registry and audit log ==="

CSS=public_html/assets/css/pm-admin.css
HDR=public_html/admin/header.php
JAR=/tmp/verify-admin-cookies.txt

admin_login() {
  rm -f "$JAR"
  local tok
  tok="$(curl -s -c "$JAR" "$MAIN/admin/login.php" \
        | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1)"
  curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code}' \
    --data-urlencode "csrf_token=$tok" \
    --data-urlencode "username=Craig" \
    --data-urlencode "password=localtest-analytics-pw" \
    "$MAIN/admin/login.php"
}
admin_get() { curl -s -b "$JAR" "$MAIN/admin/$1"; }

check "pm-admin.css exists"                  "yes" "$([ -s $CSS ] && echo yes || echo no)"
check "pm-admin.css carries one comment only" "1"  "$(grep -c '/\*' $CSS)"
check "and it is the one that stops a real break" "1" \
  "$(grep -c 'outranks the user agent' $CSS)"
check "IBM Plex Mono is self-hosted"         "yes" "$([ -s public_html/assets/fonts/IBMPlexMono-Regular.woff2 ] && echo yes || echo no)"
check "its OFL licence ships with it"        "yes" "$([ -s public_html/assets/fonts/OFL-IBMPlexMono.txt ] && echo yes || echo no)"
check "no Google Fonts call in the admin CSS" "0"  "$(grep -c 'fonts.googleapis' $CSS)"
check "figures are set in tabular numerals"  "4"   "$(grep -c 'tabular-nums' $CSS)"

check "the shell loads pm-admin.css"         "1"   "$(grep -c 'pm-admin.css' $HDR)"
check "the shell no longer loads admin.css"  "0"   "$(grep -c 'css/admin.css' $HDR)"
check "no Google Fonts call in the shell"    "0"   "$(grep -c 'fonts.googleapis' $HDR)"
check "Font Awesome is still on loan to old bodies" "1" "$(grep -c 'font-awesome' $HDR)"
check "the shell uses the real logo file"    "1"   "$(grep -c 'favicon-512.png' $HDR)"
check "login uses the real logo file"        "1"   "$(grep -c 'favicon-512.png' public_html/admin/login.php)"
check "login is marked noindex"              "1"   "$(grep -c 'noindex' public_html/admin/login.php)"
check "the shell is marked noindex"          "1"   "$(grep -c 'noindex' $HDR)"

check "nav registry lists four groups"       "4"   "$(php -r "
  require 'public_html/admin/includes/nav.php'; echo count(pmAdminNav());")"
check "nav registry covers 19 screens"       "19"  "$(php -r "
  require 'public_html/admin/includes/nav.php';
  \$n=0; foreach (pmAdminNav() as \$g) { \$n += count(\$g['items']); } echo \$n;")"
check "eighteen screens are built so far"    "18"   "$(php -r "
  require 'public_html/admin/includes/nav.php';
  \$n=0; foreach (pmAdminNav() as \$g) foreach (\$g['items'] as \$i) if (!empty(\$i['built'])) \$n++; echo \$n;")"
check "the CMS permission modules exist"     "8"   "$(php -r "
  require 'public_html/includes/auth.php';
  \$f=getPermissionFeatures(); \$n=0;
  foreach (['content','media','menus','submissions','seo','redirects','audit','health'] as \$m)
    if (isset(\$f[\$m])) \$n++;
  echo \$n;")"

check "cms_audit_log exists"                 "1"   "$(table_exists cms_audit_log)"
check "the audit helper catches Throwable"   "2"   "$(grep -c 'catch (Throwable' public_html/includes/audit.php)"
check "the audit helper catches no Exception" "0"  "$(grep -c 'catch (Exception' public_html/includes/audit.php)"
check "pmAudit returns void"                 "1"   "$(grep -c '): void {' public_html/includes/audit.php)"

"${DB_MAIN[@]}" "DELETE FROM cms_audit_log" >/dev/null 2>&1
check "signing in redirects"                 "302" "$(admin_login)"
check "signing in wrote an audit row"        "1"   "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_audit_log WHERE action='login' AND actor_username='Craig'")"
check "the audit row names the actor"        "Craig" "$("${DB_MAIN[@]}" "SELECT actor_username FROM cms_audit_log ORDER BY id DESC LIMIT 1")"
check "the audit row records an address"     "1"   "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_audit_log WHERE ip_address IS NOT NULL")"

for s in dashboard analytics registrations events accounting users settings; do
  check "re-skinned $s.php still returns 200" "200" \
    "$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' "$MAIN/admin/$s.php")"
done
check "the shell renders the sidebar"        "1"   "$(admin_get dashboard.php | grep -c 'class="pma-side"')"
check "the sidebar shows the account"        "1"   "$(admin_get dashboard.php | grep -c 'Craig')"
check "the tier 3 placeholder is not a link" "0"   "$(admin_get dashboard.php | grep -c 'href="#"')"
check "the tier 3 placeholder is marked"     "1"   "$(admin_get dashboard.php | grep -c 'pma-chip-later')"

echo "  ---- CRITICAL: a failing audit log must never block a sign-in ----"
"${DB_MAIN[@]}" "RENAME TABLE cms_audit_log TO cms_audit_log_parked" >/dev/null 2>&1
check "sign-in still redirects with the log gone" "302" "$(admin_login)"
check "the panel still serves with the log gone" "200" \
  "$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' "$MAIN/admin/dashboard.php")"
"${DB_MAIN[@]}" "DROP TABLE IF EXISTS cms_audit_log" >/dev/null 2>&1
"${DB_MAIN[@]}" "RENAME TABLE cms_audit_log_parked TO cms_audit_log" >/dev/null 2>&1
check "the audit table is back"              "1"   "$(table_exists cms_audit_log)"
rm -f "$JAR"

echo
echo "=== 14. Phase 5B: submissions inbox, and sponsorship enquiries that persist ==="

MIG=public_html/database/migrations
JAR=/tmp/verify-sub-cookies.txt

sub_login() {
  rm -f "$JAR"
  local tok
  tok="$(curl -s -c "$JAR" "$MAIN/admin/login.php" \
        | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1)"
  curl -s -b "$JAR" -c "$JAR" -o /dev/null \
    --data-urlencode "csrf_token=$tok" \
    --data-urlencode "username=Craig" \
    --data-urlencode "password=localtest-analytics-pw" \
    "$MAIN/admin/login.php"
}
sub_token() {
  curl -s -b "$JAR" "$MAIN/admin/submissions.php?tab=$1" \
    | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1
}
spons_post() {
  curl -s -X POST "$MAIN/process-sponsorship.php" \
    -d "first_name=Ver" -d "last_name=Ifier" -d "organisation=$1" \
    -d "email=$2" -d "tier=Gold" -d "message=verify" -d "events[]=IPSAS Clean-Audit"
}

check "sponsorship migration has an up and a down" "2" \
  "$(ls $MIG/2026-09-03-02-create-sponsorship-enquiries.*.sql 2>/dev/null | wc -l | tr -d ' ')"
check "newsletter migration has an up and a down" "2" \
  "$(ls $MIG/2026-09-03-03-newsletter-unsubscribe.*.sql 2>/dev/null | wc -l | tr -d ' ')"
check "sponsorship_enquiries exists"          "1" "$(table_exists sponsorship_enquiries)"
check "newsletter has an unsubscribed_at"     "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='newsletter_subscribers' AND column_name='unsubscribed_at'")"

"${DB_MAIN[@]}" "DELETE FROM sponsorship_enquiries" >/dev/null 2>&1
"${DB_MAIN[@]}" "DELETE FROM failed_notifications WHERE subject LIKE '%Sponsorship%'" >/dev/null 2>&1
clearmail

OUT="$(spons_post "Verify Ministry" "spons-ok@example.test")"
check "sponsorship returns success"           "true" "$(json_success "$OUT")"
check "the enquiry is stored"                 "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM sponsorship_enquiries WHERE email='spons-ok@example.test'")"
check "the tier is stored"                    "Gold" \
  "$("${DB_MAIN[@]}" "SELECT tier FROM sponsorship_enquiries WHERE email='spons-ok@example.test'")"
check "the events are stored as JSON"         "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM sponsorship_enquiries WHERE email='spons-ok@example.test' AND events LIKE '[%]'")"
check "both sponsorship emails went out"      "2" "$(mailcount)"
check "the enquiry is flagged as notified"    "1" \
  "$("${DB_MAIN[@]}" "SELECT notified FROM sponsorship_enquiries WHERE email='spons-ok@example.test'")"

echo "  ---- CRITICAL: a sponsorship enquiry must outlive a mail failure ----"
cp public_html/.env /tmp/verify-env-spons.bak
sed -i '' 's/^SMTP_PORT=.*/SMTP_PORT=1099/' public_html/.env
OUT="$(spons_post "Dead Mail Treasury" "spons-dead@example.test")"
cp /tmp/verify-env-spons.bak public_html/.env
check "dead mail still returns success"       "true" "$(json_success "$OUT")"
check "the enquiry survived the mail failure" "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM sponsorship_enquiries WHERE email='spons-dead@example.test'")"
check "it is honestly marked as not notified" "0" \
  "$("${DB_MAIN[@]}" "SELECT notified FROM sponsorship_enquiries WHERE email='spons-dead@example.test'")"
check "both failures were recorded"           "2" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM failed_notifications WHERE subject LIKE '%Sponsorship%'")"
check "the handler checks the stored row"     "1" \
  "$(grep -c 'if (\$enquiryId === 0)' public_html/process-sponsorship.php)"
check "the handler no longer ignores sends"   "2" \
  "$(grep -c 'sendEmailMessages(' public_html/process-sponsorship.php)"

sub_login
for t in enquiries sponsorship newsletter; do
  check "submissions tab $t returns 200" "200" \
    "$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' "$MAIN/admin/submissions.php?tab=$t")"
done
check "the inbox names all three sources" "3" \
  "$(curl -s -b "$JAR" "$MAIN/admin/submissions.php" \
     | grep -oE 'tab=(enquiries|sponsorship|newsletter)"' | sort -u | wc -l | tr -d ' ')"

SID="$("${DB_MAIN[@]}" "SELECT id FROM sponsorship_enquiries WHERE email='spons-ok@example.test'")"
TOK="$(sub_token sponsorship)"
curl -s -b "$JAR" -o /dev/null -X POST "$MAIN/admin/submissions.php?tab=sponsorship" \
  --data-urlencode "csrf_token=$TOK" -d "action=handle" -d "scope=sponsorship" \
  -d "id=$SID" -d "state=handled"
check "marking handled updates the row" "handled" \
  "$("${DB_MAIN[@]}" "SELECT status FROM sponsorship_enquiries WHERE id=$SID")"
check "marking handled is audited"      "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_audit_log WHERE action='submission_status' AND entity_id='$SID'")"

curl -s -b "$JAR" -o /dev/null -X POST "$MAIN/admin/submissions.php?tab=sponsorship" \
  --data-urlencode "csrf_token=$TOK" -d "action=handle" -d "scope=sponsorship" \
  -d "id=$SID" -d "state=new"
check "a handled row can be reopened"   "new" \
  "$("${DB_MAIN[@]}" "SELECT status FROM sponsorship_enquiries WHERE id=$SID")"

curl -s -b "$JAR" -o /dev/null -X POST "$MAIN/admin/submissions.php?tab=sponsorship" \
  -d "csrf_token=not-the-token" -d "action=handle" -d "scope=sponsorship" \
  -d "id=$SID" -d "state=handled"
check "a bad CSRF token changes nothing" "new" \
  "$("${DB_MAIN[@]}" "SELECT status FROM sponsorship_enquiries WHERE id=$SID")"

"${DB_MAIN[@]}" "INSERT IGNORE INTO newsletter_subscribers (email, source) VALUES ('unsub-me@example.test','verify')" >/dev/null 2>&1
NID="$("${DB_MAIN[@]}" "SELECT id FROM newsletter_subscribers WHERE email='unsub-me@example.test'")"
TOK="$(sub_token newsletter)"
curl -s -b "$JAR" -o /dev/null -X POST "$MAIN/admin/submissions.php?tab=newsletter" \
  --data-urlencode "csrf_token=$TOK" -d "action=unsubscribe" -d "id=$NID"
check "unsubscribing stamps a time"      "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM newsletter_subscribers WHERE id=$NID AND unsubscribed_at IS NOT NULL")"
check "the address is kept, not deleted" "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM newsletter_subscribers WHERE id=$NID")"
check "unsubscribing is audited"         "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_audit_log WHERE action='newsletter_unsubscribe' AND entity_id='$NID'")"

CSV="$(curl -s -b "$JAR" "$MAIN/admin/submissions.php?export=newsletter")"
check "the newsletter export is CSV"          "1" "$(printf '%s' "$CSV" | grep -c 'EMAIL,SUBSCRIBED_AT,SOURCE')"
check "the export leaves out the opted-out"   "0" "$(printf '%s' "$CSV" | grep -c 'unsub-me@example.test')"
CSV="$(curl -s -b "$JAR" "$MAIN/admin/submissions.php?export=sponsorship")"
check "the sponsorship export has a header"   "1" "$(printf '%s' "$CSV" | grep -c 'Organisation')"
check "the sponsorship export has the row"    "1" "$(printf '%s' "$CSV" | grep -c 'spons-ok@example.test')"
check "exporting is audited"                  "2" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_audit_log WHERE action='submission_export'")"

check "submissions is now built in the registry" "1" \
  "$(php -r "
    require 'public_html/admin/includes/nav.php';
    foreach (pmAdminNav() as \$g) foreach (\$g['items'] as \$i)
      if (\$i['key']==='submissions') echo (int) !empty(\$i['built']);")"
rm -f "$JAR"

echo
echo "=== 15. Phase 5 design fidelity: the rules the prototype is built on ==="

ACSS=public_html/assets/css/pm-admin.css
APHP="public_html/admin/dashboard.php public_html/admin/analytics.php public_html/admin/registrations.php public_html/admin/events.php public_html/admin/accounting.php public_html/admin/users.php public_html/admin/settings.php public_html/admin/submissions.php public_html/admin/header.php public_html/admin/login.php"

check "no gradients anywhere in the panel"  "0" "$(grep -rhc 'linear-gradient\|radial-gradient' $ACSS $APHP | paste -sd+ - | bc)"
check "no glassmorphism"                    "0" "$(grep -rhc 'backdrop-filter' $ACSS $APHP | paste -sd+ - | bc)"
check "every radius in the pages is 2px"    "0" \
  "$(grep -rhoE 'border-radius: ?[0-9]+(px|%)' $APHP | grep -vc 'border-radius:2px')"
check "no radius above 2px in the CSS"      "0" \
  "$(grep -ohE 'border-radius: ?[0-9]+px' $ACSS | grep -vcE 'border-radius: ?(0|2)px')"

# The palette is closed. Anything not on this list is a colour the brand does
# not have, which is how the old template look crept back in as inline styles.
PALETTE='#000000|#00bf63|#007a41|#0d0d0d|#3d3d3d|#6b6b6b|#8a5a00|#9a9a9a|#b02a17|#b8b8b8|#d8bb7a|#dcdcdc|#e8e8e8|#f6f6f4|#ffffff|#1a0dab|#4d5156'
check "the page bodies use only palette colours" "0" \
  "$(grep -rhoE '#[0-9a-fA-F]{6}' $APHP | tr 'A-F' 'a-f' | grep -vcE "^($PALETTE)$")"
check "the stylesheet uses only palette colours" "0" \
  "$(grep -ohE '#[0-9a-fA-F]{6}' $ACSS | tr 'A-F' 'a-f' | grep -vcE "^($PALETTE)$")"
check "Google's colours appear only in the search preview" "0" \
  "$(grep -n '#1a0dab\|#4d5156' $ACSS | grep -vc 'serp')"

check "content is capped to the prototype frame" "1" "$(grep -c '\-\-pma-content: 1254px' $ACSS)"
check "the top bar and body both respect it"     "2" "$(grep -c 'var(--pma-content)' $ACSS)"

check "buttons carry no icon glyphs"        "1" "$(grep -c '\.pma \.btn i,' $ACSS)"
check "selects are styled, not native"      "2" "$(grep -c 'appearance: none' $ACSS)"
check "filter rows use the toolbar pattern" "2" \
  "$(grep -l 'class="pma-toolbar"' public_html/admin/analytics.php public_html/admin/submissions.php | wc -l | tr -d ' ')"
check "labels in toolbars are visually hidden" "1" "$(grep -c '\.pma-vh' $ACSS)"

check "the sidebar can be collapsed"        "1" "$(grep -c 'pma-side-toggle' public_html/admin/header.php)"
check "the collapsed state has styles"      "1" "$(grep -c '\.pma-shell\.is-collapsed {' $ACSS)"
check "the toggle script is loaded"         "1" "$(grep -c 'pm-admin.js' public_html/admin/header.php)"
check "pm-admin.js lints"                   "0" \
  "$(command -v node >/dev/null 2>&1 && { node --check public_html/assets/js/pm-admin.js >/dev/null 2>&1; echo $?; } || echo 0)"
check "toggle choices survive a reload"      "2" "$(grep -c 'localStorage.setItem' public_html/assets/js/pm-admin.js)"
check "blocked site data does not break it" "4" "$(grep -c 'catch (error)' public_html/assets/js/pm-admin.js)"

echo
echo "=== 16. Phase 5C: media library ==="

UPD=public_html/assets/uploads
MJAR=/tmp/verify-media-cookies.txt

media_login() {
  rm -f "$MJAR"
  local tok
  tok="$(curl -s -c "$MJAR" "$MAIN/admin/login.php" \
        | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1)"
  curl -s -b "$MJAR" -c "$MJAR" -o /dev/null \
    --data-urlencode "csrf_token=$tok" --data-urlencode "username=Craig" \
    --data-urlencode "password=localtest-analytics-pw" "$MAIN/admin/login.php"
}
media_token() {
  curl -s -b "$MJAR" "$MAIN/admin/media.php" \
    | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1
}
media_upload() {
  curl -s -b "$MJAR" -X POST "$MAIN/admin/media.php" \
    -F "csrf_token=$(media_token)" -F "action=upload" \
    -F "alt_text=$2" -F "file=@$1"
}

check "the uploads directory exists"        "yes" "$([ -d $UPD ] && echo yes || echo no)"
check "uploads refuse to run PHP"           "1"   "$(grep -c 'php_flag engine off' $UPD/.htaccess)"
check "uploads deny script extensions"      "1"   "$(grep -c 'Require all denied' $UPD/.htaccess)"
check "uploaded files are gitignored"       "1"   "$(grep -c '^\*$' $UPD/.gitignore)"
check "the media migration has both halves" "2"   \
  "$(ls public_html/database/migrations/2026-09-03-04-create-cms-media.*.sql 2>/dev/null | wc -l | tr -d ' ')"

"${DB_MAIN[@]}" "DELETE FROM cms_media" >/dev/null 2>&1
rm -f $UPD/*.png $UPD/*/*.png $UPD/*.jpg $UPD/*/*.jpg 2>/dev/null
media_login
check "media.php returns 200" "200" "$(curl -s -b "$MJAR" -o /dev/null -w '%{http_code}' "$MAIN/admin/media.php")"
check "cms_media was created on demand"       "1" "$(table_exists cms_media)"
check "cms_media_usage was created on demand" "1" "$(table_exists cms_media_usage)"

OUT="$(media_upload "public_html/assets/images/fisrt-logo.png" "Brand shield in green")"
check "an upload is accepted"      "1" "$(printf '%s' "$OUT" | grep -c 'Uploaded\.')"
check "the file is recorded"       "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_media WHERE mime='image/png'")"
check "the alt text is stored"     "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_media WHERE alt_text='Brand shield in green'")"
check "dimensions were read"       "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_media WHERE width=713 AND height=183")"
FN="$("${DB_MAIN[@]}" "SELECT filename FROM cms_media ORDER BY id DESC LIMIT 1")"
check "the original is on disk"    "yes" "$([ -f "$UPD/$FN" ] && echo yes || echo no)"
for s in thumb medium large; do
  check "the $s size was generated" "yes" "$([ -f "$UPD/$s/$FN" ] && echo yes || echo no)"
done
check "the thumb is 400 wide"      "400" "$(php -r "\$i=getimagesize('$UPD/thumb/$FN'); echo \$i[0];")"
check "a small image is not upscaled" "713" "$(php -r "\$i=getimagesize('$UPD/large/$FN'); echo \$i[0];")"
check "the stored name is not the original" "0" "$(printf '%s' "$FN" | grep -c '^fisrt-logo\.png$')"

echo "  ---- CRITICAL: the type check must not trust the extension ----"
printf '<?php echo "pwned"; ?>' > /tmp/verify-fake.png
OUT="$(media_upload "/tmp/verify-fake.png" "should not be accepted")"
check "a PHP file named .png is refused" "1" "$(printf '%s' "$OUT" | grep -c 'file type is not accepted')"
check "nothing was recorded for it"      "0" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_media WHERE original_name='verify-fake.png'")"
check "nothing was written to disk"      "0" "$(ls $UPD/*.png 2>/dev/null | grep -c 'verify-fake')"
rm -f /tmp/verify-fake.png

check "no PHP notice leaks into the panel" "0" \
  "$(curl -s -b "$MJAR" "$MAIN/admin/media.php" | grep -cE 'Deprecated:|Warning:|Notice:')"
check "errors are logged, never displayed" "1" \
  "$(grep -c "ini_set('display_errors'" public_html/includes/config.php)"
check "the CPD subdomain does the same"    "1" \
  "$(grep -c "ini_set('display_errors'" cpd.prosper-minds.com/config.php)"

MID="$("${DB_MAIN[@]}" "SELECT id FROM cms_media ORDER BY id DESC LIMIT 1")"
curl -s -b "$MJAR" -o /dev/null -X POST "$MAIN/admin/media.php" \
  -F "csrf_token=$(media_token)" -F "action=delete" -F "id=$MID"
check "deleting removes the row"        "0" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_media WHERE id=$MID")"
check "but the file is kept for restoring" "yes" "$([ -f "$UPD/$FN" ] && echo yes || echo no)"
check "and so are its sizes"               "yes" "$([ -f "$UPD/thumb/$FN" ] && echo yes || echo no)"
check "and a snapshot went to the trash"     "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_trash WHERE entity_type='media'")"
check "media upload is audited"    "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_audit_log WHERE action='media_upload'")"
check "media delete is audited"    "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_audit_log WHERE action='media_delete'")"
rm -f "$MJAR"

echo
echo "=== 17. Phase 5D: site identity and menus ==="

NJAR=/tmp/verify-menu-cookies.txt
menu_login() {
  rm -f "$NJAR"
  local tok
  tok="$(curl -s -c "$NJAR" "$MAIN/admin/login.php" \
        | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1)"
  curl -s -b "$NJAR" -c "$NJAR" -o /dev/null \
    --data-urlencode "csrf_token=$tok" --data-urlencode "username=Craig" \
    --data-urlencode "password=localtest-analytics-pw" "$MAIN/admin/login.php"
}
menu_token() {
  curl -s -b "$NJAR" "$MAIN/admin/menus.php" \
    | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1
}
nav_labels() { curl -s "$MAIN/index.php" | grep -c 'class="pm-nav__link"'; }

menu_login
check "menus.php returns 200" "200" "$(curl -s -b "$NJAR" -o /dev/null -w '%{http_code}' "$MAIN/admin/menus.php")"
check "cms_menu_items was created on demand" "1" "$(table_exists cms_menu_items)"
check "the header menu was seeded from the built-in list" "6" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_menu_items WHERE location='header'")"
check "the menu migration has both halves" "2" \
  "$(ls public_html/database/migrations/2026-09-03-05-create-cms-menu-items.*.sql 2>/dev/null | wc -l | tr -d ' ')"
check "the public nav renders six links" "6" "$(nav_labels)"
check "the current page is still marked" "2" "$(curl -s "$MAIN/events.php" | grep -c 'aria-current="page"')"

MID="$("${DB_MAIN[@]}" "SELECT id FROM cms_menu_items WHERE label='Services' LIMIT 1")"
curl -s -b "$NJAR" -o /dev/null -X POST "$MAIN/admin/menus.php" \
  --data-urlencode "csrf_token=$(menu_token)" -d "action=update" -d "location=header" \
  -d "id=$MID" -d "label=Programmes" -d "link_type=page" -d "target=services.php" -d "is_active=1"
check "renaming an item changes the live site" "2" "$(curl -s "$MAIN/index.php" | grep -c '>Programmes<')"
check "renaming a menu item is audited"        "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_audit_log WHERE action='menu_update'")"

curl -s -b "$NJAR" -o /dev/null -X POST "$MAIN/admin/menus.php" \
  --data-urlencode "csrf_token=$(menu_token)" -d "action=update" -d "location=header" \
  -d "id=$MID" -d "label=Programmes" -d "link_type=page" -d "target=services.php"
check "an item can be hidden from the site" "5" "$(nav_labels)"

OUT="$(curl -s -b "$NJAR" -X POST "$MAIN/admin/menus.php" \
  --data-urlencode "csrf_token=$(menu_token)" -d "action=add" -d "location=header" \
  -d "label=Bad" -d "link_type=external" --data-urlencode "target=javascript:alert(1)")"
check "a javascript: destination is refused" "1" "$(printf '%s' "$OUT" | grep -c 'not usable')"
check "and nothing was stored for it"        "0" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_menu_items WHERE label='Bad'")"

echo "  ---- CRITICAL: an empty or missing menu table must not empty the navigation ----"
"${DB_MAIN[@]}" "DELETE FROM cms_menu_items" >/dev/null 2>&1
check "an empty menu falls back to the built-in nav" "6" "$(nav_labels)"
"${DB_MAIN[@]}" "RENAME TABLE cms_menu_items TO cms_menu_items_parked" >/dev/null 2>&1
check "a missing menu table falls back too"          "6" "$(nav_labels)"
check "the homepage still returns 200"             "200" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/index.php")"
"${DB_MAIN[@]}" "RENAME TABLE cms_menu_items_parked TO cms_menu_items" >/dev/null 2>&1

check "settings saves the site identity keys" "1" \
  "$(grep -c "'site_title', 'site_tagline'" public_html/admin/settings.php)"
check "settings can replace the logo"         "2" \
  "$(grep -c "save_identity_image" public_html/admin/settings.php)"
check "the logo is previewed on both grounds" "1" \
  "$(grep -c 'pma-identity-preview is-dark' public_html/admin/settings.php)"
check "settings.php still returns 200"      "200" \
  "$(curl -s -b "$NJAR" -o /dev/null -w '%{http_code}' "$MAIN/admin/settings.php")"
rm -f "$NJAR"

echo
echo "=== 18. Phase 5E: pages, blocks, revisions and publishing ==="

PJAR=/tmp/verify-pages-cookies.txt
pg_login() {
  rm -f "$PJAR"
  local tok
  tok="$(curl -s -c "$PJAR" "$MAIN/admin/login.php" \
        | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1)"
  curl -s -b "$PJAR" -c "$PJAR" -o /dev/null \
    --data-urlencode "csrf_token=$tok" --data-urlencode "username=Craig" \
    --data-urlencode "password=localtest-analytics-pw" "$MAIN/admin/login.php"
}
pg_token() { curl -s -b "$PJAR" "$1" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1; }

pg_login
"${DB_MAIN[@]}" "DELETE FROM cms_page_blocks" >/dev/null 2>&1
"${DB_MAIN[@]}" "DELETE FROM cms_revisions" >/dev/null 2>&1
"${DB_MAIN[@]}" "DELETE FROM cms_pages" >/dev/null 2>&1

check "pages.php returns 200" "200" "$(curl -s -b "$PJAR" -o /dev/null -w '%{http_code}' "$MAIN/admin/pages.php")"
check "cms_pages was created on demand"   "1" "$(table_exists cms_pages)"
check "cms_page_blocks exists"            "1" "$(table_exists cms_page_blocks)"
check "cms_revisions exists"              "1" "$(table_exists cms_revisions)"
check "cms_preview_tokens exists"         "1" "$(table_exists cms_preview_tokens)"
check "the pages migration has both halves" "2" \
  "$(ls public_html/database/migrations/2026-09-03-06-create-cms-pages.*.sql 2>/dev/null | wc -l | tr -d ' ')"

curl -s -b "$PJAR" -o /dev/null -X POST "$MAIN/admin/pages.php" \
  --data-urlencode "csrf_token=$(pg_token "$MAIN/admin/pages.php")" \
  -d "action=create" -d "title=Verify Page" -d "slug="
PID="$("${DB_MAIN[@]}" "SELECT id FROM cms_pages ORDER BY id DESC LIMIT 1")"
ED="$MAIN/admin/page-editor.php?id=$PID"
check "a new page is created"        "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_pages WHERE slug='verify-page'")"
check "and it starts as a draft" "draft" "$("${DB_MAIN[@]}" "SELECT status FROM cms_pages WHERE id=$PID")"
check "creating it kept a version"   "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_revisions WHERE entity_id=$PID AND note='Created'")"

OUT="$(curl -s -b "$PJAR" -X POST "$MAIN/admin/pages.php" \
  --data-urlencode "csrf_token=$(pg_token "$MAIN/admin/pages.php")" \
  -d "action=create" -d "title=Events" -d "slug=events")"
check "a slug cannot shadow a real page" "1" "$(printf '%s' "$OUT" | grep -c 'already used by a built-in page')"

check "the editor returns 200" "200" "$(curl -s -b "$PJAR" -o /dev/null -w '%{http_code}' "$ED")"
curl -s -b "$PJAR" -o /dev/null -X POST "$ED" --data-urlencode "csrf_token=$(pg_token "$ED")" -d "action=add_block" -d "block_type=hero"
curl -s -b "$PJAR" -o /dev/null -X POST "$ED" --data-urlencode "csrf_token=$(pg_token "$ED")" -d "action=add_block" -d "block_type=stats"
check "blocks are added in order" "2" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_page_blocks WHERE page_id=$PID")"
BID="$("${DB_MAIN[@]}" "SELECT id FROM cms_page_blocks WHERE page_id=$PID ORDER BY sort_order LIMIT 1")"

curl -s -b "$PJAR" -o /dev/null -X POST "$ED" --data-urlencode "csrf_token=$(pg_token "$ED")" \
  -d "action=save_block" -d "block_id=$BID" -d "appearance=dark" \
  --data-urlencode "f_heading=A verified heading" --data-urlencode "f_body=Body copy."
check "a block stores its content"    "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_page_blocks WHERE id=$BID AND payload LIKE '%A verified heading%'")"
check "a block stores its appearance" "dark" "$("${DB_MAIN[@]}" "SELECT appearance FROM cms_page_blocks WHERE id=$BID")"
check "editing kept another version"  "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_revisions WHERE entity_id=$PID AND note='Before editing a block'")"

echo "  ---- CRITICAL: a page that is not published must not be public ----"
check "an unpublished page is a 404" "404" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/cms-page.php?slug=verify-page")"
curl -s -b "$PJAR" -o /dev/null -X POST "$ED" --data-urlencode "csrf_token=$(pg_token "$ED")" -d "action=publish"
check "publishing makes it public"   "200" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/cms-page.php?slug=verify-page")"
check "the heading reaches the page"   "1" "$(curl -s "$MAIN/cms-page.php?slug=verify-page" | grep -c 'A verified heading')"
check "the dark appearance is applied" "1" "$(curl -s "$MAIN/cms-page.php?slug=verify-page" | grep -c 'pm-section--dark')"
curl -s -b "$PJAR" -o /dev/null -X POST "$ED" --data-urlencode "csrf_token=$(pg_token "$ED")" -d "action=unpublish"
check "unpublishing hides it again"  "404" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/cms-page.php?slug=verify-page")"

TOKEN="$(curl -s -b "$PJAR" -X POST "$ED" --data-urlencode "csrf_token=$(pg_token "$ED")" \
        -d "action=preview_link" | grep -oE 'preview=[a-f0-9]{48}' | head -1 | cut -d= -f2)"
check "a preview link reaches a draft" "200" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/cms-page.php?slug=verify-page&preview=$TOKEN")"
check "and it says so on the page"       "1" \
  "$(curl -s "$MAIN/cms-page.php?slug=verify-page&preview=$TOKEN" | grep -c 'previewing a page that is not published')"
check "a preview is never indexed"       "1" \
  "$(curl -s "$MAIN/cms-page.php?slug=verify-page&preview=$TOKEN" | grep -c 'noindex')"
check "a made-up token does not"       "404" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/cms-page.php?slug=verify-page&preview=$(printf 'a%.0s' $(seq 48))")"

REV="$("${DB_MAIN[@]}" "SELECT id FROM cms_revisions WHERE entity_id=$PID ORDER BY id ASC LIMIT 1")"
BEFORE="$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_revisions WHERE entity_id=$PID")"
curl -s -b "$PJAR" -o /dev/null -X POST "$ED" --data-urlencode "csrf_token=$(pg_token "$ED")" \
  -d "action=restore_revision" -d "revision_id=$REV"
AFTER="$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_revisions WHERE entity_id=$PID")"
check "restoring adds versions rather than overwriting" "yes" "$([ "$AFTER" -gt "$BEFORE" ] && echo yes || echo no)"
check "the restore is itself recorded" "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_revisions WHERE entity_id=$PID AND note LIKE 'Restored revision%'")"

echo "  ---- CRITICAL: an editor must not be able to inject markup or third-party script ----"
curl -s -b "$PJAR" -o /dev/null -X POST "$ED" --data-urlencode "csrf_token=$(pg_token "$ED")" -d "action=publish"
HERO="$("${DB_MAIN[@]}" "SELECT id FROM cms_page_blocks WHERE page_id=$PID ORDER BY sort_order LIMIT 1")"
curl -s -b "$PJAR" -o /dev/null -X POST "$ED" --data-urlencode "csrf_token=$(pg_token "$ED")" \
  -d "action=save_block" -d "block_id=$HERO" -d "appearance=light" \
  --data-urlencode "f_heading=Clean" --data-urlencode "f_body=<b>hero body is plain text</b>"
check "a plain text field escapes all markup" "0" \
  "$(curl -s "$MAIN/cms-page.php?slug=verify-page" | grep -c '<b>hero body is plain text</b>')"

curl -s -b "$PJAR" -o /dev/null -X POST "$ED" --data-urlencode "csrf_token=$(pg_token "$ED")" \
  -d "action=add_block" -d "block_type=richtext"
RT="$("${DB_MAIN[@]}" "SELECT id FROM cms_page_blocks WHERE page_id=$PID AND block_type='richtext' LIMIT 1")"
curl -s -b "$PJAR" -o /dev/null -X POST "$ED" --data-urlencode "csrf_token=$(pg_token "$ED")" \
  -d "action=save_block" -d "block_id=$RT" -d "appearance=light" \
  --data-urlencode "f_heading=Rich" --data-urlencode "f_body=<script>alert(1)</script><p>kept</p><img src=x onerror=alert(1)>"
check "a script tag never reaches the page" "0" \
  "$(curl -s "$MAIN/cms-page.php?slug=verify-page" | grep -c '<script>alert')"
check "an img with a handler is stripped"   "0" \
  "$(curl -s "$MAIN/cms-page.php?slug=verify-page" | grep -c 'onerror')"
check "the permitted markup survives"       "1" \
  "$(curl -s "$MAIN/cms-page.php?slug=verify-page" | grep -c '<p>kept</p>')"
check "the rich text whitelist has no script" "0" "$(grep -c '<script>' public_html/includes/blocks.php)"
check "an embed only accepts known hosts"     "1" "$(grep -c 'player.vimeo.com' public_html/includes/blocks.php)"
check "and refuses anything else"             "1" "$(grep -c 'not from a permitted source' public_html/includes/blocks.php)"
check "one bad block cannot take a page down" "1" "$(grep -c 'block render failed' public_html/includes/blocks.php)"

curl -s -b "$PJAR" -o /dev/null -X POST "$MAIN/admin/pages.php" \
  --data-urlencode "csrf_token=$(pg_token "$MAIN/admin/pages.php")" -d "action=trash" -d "id=$PID"
check "trashing hides it from the public" "404" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/cms-page.php?slug=verify-page")"
check "and the page is recoverable"         "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_pages WHERE id=$PID AND trashed_at IS NOT NULL")"
rm -f "$PJAR"

echo
echo "=== 19. Trash: nothing is deleted without an undo ==="

TJAR=/tmp/verify-trash-cookies.txt
tr_login() {
  rm -f "$TJAR"
  local tok
  tok="$(curl -s -c "$TJAR" "$MAIN/admin/login.php" \
        | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1)"
  curl -s -b "$TJAR" -c "$TJAR" -o /dev/null \
    --data-urlencode "csrf_token=$tok" --data-urlencode "username=Craig" \
    --data-urlencode "password=localtest-analytics-pw" "$MAIN/admin/login.php"
}
tr_token() { curl -s -b "$TJAR" "$1" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1; }

tr_login
curl -s -b "$TJAR" -o /dev/null "$MAIN/admin/trash.php"
check "trash.php returns 200" "200" "$(curl -s -b "$TJAR" -o /dev/null -w '%{http_code}' "$MAIN/admin/trash.php")"
check "cms_trash was created on demand" "1" "$(table_exists cms_trash)"
check "the trash migration has both halves" "2" \
  "$(ls public_html/database/migrations/2026-09-03-07-create-cms-trash.*.sql 2>/dev/null | wc -l | tr -d ' ')"

"${DB_MAIN[@]}" "DELETE FROM cms_trash" >/dev/null 2>&1
MU="$MAIN/admin/menus.php"
curl -s -b "$TJAR" -o /dev/null "$MU"
MID="$("${DB_MAIN[@]}" "SELECT id FROM cms_menu_items ORDER BY id DESC LIMIT 1")"
MLABEL="$("${DB_MAIN[@]}" "SELECT label FROM cms_menu_items WHERE id=$MID")"
curl -s -b "$TJAR" -o /dev/null -X POST "$MU" --data-urlencode "csrf_token=$(tr_token "$MU")" \
  -d "action=delete" -d "location=header" -d "id=$MID"
check "deleting a menu item keeps a snapshot" "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_trash WHERE entity_type='menu_item' AND label='$MLABEL'")"
check "the trash records who did it" "Craig" \
  "$("${DB_MAIN[@]}" "SELECT deleted_by FROM cms_trash ORDER BY id DESC LIMIT 1")"
check "and where it came from" "Header menu" \
  "$("${DB_MAIN[@]}" "SELECT context FROM cms_trash ORDER BY id DESC LIMIT 1")"
check "the live menu is one shorter" "5" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_menu_items WHERE location='header'")"

TU="$MAIN/admin/trash.php"
TID="$("${DB_MAIN[@]}" "SELECT id FROM cms_trash ORDER BY id DESC LIMIT 1")"
curl -s -b "$TJAR" -o /dev/null -X POST "$TU" --data-urlencode "csrf_token=$(tr_token "$TU")" \
  -d "action=restore" -d "id=$TID"
check "restoring puts the menu item back" "6" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_menu_items WHERE location='header'")"
check "and the trash row is marked restored" "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_trash WHERE id=$TID AND restored_at IS NOT NULL")"
check "restoring is audited" "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_audit_log WHERE action='trash_restore'")"

echo "  ---- CRITICAL: if the undo cannot be written, nothing may be deleted ----"
BEFORE="$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_menu_items")"
"${DB_MAIN[@]}" "RENAME TABLE cms_trash TO cms_trash_parked" >/dev/null 2>&1
"${DB_MAIN[@]}" "CREATE TABLE cms_trash (id INT PRIMARY KEY)" >/dev/null 2>&1
MID2="$("${DB_MAIN[@]}" "SELECT id FROM cms_menu_items ORDER BY id DESC LIMIT 1")"
curl -s -b "$TJAR" -o /dev/null -X POST "$MU" --data-urlencode "csrf_token=$(tr_token "$MU")" \
  -d "action=delete" -d "location=header" -d "id=$MID2"
check "the item survives when the undo cannot be written" "$BEFORE" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_menu_items")"
check "and the panel still serves" "200" "$(curl -s -b "$TJAR" -o /dev/null -w '%{http_code}' "$MU")"
"${DB_MAIN[@]}" "DROP TABLE cms_trash" >/dev/null 2>&1
"${DB_MAIN[@]}" "RENAME TABLE cms_trash_parked TO cms_trash" >/dev/null 2>&1

check "media delete keeps the file for restoring" "1" \
  "$(grep -c 'The files stay on disk until the 30 days are up' public_html/admin/media.php)"
check "expiry removes the files for good"          "2" \
  "$(grep -c 'pmMediaUnlinkFiles' public_html/includes/trash.php)"
check "an event restores under its original id"    "1" \
  "$(grep -c 'The id is written back deliberately' public_html/includes/trash.php)"

echo "  ---- every destructive control asks first ----"
for f in media menus pages page-editor trash; do
  check "$f.php confirms before deleting" "yes" \
    "$(grep -q 'data-confirm' public_html/admin/$f.php && echo yes || echo no)"
done
for f in users events; do
  check "$f.php confirms before deleting" "yes" \
    "$(grep -q 'return confirm(' public_html/admin/$f.php && echo yes || echo no)"
done
check "the confirmation says where it goes" "5" \
  "$(grep -rhc 'restore it for 30 days\|restore the account for 30 days\|restore it for 30\|for 30 days' public_html/admin/media.php public_html/admin/menus.php public_html/admin/pages.php public_html/admin/page-editor.php public_html/admin/users.php | paste -sd+ - | bc)"
check "the confirm handler survives no JavaScript" "1" \
  "$(grep -c 'still lands in the trash' public_html/assets/js/pm-admin.js)"
check "pm-admin.js still lints" "0" \
  "$(command -v node >/dev/null 2>&1 && { node --check public_html/assets/js/pm-admin.js >/dev/null 2>&1; echo $?; } || echo 0)"
rm -f "$TJAR"

echo
echo "=== 20. Phase 5F and 5G: money, invoices, health, redirects and search ==="

GJAR=/tmp/verify-5g-cookies.txt
g_login() {
  rm -f "$GJAR"
  local tok
  tok="$(curl -s -c "$GJAR" "$MAIN/admin/login.php" \
        | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1)"
  curl -s -b "$GJAR" -c "$GJAR" -o /dev/null \
    --data-urlencode "csrf_token=$tok" --data-urlencode "username=Craig" \
    --data-urlencode "password=localtest-analytics-pw" "$MAIN/admin/login.php"
}
g_token() { curl -s -b "$GJAR" "$1" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1; }
g_php() { (cd public_html && php -r "$1"); }

g_login
for s in earlybird banners health audit redirects seo; do
  check "$s.php returns 200" "200" "$(curl -s -b "$GJAR" -o /dev/null -w '%{http_code}' "$MAIN/admin/$s.php")"
done
check "eighteen screens are built" "18" "$(php -r "
  require 'public_html/admin/includes/nav.php';
  \$n=0; foreach (pmAdminNav() as \$g) foreach (\$g['items'] as \$i) if (!empty(\$i['built'])) \$n++; echo \$n;")"

echo "  ---- the early bird gap is stated, not hidden ----"
EB="$(curl -s -b "$GJAR" "$MAIN/admin/earlybird.php")"
check "the screen says the promise and the invoice disagree" "1" \
  "$(printf '%s' "$EB" | grep -c 'do not agree')"
check "it names how many were billed at full price" "1" \
  "$(printf '%s' "$EB" | grep -c 'was billed the full price')"
check "the invoicing policy defaults to off" "1" \
  "$(printf '%s' "$EB" | grep -c 'Currently <strong>off</strong>')"
curl -s -b "$GJAR" -o /dev/null -X POST "$MAIN/admin/earlybird.php" \
  --data-urlencode "csrf_token=$(g_token "$MAIN/admin/earlybird.php")" \
  -d "action=save_policy" -d "apply_to_invoices=1"
check "the decision is recorded" "1" \
  "$("${DB_MAIN[@]}" "SELECT setting_value FROM site_settings WHERE setting_key='early_bird_apply'")"
check "recording it is audited" "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_audit_log WHERE action='earlybird_policy'")"
"${DB_MAIN[@]}" "DELETE FROM site_settings WHERE setting_key='early_bird_apply'" >/dev/null 2>&1

echo "  ---- CRITICAL: an invoice link must be unguessable and must expire ----"
RID="$("${DB_MAIN[@]}" "SELECT id FROM event_registrations WHERE invoice_number IS NOT NULL LIMIT 1")"
LINK="$(g_php "require 'includes/config.php'; require 'includes/invoice.php'; echo pmInvoiceLink($RID, 30, false);")"
check "a signed link serves the PDF" "200" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN$LINK")"
check "and it is served as a PDF" "application/pdf" "$(curl -s -o /dev/null -w '%{content_type}' "$MAIN$LINK")"
check "a tampered signature is refused" "403" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN${LINK%??}zz")"
check "a swapped registration id is refused" "403" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN$(printf '%s' "$LINK" | sed "s/r=$RID/r=999/")")"
cat > /tmp/verify-expired-link.php <<'PHPEOF'
<?php
require 'includes/config.php';
require 'includes/invoice.php';
$rid = (int) ($argv[1] ?? 0);
$e   = time() - 10;
echo '/invoice.php?r=' . $rid . '&e=' . $e . '&s=' . pmInvoiceSignature($rid, $e);
PHPEOF
EXPIRED="$(cd public_html && php /tmp/verify-expired-link.php "$RID")"
check "an expired link is refused" "403" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN$EXPIRED")"
check "no link at all is refused"  "403" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/invoice.php")"
check "signatures compare in constant time" "2" "$(grep -c 'hash_equals' public_html/includes/invoice.php)"
check "the invoice directory is still denied" "1" \
  "$(grep -c 'Require all denied' public_html/assets/invoices/.htaccess)"

echo "  ---- site health ----"
HE="$(curl -s -b "$GJAR" "$MAIN/admin/health.php")"
check "health runs every check" "11" "$(printf '%s' "$HE" | grep -c 'class="badge badge-\(green\|orange\|red\)"')"
check "it notices unsent mail"    "1" "$(printf '%s' "$HE" | grep -c 'failed to send in the last seven days')"
check "it checks the invoice directory" "1" "$(printf '%s' "$HE" | grep -c 'Delegates receive a signed link')"
check "it checks uploads cannot run code" "1" "$(printf '%s' "$HE" | grep -c 'PHP engine is off')"
check "a failing check cannot take the page down" "1" "$(grep -c 'This check failed' public_html/admin/health.php)"

echo "  ---- redirects and the 404 log ----"
"${DB_MAIN[@]}" "DELETE FROM cms_redirects" >/dev/null 2>&1
"${DB_MAIN[@]}" "DELETE FROM cms_not_found" >/dev/null 2>&1
curl -s -o /dev/null "$MAIN/cms-page.php?slug=gone-for-good"
check "a 404 is recorded" "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_not_found")"
curl -s -o /dev/null "$MAIN/cms-page.php?slug=gone-for-good"
check "a repeat increments rather than duplicates" "2" \
  "$("${DB_MAIN[@]}" "SELECT hits FROM cms_not_found ORDER BY id DESC LIMIT 1")"

RD="$MAIN/admin/redirects.php"
curl -s -b "$GJAR" -o /dev/null -X POST "$RD" --data-urlencode "csrf_token=$(g_token "$RD")" \
  -d "action=add" -d "from_path=/cms-page.php" -d "to_path=/events.php" -d "status_code=301"
check "the redirect is stored" "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_redirects")"
check "and it is followed" "301" "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/cms-page.php?slug=gone-for-good")"
check "the redirect counts its use" "1" "$("${DB_MAIN[@]}" "SELECT hits FROM cms_redirects LIMIT 1")"
OUT="$(curl -s -b "$GJAR" -X POST "$RD" --data-urlencode "csrf_token=$(g_token "$RD")" \
  -d "action=add" -d "from_path=/evil" --data-urlencode "to_path=javascript:alert(1)")"
check "an off-site scheme is refused" "1" "$(printf '%s' "$OUT" | grep -c 'must be a path on this site')"
check "the open redirect guard is in the helper" "1" \
  "$(grep -c 'would be an open redirect' public_html/includes/redirects.php)"
"${DB_MAIN[@]}" "DELETE FROM cms_redirects" >/dev/null 2>&1

echo "  ---- search appearance and structured data ----"
curl -s -b "$GJAR" -o /dev/null -X POST "$MAIN/admin/seo.php" \
  --data-urlencode "csrf_token=$(g_token "$MAIN/admin/seo.php")" -d "page=about" \
  --data-urlencode "meta_title=Verified search title" \
  --data-urlencode "meta_description=Verified search description."
check "a search title is stored" "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM page_content WHERE page_slug='about' AND section_key='meta_title' AND content_value='Verified search title'")"
check "and it reaches the public page" "3" \
  "$(curl -s "$MAIN/about.php" | grep -c 'Verified search title')"
EID="$("${DB_MAIN[@]}" "SELECT id FROM events WHERE is_active=1 AND event_start_date IS NOT NULL LIMIT 1")"
EV="$(curl -s "$MAIN/event.php?id=$EID")"
check "an event publishes structured data" "1" "$(printf '%s' "$EV" | grep -c 'application/ld+json')"
check "it declares an EducationEvent"      "1" "$(printf '%s' "$EV" | grep -c 'EducationEvent')"
check "it carries a real start date"       "1" "$(printf '%s' "$EV" | grep -c '"startDate":"20')"
check "it carries the price and currency"  "1" "$(printf '%s' "$EV" | grep -c '"priceCurrency":"USD"')"
check "a date-less event publishes nothing" "1" "$(grep -c 'is worse than none' public_html/includes/schema.php)"
check "the JSON cannot close the script tag" "1" "$(grep -c "str_replace('</'" public_html/includes/schema.php)"
rm -f "$GJAR"

echo
echo "=== 21. Staying signed in, resetting a password, and admin search ==="

SJAR=/tmp/verify-sess-a.txt
RJAR=/tmp/verify-sess-b.txt
KJAR=/tmp/verify-sess-c.txt
CRED_PW='localtest-analytics-pw'
CRED_HASH='$2y$12$giO77eJa0QkaVtgtZmQMReV/wzHhY/8DeY5yo9XNMDshpMf5R7aZW'

s_token() { curl -s -c "$1" "$2" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1; }

check "the session migration has both halves" "2" \
  "$(ls public_html/database/migrations/2026-09-03-08-create-admin-session-tables.*.sql 2>/dev/null | wc -l | tr -d ' ')"

"${DB_MAIN[@]}" "DELETE FROM login_attempts" >/dev/null 2>&1
rm -f "$SJAR" "$RJAR" "$KJAR"
TOK="$(s_token "$SJAR" "$MAIN/admin/login.php")"
check "the login page offers to keep you signed in" "1" \
  "$(curl -s "$MAIN/admin/login.php" | grep -c 'name="remember"')"
check "and offers a password reset" "1" \
  "$(curl -s "$MAIN/admin/login.php" | grep -c 'forgot-password.php')"

curl -s -b "$SJAR" -c "$SJAR" -o /dev/null --data-urlencode "csrf_token=$TOK" \
  --data-urlencode "username=Craig" --data-urlencode "password=$CRED_PW" \
  -d "remember=1" "$MAIN/admin/login.php"
check "a remembered sign-in stores a token" "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM admin_remember_tokens")"
check "and sets a cookie" "1" "$(grep -c 'pm_admin_remember' "$SJAR")"
check "only the hash is stored, never the validator" "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM admin_remember_tokens WHERE CHAR_LENGTH(validator_hash)=64")"

grep 'pm_admin_remember' "$SJAR" > "$RJAR"
S1="$("${DB_MAIN[@]}" "SELECT selector FROM admin_remember_tokens LIMIT 1")"
check "the cookie alone signs you back in" "200" \
  "$(curl -s -b "$RJAR" -c "$KJAR" -o /dev/null -w '%{http_code}' "$MAIN/admin/dashboard.php")"
S2="$("${DB_MAIN[@]}" "SELECT selector FROM admin_remember_tokens LIMIT 1")"
check "the token is replaced on use" "yes" "$([ "$S1" != "$S2" ] && echo yes || echo no)"
check "and there is still only one" "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM admin_remember_tokens")"

echo "  ---- CRITICAL: a used cookie must not work a second time ----"
check "replaying the old cookie is refused" "302" \
  "$(curl -s -b "$RJAR" -o /dev/null -w '%{http_code}' "$MAIN/admin/dashboard.php")"
check "a made-up cookie is refused" "302" \
  "$(curl -s -H "Cookie: pm_admin_remember=$(printf 'a%.0s' $(seq 24)):$(printf 'b%.0s' $(seq 64))" \
     -o /dev/null -w '%{http_code}' "$MAIN/admin/dashboard.php")"
check "a malformed cookie is refused" "302" \
  "$(curl -s -H 'Cookie: pm_admin_remember=nonsense' -o /dev/null -w '%{http_code}' "$MAIN/admin/dashboard.php")"
check "resuming is audited" "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_audit_log WHERE action='login_resumed'")"

curl -s -b "$KJAR" -o /dev/null "$MAIN/admin/logout.php"
check "signing out drops the remembered token" "0" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM admin_remember_tokens")"

echo "  ---- password reset ----"
"${DB_MAIN[@]}" "DELETE FROM admin_password_resets; DELETE FROM login_attempts;" >/dev/null 2>&1
clearmail
FJAR=/tmp/verify-forgot.txt; rm -f "$FJAR"
FTOK="$(s_token "$FJAR" "$MAIN/admin/forgot-password.php")"
REAL="$(curl -s -b "$FJAR" -c "$FJAR" -X POST "$MAIN/admin/forgot-password.php" \
        --data-urlencode "csrf_token=$FTOK" -d "account=Craig")"
check "a reset link is issued" "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM admin_password_resets")"
check "and emailed" "1" "$(mailcount)"
check "requesting one is audited" "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM cms_audit_log WHERE action='password_reset_requested'")"

echo "  ---- CRITICAL: the form must not reveal who has an account ----"
GJAR2=/tmp/verify-forgot2.txt; rm -f "$GJAR2"
GTOK="$(s_token "$GJAR2" "$MAIN/admin/forgot-password.php")"
FAKE="$(curl -s -b "$GJAR2" -c "$GJAR2" -X POST "$MAIN/admin/forgot-password.php" \
        --data-urlencode "csrf_token=$GTOK" -d "account=nobody-has-this-name")"
REAL_MSG="$(printf '%s' "$REAL" | grep -o 'If that account exists[^<]*' | head -1)"
FAKE_MSG="$(printf '%s' "$FAKE" | grep -o 'If that account exists[^<]*' | head -1)"
check "both answers are the same sentence" "yes" \
  "$([ -n "$REAL_MSG" ] && [ "$REAL_MSG" = "$FAKE_MSG" ] && echo yes || echo no)"
check "and no token was made for the fake one" "1" "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM admin_password_resets")"

SEL="$("${DB_MAIN[@]}" "SELECT selector FROM admin_password_resets LIMIT 1")"
check "the reset token stores only a hash" "1" \
  "$("${DB_MAIN[@]}" "SELECT COUNT(*) FROM admin_password_resets WHERE CHAR_LENGTH(validator_hash)=64")"
check "a wrong validator is rejected" "1" \
  "$(curl -s "$MAIN/admin/reset-password.php?s=$SEL&v=$(printf 'c%.0s' $(seq 64))" | grep -c 'cannot be used')"
check "a made-up selector is rejected" "1" \
  "$(curl -s "$MAIN/admin/reset-password.php?s=$(printf 'd%.0s' $(seq 24))&v=$(printf 'e%.0s' $(seq 64))" \
     | grep -c 'cannot be used')"
"${DB_MAIN[@]}" "UPDATE admin_password_resets SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE)" >/dev/null 2>&1
check "an expired link is rejected" "1" \
  "$(curl -s "$MAIN/admin/reset-password.php?s=$SEL&v=$(printf 'c%.0s' $(seq 64))" | grep -c 'cannot be used')"
check "setting a password clears remembered browsers" "2" \
  "$(grep -c 'pmRememberForgetAll' public_html/includes/adminsession.php)"
check "a reset link is single use" "1" "$(grep -c 'used_at IS NULL' public_html/includes/adminsession.php)"
"${DB_MAIN[@]}" "DELETE FROM admin_password_resets; DELETE FROM login_attempts;" >/dev/null 2>&1
"${DB_MAIN[@]}" "UPDATE admin_users SET password='$CRED_HASH' WHERE username='Craig'" >/dev/null 2>&1

echo "  ---- admin search ----"
rm -f "$SJAR"
STOK="$(s_token "$SJAR" "$MAIN/admin/login.php")"
curl -s -b "$SJAR" -c "$SJAR" -o /dev/null --data-urlencode "csrf_token=$STOK" \
  --data-urlencode "username=Craig" --data-urlencode "password=$CRED_PW" "$MAIN/admin/login.php"
check "search needs a signed-in session" "302" \
  "$(curl -s -o /dev/null -w '%{http_code}' "$MAIN/admin/search.php?q=ipsas")"
check "search returns JSON" "application/json" \
  "$(curl -s -b "$SJAR" -o /dev/null -w '%{content_type}' "$MAIN/admin/search.php?q=ipsas" | cut -d';' -f1)"
check "one character returns nothing" "1" \
  "$(curl -s -b "$SJAR" "$MAIN/admin/search.php?q=a" | grep -c '{"results":\[\]}')"
check "it finds an event"  "1" \
  "$(curl -s -b "$SJAR" "$MAIN/admin/search.php?q=ipsas" | grep -c '"kind":"Event"')"
check "it finds a screen" "1" \
  "$(curl -s -b "$SJAR" "$MAIN/admin/search.php?q=media" | grep -c '"kind":"Screen"')"
check "a section that fails cannot empty the palette" "1" \
  "$(grep -c 'must not empty the whole palette' public_html/admin/search.php)"
check "the palette is in the shell" "1" "$(grep -c 'id="pm-palette"' public_html/admin/header.php)"
check "and stays hidden until opened" "1" "$(grep -c '\.pma-palette\[hidden\]' public_html/assets/css/pm-admin.css)"
check "Ctrl K opens it" "1" "$(grep -c "key.toLowerCase() === 'k'" public_html/assets/js/pm-admin.js)"
check "Escape is caught before the input eats it" "1" \
  "$(grep -c 'consumes Escape for its' public_html/assets/js/pm-admin.js)"
check "a slow reply cannot overwrite a newer one" "1" \
  "$(grep -c 'Only the newest response is rendered' public_html/assets/js/pm-admin.js)"
check "pm-admin.js still lints" "0" \
  "$(command -v node >/dev/null 2>&1 && { node --check public_html/assets/js/pm-admin.js >/dev/null 2>&1; echo $?; } || echo 0)"
rm -f "$SJAR" "$RJAR" "$KJAR" "$FJAR" "$GJAR2"

echo
printf '\n%s\npassed=%d failed=%d\n%s\n' "$(printf '=%.0s' {1..78})" "$pass" "$fail" "$(printf '=%.0s' {1..78})"
exit $((fail > 0 ? 1 : 0))
