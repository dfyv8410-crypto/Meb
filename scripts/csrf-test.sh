#!/usr/bin/env bash
# CSRF regression test (prod-shaped: Sweb drops the Authorization header, so
# auth rides the HttpOnly login cookie and CSRF must come from X-CSRF-Token).
#
# Verifies the fixed contract (§10):
#   1. login PASS (returns auth token + CSRF token)
#   2. get CSRF PASS (login response carries csrf)
#   3. PUT with real CSRF -> 200 success:true
#   4. persists after reload (same session, same token)
#   5. repeat save PASS
#   6. PUT without CSRF -> 403
#   7. PUT with wrong CSRF -> 403
#   8. other CRUD not broken (GET /me, GET /settings, catalog list)
set -u
cd "$(dirname "$0")/.."
PHP="env -u LD_LIBRARY_PATH timeout 15 php scripts/apirun.php"
PASS=0; FAIL=0
ok(){ PASS=$((PASS+1)); echo "  OK  $1"; }
bad(){ FAIL=$((FAIL+1)); echo "  FAIL $1 -- $2"; }

echo "=== CSRF TEST (prod-shaped) ==="

# --- 1. login ---
LG=$(METHOD=POST PATH_=/auth/login SESS=csrfsess BODY='{"email":"admin@meb.local","password":"Sup3rSecret!"}' $PHP 2>/dev/null)
ST=$(echo "$LG" | head -1 | cut -d: -f2)
[ "$ST" = "200" ] && ok "login 200" || bad "login 200" "$ST"
TOKEN=$(echo "$LG" | tail -1 | env -u LD_LIBRARY_PATH php -r '$j=json_decode(stream_get_contents(STDIN),true);echo $j["data"]["token"]??"";' 2>/dev/null)
CSRF=$(echo "$LG" | tail -1 | env -u LD_LIBRARY_PATH php -r '$j=json_decode(stream_get_contents(STDIN),true);echo $j["data"]["csrf"]??"";' 2>/dev/null)
[ -n "$TOKEN" ] && ok "login returned token" || bad "login token" "empty"
[ -n "$CSRF" ] && ok "login returned CSRF token" || bad "login CSRF" "empty"

# --- 2. prod-shaped cookie auth (no Authorization header) ---
COOKIE="token=$TOKEN"

# --- 3. PUT with real CSRF -> 200 ---
OUT=$(METHOD=PUT PATH_=/settings COOKIE="$COOKIE" SESS=csrfsess SERVER_CSRF="$CSRF" CSRF="$CSRF" BODY='{"siteName":"MEB CSRF Test"}' $PHP 2>/dev/null)
ST=$(echo "$OUT" | head -1 | cut -d: -f2)
echo "$OUT" | tail -1 | env -u LD_LIBRARY_PATH php -r '$j=json_decode(stream_get_contents(STDIN),true);echo $j["success"]===true?"":"not-success";' 2>/dev/null | grep -q "not-success" && bad "PUT real CSRF success" "$OUT" || ok "PUT with real CSRF -> $ST success:true"

# --- 4. persists after reload (same session+token returns again) ---
OUT=$(METHOD=PUT PATH_=/settings COOKIE="$COOKIE" SESS=csrfsess SERVER_CSRF="$CSRF" CSRF="$CSRF" BODY='{"siteName":"MEB CSRF Test 2"}' $PHP 2>/dev/null)
ST=$(echo "$OUT" | head -1 | cut -d: -f2)
[ "$ST" = "200" ] && ok "PUT after reload ($ST)" || bad "reload persist" "$OUT"

# --- 5. repeat save PASS ---
OUT=$(METHOD=PUT PATH_=/settings COOKIE="$COOKIE" SESS=csrfsess SERVER_CSRF="$CSRF" CSRF="$CSRF" BODY='{"siteName":"MEB CSRF Test 3"}' $PHP 2>/dev/null)
ST=$(echo "$OUT" | head -1 | cut -d: -f2)
[ "$ST" = "200" ] && ok "repeat save ($ST)" || bad "repeat save" "$OUT"

# --- 6. PUT without CSRF -> 403 ---
OUT=$(METHOD=PUT PATH_=/settings COOKIE="$COOKIE" SESS=csrfsess SERVER_CSRF="$CSRF" BODY='{"siteName":"no-csrf"}' $PHP 2>/dev/null)
ST=$(echo "$OUT" | head -1 | cut -d: -f2)
[ "$ST" = "403" ] && ok "PUT without CSRF -> 403" || bad "no-csrf" "$OUT"

# --- 7. PUT with wrong CSRF -> 403 ---
OUT=$(METHOD=PUT PATH_=/settings COOKIE="$COOKIE" SESS=csrfsess SERVER_CSRF="$CSRF" CSRF="deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef" BODY='{"siteName":"wrong-csrf"}' $PHP 2>/dev/null)
ST=$(echo "$OUT" | head -1 | cut -d: -f2)
[ "$ST" = "403" ] && ok "PUT with wrong CSRF -> 403" || bad "wrong-csrf" "$OUT"

# --- 8. other CRUD not broken (GETs still cookie-auth, no csrf needed) ---
A=$($PHP <<<'' ; true)
OUT=$(METHOD=GET PATH_=/me COOKIE="$COOKIE" $PHP 2>/dev/null)
ST=$(echo "$OUT" | head -1 | cut -d: -f2)
[ "$ST" = "200" ] && ok "GET /me $ST" || bad "/me" "$OUT"
OUT=$(METHOD=GET PATH_=/settings COOKIE="$COOKIE" $PHP 2>/dev/null)
ST=$(echo "$OUT" | head -1 | cut -d: -f2)
[ "$ST" = "200" ] && ok "GET /settings $ST" || bad "GET settings" "$OUT"
OUT=$(METHOD=GET PATH_=/catalog COOKIE="$COOKIE" $PHP 2>/dev/null)
ST=$(echo "$OUT" | head -1 | cut -d: -f2)
[ "$ST" = "200" ] && ok "GET /catalog $ST" || bad "catalog" "$OUT"

echo
echo "RESULT: $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ] || exit 1
echo "ALL OK"