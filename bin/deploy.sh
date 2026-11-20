#!/usr/bin/env bash
# Deploy to pixelforge.heptalex.ro. Refuses to ship unless types, tests and the
# build all pass, and only from a clean, committed tree.
#
#   bin/deploy.sh            # check, build, upload, migrate, restart
#   DEPLOY_HOST=user@host bin/deploy.sh
set -euo pipefail

HOST="${DEPLOY_HOST:-ubuntu@heptalex.ro}"
DIR="${DEPLOY_DIR:-/var/www/html/pixelforge}"
URL="${DEPLOY_URL:-https://pixelforge.heptalex.ro}"
cd "$(dirname "$0")/.."

step() { printf '\n\033[1;33m▸ %s\033[0m\n' "$*"; }

step "Clean tree?"
if [[ -n "$(git status --porcelain)" ]]; then
    git status --short
    echo "Commit or stash first: the server must run exactly a commit." >&2
    exit 1
fi

step "Type check"
npx vue-tsc --noEmit

step "Tests"
php vendor/bin/phpunit --no-progress

step "Build (client + SSR)"
rm -rf public/build bootstrap/ssr
npm run build:ssr >/dev/null

step "Upload $(git rev-parse --short HEAD) to $HOST:$DIR"
rsync -az --delete --no-group --no-perms --omit-dir-times \
    --exclude='.git/' --exclude='node_modules/' --exclude='vendor/' \
    --exclude='.env' --exclude='.env.*' --include='.env.example' \
    --exclude='database/database.sqlite*' --exclude='storage/' \
    --exclude='bootstrap/cache/*.php' --exclude='public/storage' --exclude='public/hot' \
    --exclude='.phpunit.cache/' \
    ./ "$HOST:$DIR/"

step "Install, migrate, cache, restart"
ssh -o BatchMode=yes "$HOST" "set -e; cd '$DIR'
    COMPOSER_NO_INTERACTION=1 composer install --no-dev --optimize-autoloader --no-progress -q
    npm ci --omit=dev --ignore-scripts --no-audit --no-fund --loglevel=error
    sudo -u www-data php8.4 artisan migrate --force
    sudo -u www-data php8.4 artisan optimize
    sudo systemctl restart pixelforge-ssr pixelforge-queue
    sleep 2
    systemctl is-active pixelforge-ssr pixelforge-queue"

step "Smoke test"
for path in / /login /up; do
    code=$(curl -s -o /dev/null -w '%{http_code}' "$URL$path")
    echo "$path $code"
    [[ "$code" == 200 ]] || { echo "Smoke test failed on $path" >&2; exit 1; }
done
curl -s "$URL/" | grep -q 'data-server-rendered' && echo "SSR on" || echo "warning: SSR off (client rendering fallback)"
code=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -d '{}' "$URL/webhooks/pixelforge")
echo "unsigned webhook -> $code (expect 400)"

step "Deployed $(git rev-parse --short HEAD)"
