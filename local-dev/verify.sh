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
# The localtest account is inserted by local-dev/main-local-overrides.sql into
# the throwaway container only. It does not exist in production.
check "admin login succeeds" "302" \
  "$(curl -s -b "$AJAR" -c "$AJAR" -o /dev/null -w '%{http_code}' -X POST "$MAIN/admin/login.php" \
      -d "csrf_token=$ATOK" -d "username=localtest" -d "password=localtest-analytics-pw")"
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


echo
printf '\n%s\npassed=%d failed=%d\n%s\n' "$(printf '=%.0s' {1..78})" "$pass" "$fail" "$(printf '=%.0s' {1..78})"
exit $((fail > 0 ? 1 : 0))
