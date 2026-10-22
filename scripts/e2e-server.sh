#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

export APP_URL="http://127.0.0.1:8001"
export APP_DEMO="true"
export DB_DATABASE="${E2E_DB_DATABASE:-trustgate_e2e}"
export QUEUE_CONNECTION="database"
export CACHE_STORE="file"
export SESSION_DRIVER="database"
export GRANT_KEY_STORE="file"
export DEMO_SLOW_RUN_SLEEP_SECONDS="2"
export LOG_LEVEL="warning"

DB_CREATE_LOG="/tmp/trustgate-e2e-db-create.log"
MIGRATE_LOG="/tmp/trustgate-e2e-migrate.log"
WORKER_LOG="/tmp/trustgate-e2e-worker.log"
SERVER_LOG="/tmp/trustgate-e2e-server.log"

php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c = config("database.connections.mysql");
$pdo = new PDO(
    "mysql:host={$c["host"]};port={$c["port"]};charset=utf8mb4",
    $c["username"],
    $c["password"],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$db = str_replace("`", "``", $c["database"]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "database {$c["database"]} ready\n";
' >"$DB_CREATE_LOG" 2>&1 || {
    cat "$DB_CREATE_LOG"
    exit 1
}

if ! php artisan migrate:fresh --seed --force >"$MIGRATE_LOG" 2>&1; then
    cat "$MIGRATE_LOG"
    exit 1
fi

php artisan optimize:clear >/dev/null 2>&1 || true
php artisan storage:link >/dev/null 2>&1 || true

php artisan queue:work --sleep=1 --tries=1 --timeout=120 >"$WORKER_LOG" 2>&1 &
worker_pid=$!

php artisan serve --host=127.0.0.1 --port=8001 >"$SERVER_LOG" 2>&1 &
server_pid=$!

cleanup() {
    kill "$worker_pid" "$server_pid" 2>/dev/null || true
    wait "$worker_pid" "$server_pid" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

wait "$server_pid"
