#!/usr/bin/env bash
# Post one registration to the LOCAL CPD dev server and print status + redirect.
#
#   local-dev/test-register-cpd.sh [email]
#
# Environment:
#   TOKEN_MODE=valid    (default) fetch the form, reuse its session + CSRF token
#   TOKEN_MODE=missing  send no csrf_token at all
#   TOKEN_MODE=forged   send a well-formed but wrong csrf_token
#   TOKEN_MODE=nosession send a valid token without the session cookie
#   EVENT_ID=13         which CPD event to register for
#
# BASE must stay on 127.0.0.1. This never talks to production.
set -euo pipefail

BASE="${BASE:-http://127.0.0.1:8081}"
EMAIL="${1:-cpd-delegate@example.test}"
EVENT_ID="${EVENT_ID:-13}"
TOKEN_MODE="${TOKEN_MODE:-valid}"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

PAGE="$(curl -s -c "$JAR" "$BASE/registration_page.php?event_id=$EVENT_ID")"
TOKEN="$(printf '%s' "$PAGE" | sed -n 's/.*name="csrf_token" value="\([a-f0-9]*\)".*/\1/p' | head -1)"

if [ -z "$TOKEN" ] && [ "$TOKEN_MODE" = "valid" ]; then
  echo "FAILED: no csrf_token found in the rendered form" >&2
  exit 1
fi

TOKEN_ARGS=(--data-urlencode "csrf_token=$TOKEN")
COOKIE_ARGS=(-b "$JAR")
case "$TOKEN_MODE" in
  missing)   TOKEN_ARGS=() ;;
  forged)    TOKEN_ARGS=(--data-urlencode "csrf_token=$(printf 'a%.0s' {1..64})") ;;
  nosession) COOKIE_ARGS=() ;;
esac

curl -s -o /dev/null -w "status=%{http_code} location=[%{redirect_url}]\n" \
  ${COOKIE_ARGS[@]+"${COOKIE_ARGS[@]}"} -X POST "$BASE/register.php" \
  ${TOKEN_ARGS[@]+"${TOKEN_ARGS[@]}"} \
  --data-urlencode "event_id=$EVENT_ID" \
  --data-urlencode "first_name=Cpd" \
  --data-urlencode "last_name=Delegate" \
  --data-urlencode "phone=+254700000009" \
  --data-urlencode "email=$EMAIL" \
  --data-urlencode "organization=Local Test Org"
