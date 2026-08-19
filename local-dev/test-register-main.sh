#!/usr/bin/env bash
# Post one registration to the LOCAL main-site dev server and print the JSON.
#
#   local-dev/test-register-main.sh [email]
#
# Environment:
#   TOKEN_MODE=valid    (default) fetch the form, reuse its session + CSRF token
#   TOKEN_MODE=missing  send no csrf_token at all
#   TOKEN_MODE=forged   send a well-formed but wrong csrf_token
#   TOKEN_MODE=nosession send a valid-looking token without the session cookie
#
# BASE must stay on 127.0.0.1. This never talks to production.
set -euo pipefail

BASE="${BASE:-http://127.0.0.1:8080}"
EMAIL="${1:-delegate@example.test}"
TOKEN_MODE="${TOKEN_MODE:-valid}"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

# Load the registration form the way a browser would: this sets the session
# cookie and renders the hidden csrf_token that belongs to it.
PAGE="$(curl -s -c "$JAR" "$BASE/event-registration.php?id=2")"
TOKEN="$(printf '%s' "$PAGE" | sed -n 's/.*name="csrf_token" value="\([a-f0-9]*\)".*/\1/p' | head -1)"

if [ -z "$TOKEN" ] && [ "$TOKEN_MODE" = "valid" ]; then
  echo "FAILED: no csrf_token found in the rendered form" >&2
  exit 1
fi

TOKEN_ARGS=(-F "csrf_token=$TOKEN")
COOKIE_ARGS=(-b "$JAR")
case "$TOKEN_MODE" in
  missing)   TOKEN_ARGS=() ;;
  forged)    TOKEN_ARGS=(-F "csrf_token=$(printf 'a%.0s' {1..64})") ;;
  nosession) COOKIE_ARGS=() ;;
esac

# The +"..." forms keep `set -u` happy when an array is empty (bash 3.2 on macOS).
curl -s ${COOKIE_ARGS[@]+"${COOKIE_ARGS[@]}"} -X POST "$BASE/process-registration.php" \
  ${TOKEN_ARGS[@]+"${TOKEN_ARGS[@]}"} \
  -F "event_id=2" \
  -F "event_name=IPSAS Clean-Audit Mastery & Intelligent Assets Accounting" \
  -F "first_name=Test" \
  -F "last_name=Delegate" \
  -F "phone=+254700000000" \
  -F "email=$EMAIL" \
  -F "organization=Local Test Ministry of Finance" \
  -F "country=Kenya" \
  -F "address=1 Test Street, Nairobi, 00100, Kenya" \
  -F "gender=Female" \
  -F "meal_preference=Vegetarian" \
  -F "future_topics=Local testing only" \
  -F "consent=on" \
  -F "attendees[first_name][]=Test" \
  -F "attendees[last_name][]=Delegate" \
  -F "attendees[email][]=$EMAIL" \
  -F "attendees[title][]=Director of Testing" \
  -F "attendees[first_name][]=Second" \
  -F "attendees[last_name][]=Delegate" \
  -F "attendees[email][]=second.$EMAIL" \
  -F "attendees[title][]=Deputy Tester"
echo
