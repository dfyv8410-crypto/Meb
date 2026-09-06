#!/usr/bin/env bash
# MEB API integration test. Drives scripts/apirun.php against the framework.
set -u
cd "$(dirname "$0")/.."
PHP="env -u LD_LIBRARY_PATH timeout 15 php scripts/apirun.php"
PASS=0; FAIL=0
ok(){ PASS=$((PASS+1)); echo "  OK  $1"; }
bad(){ FAIL=$((FAIL+1)); echo "  FAIL $1 -- $2"; }

# --- login ---
LOGIN=$(METHOD=POST PATH_=/auth/login BODY='{"email":"admin@meb.local","password":"Sup3rSecret!"}' $PHP 2>/dev/null)
STATUS=$(echo "$LOGIN" | head -1 | cut -d: -f2)
[ "$STATUS" = "200" ] && ok "login 200" || bad "login" "$STATUS"
TOKEN=$(echo "$LOGIN" | tail -1 | env -u LD_LIBRARY_PATH php -r '$j=json_decode(stream_get_contents(STDIN),true);echo $j["data"]["token"]??"";' 2>/dev/null)
[ -n "$TOKEN" ] && ok "login returned token" || bad "token" "empty"

RH="--_MEB_AUTH_=$TOKEN"

run(){ # run <name> <method> <path> <body> <expectStatus>
  local name=$1 m=$2 p=$3 b=$4 expect=$5
  local auth=${AUTH-$TOKEN}
  local out
  out=$(METHOD=$m PATH_=$p AUTH="Bearer $auth" BODY="$b" $PHP 2>/dev/null)
  local st=$(echo "$out" | head -1 | cut -d: -f2)
  if [ "$st" = "$expect" ]; then ok "$name ($m $p -> $st)"; else bad "$name ($m $p -> $st)" "$out"; fi
}

# --- me ---
AUTH="$TOKEN" run "me" GET /me '' 200
# --- unauthorized (wrong token) ---
AUTH="bad.token.here" run "me unauthorized" GET /me '' 401
# --- no auth ---
AUTH="" run "me noauth" GET /me '' 401

# --- analytics ---
AUTH="$TOKEN" run "analytics summary" GET /analytics/summary '' 200

# --- crud list ---
AUTH="$TOKEN" run "catalog list" GET /catalog '' 200
AUTH="$TOKEN" run "projects list" GET /projects '' 200
AUTH="$TOKEN" run "leads list" GET /leads '' 200
AUTH="$TOKEN" run "pages list" GET /pages '' 200
AUTH="$TOKEN" run "reviews list" GET /reviews '' 200
AUTH="$TOKEN" run "users list" GET /users '' 200
AUTH="$TOKEN" run "media list" GET /media '' 200
AUTH="$TOKEN" run "notifications" GET /notifications '' 200
AUTH="$TOKEN" run "audit_log" GET /audit_log '' 200
AUTH="$TOKEN" run "seo audit" GET /seo/audit '' 200
AUTH="$TOKEN" run "system update check" GET /system/update/check '' 200
AUTH="$TOKEN" run "app latest (404 no apk)" GET /app/latest '' 404

# --- settings ---
AUTH="$TOKEN" run "settings get" GET /settings '' 200
AUTH="$TOKEN" run "settings put" PUT /settings '{"siteName":"MEB Premium"}' 200

# --- crud create/update ---
CREATE=$(METHOD=POST PATH_=/catalog AUTH="Bearer $TOKEN" BODY='{"title":"Тест кресло","slug":"test-chair","desc":"пробное"}' $PHP 2>/dev/null)
ST=$(echo "$CREATE" | head -1 | cut -d: -f2)
NEWID=$(echo "$CREATE" | tail -1 | env -u LD_LIBRARY_PATH php -r '$j=json_decode(stream_get_contents(STDIN),true);echo $j["data"]["id"]??"";' 2>/dev/null)
[ "$ST" = "200" ] && ok "catalog create" || bad "catalog create" "$CREATE"
[ -n "$NEWID" ] && ok "catalog created id=$NEWID" || bad "catalog id" "$CREATE"
AUTH="$TOKEN" run "catalog update" PUT "/catalog/$NEWID" '{"title":"Тест кресло 2"}' 200
AUTH="$TOKEN" run "catalog delete" DELETE "/catalog/$NEWID" '' 200

# --- leads create (admin) + public ---
AUTH="$TOKEN" run "lead create admin" POST /leads '{"name":"Иван","phone":"+7"}' 200
AUTH="" run "lead create public" POST /leads-public '{"name":"Пётр","phone":"+7900"}' 200
AUTH="" run "public lead bad" POST /leads-public '{"name":"","phone":""}' 400

# --- reviews public ---
AUTH="" run "review submit" POST /reviews-public '{"name":"Тестер","text":"Отличная мебель!","rating":5}' 200
AUTH="" run "review bad" POST /reviews-public '{"author":"","text":""}' 400

# --- menu ---
AUTH="$TOKEN" run "menu list" GET /menu '' 200
MENU_OUT=$(METHOD=POST PATH_=/menu AUTH="Bearer $TOKEN" BODY='{"title":"Тест","url":"/test","sort_order":1,"is_active":1}' $PHP 2>/dev/null)
MENU_ID=$(echo "$MENU_OUT" | tail -1 | env -u LD_LIBRARY_PATH php -r '$j=json_decode(stream_get_contents(STDIN),true);echo $j["data"]["id"]??"";' 2>/dev/null)
MENU_ST=$(echo "$MENU_OUT" | head -1 | cut -d: -f2)
[ "$MENU_ST" = "201" ] && ok "menu create (POST /menu -> $MENU_ST)" || bad "menu create" "$MENU_OUT"
[ -n "$MENU_ID" ] && ok "menu created id=$MENU_ID" || bad "menu id" "empty"
AUTH="$TOKEN" run "menu update" PUT "/menu/$MENU_ID" '{"title":"Тест 2","url":"/test2","sort_order":2,"is_active":0}' 200
AUTH="$TOKEN" run "menu delete" DELETE "/menu/$MENU_ID" '' 200

# --- backup ---
AUTH="$TOKEN" run "backup create" POST /backup/create '' 200
AUTH="$TOKEN" run "backup list" GET /backup/list '' 200

# --- editor cannot admin ---
# users list requires admin; we only have super_admin, so this passes; skip

echo
echo "RESULT: $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ] || exit 1
echo "ALL OK"
