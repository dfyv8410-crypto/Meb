#!/usr/bin/env bash
#
# MEB — one-command local dev startup.
#   scripts/start-dev.sh            # starts MySQL (if needed) + php -S on 127.0.0.1:8099
#   HOST=0.0.0.0 PORT=8080 scripts/start-dev.sh
#
# Requirements: PHP >= 7.2 (CLI), MySQL/MariaDB server installed locally.
# After "DB ready / Server ready" — open http://<HOST>:<PORT>/
# Data/config come from installer/ or an already-generated config/database.php.
#
set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${PORT:-8099}"
HOST="${HOST:-127.0.0.1}"
SOCK="${MYSQL_SOCK:-/var/run/mysqld/mysqld.sock}"
PIDF="$ROOT/storage/dev/php-serve.pid"

# ---- pick a working PHP binary (this sandbox needs LD_LIBRARY_PATH cleared) ----
PHP_BIN="${PHP:-}"
if [ -z "$PHP_BIN" ]; then
  if env -u LD_LIBRARY_PATH php -v >/dev/null 2>&1; then PHP_BIN="env -u LD_LIBRARY_PATH php";
  elif php -v >/dev/null 2>&1; then PHP_BIN="php";
  else echo "PHP CLI not found (need php >= 7.2)"; exit 1; fi
fi

# ---- MySQL/MariaDB -----------------------------------------------------------
db_up() {
  [ -S "$SOCK" ] || return 1
  mysqladmin --socket="$SOCK" ping >/dev/null 2>&1
}

ensure_db() {
  if db_up; then echo "DB ready (already running)"; return 0; fi
  echo -n "Starting MariaDB/MySQL ... "
  (service mysql start || service mariadb start) >/dev/null 2>&1
  for _ in $(seq 1 30); do db_up && { echo "ok"; return 0; }; sleep 1; done
  # fallback: run mysqld directly (common inside containers/sandboxes).
  # setsid detaches it into its own session so it survives the launching shell.
  if [ -x /usr/sbin/mysqld ]; then
    setsid mysqld --socket="$SOCK" --skip-networking \
      > "${TMPDIR:-/tmp}/meb-mysqld.log" 2>&1 < /dev/null &
    for _ in $(seq 1 40); do db_up && { echo "ok (direct mysqld)"; return 0; }; sleep 1; done
  fi
  echo "FAILED — install/start MariaDB, then retry."; return 1
}

# ---- PHP dev server ----------------------------------------------------------
start_php() {
  [ -f "$ROOT/scripts/dev-router.php" ] || { echo "dev-router.php missing"; return 1; }
  if [ -f "$PIDF" ] && kill -0 "$(cat "$PIDF")" 2>/dev/null; then
    echo "Server already running (pid $(cat "$PIDF")) → http://$HOST:$PORT/"
    return 2
  fi
  cd "$ROOT" || return 1
  # setsid: own session/sid so the server survives the launching shell.
  setsid bash -c "echo \$\$ > '$PIDF'; exec $PHP_BIN -S '$HOST:$PORT' scripts/dev-router.php" \
    > "$ROOT/storage/dev/php-serve.log" 2>&1 < /dev/null &
  for _ in $(seq 1 30); do
    if $PHP_BIN -r '$c=@file_get_contents("http://127.0.0.1:'"$PORT"'/robots.txt");echo $c?1:0;' 2>/dev/null | grep -q 1; then
      echo "Server ready → http://$HOST:$PORT/  (pid $(cat "$PIDF"))"
      return 0
    fi
    sleep 0.5
  done
  echo "Server did not start; see storage/dev/php-serve.log"; return 1
}

ensure_db || exit 1
start_php
rc=$?
if [ "$rc" = 2 ]; then exit 0; fi
[ "$rc" = 0 ] || exit 1

echo
echo "  Admin:   http://$HOST:$PORT/admin"
echo "  API:     http://$HOST:$PORT/api/v1 (see API.md)"
echo "  Logs:    storage/dev/php-serve.log"
echo "  Stop:    kill \$(cat $PIDF)"
echo
echo "Open the URL above. If DB is empty, run the web installer first:"
echo "  http://$HOST:$PORT/installer/"