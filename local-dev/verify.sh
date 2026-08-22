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
printf '\n%s\npassed=%d failed=%d\n%s\n' "$(printf '=%.0s' {1..78})" "$pass" "$fail" "$(printf '=%.0s' {1..78})"
exit $((fail > 0 ? 1 : 0))
