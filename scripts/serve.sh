#!/usr/bin/env bash
# Serves both sites with PHP's built-in server:
#   WordPress  http://127.0.0.1:8080
#   Drupal     http://127.0.0.1:8081
set -euo pipefail
source "$(dirname "$0")/env.sh"
"$(dirname "$0")/start-db.sh"
pkill -f "php -S 127.0.0.1:$WP_PORT" 2>/dev/null || true
pkill -f "php -S 127.0.0.1:$DRUPAL_PORT" 2>/dev/null || true
setsid php -S 127.0.0.1:$WP_PORT -t "$WP_DIR" > "$ROOT/wordpress.log" 2>&1 < /dev/null &
(cd "$DRUPAL_DIR/web" && setsid php -S 127.0.0.1:$DRUPAL_PORT .ht.router.php > "$ROOT/drupal.log" 2>&1 < /dev/null &)
sleep 1
echo "WordPress: http://127.0.0.1:$WP_PORT/wp-admin  (admin/admin)"
echo "Drupal:    http://127.0.0.1:$DRUPAL_PORT/user/login  (admin/admin)"
