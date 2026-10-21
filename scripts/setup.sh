#!/usr/bin/env bash

set -euo pipefail

PHP_VERSION="${PHP_VERSION:-8.3}"
NODE_MAJOR="${NODE_MAJOR:-20}"
APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
APP_USER="${APP_USER:-www-data}"
APP_URL="${APP_URL:-http://localhost}"
SERVER_NAME="${SERVER_NAME:-localhost}"
DB_DATABASE="${DB_DATABASE:-sheets_sync}"
DB_USERNAME="${DB_USERNAME:-sheets_sync}"
DB_PASSWORD="${DB_PASSWORD:-}"
SITE_NAME="${SITE_NAME:-sheets-sync}"
CACHE_DIR="${CACHE_DIR:-/var/cache/sheets-sync}"
INSTALL_NGINX="${INSTALL_NGINX:-yes}"
SCHEDULE_TIME="${SCHEDULE_TIME:-* * * * *}"

log() {
    printf '\n\033[1;34m==>\033[0m %s\n' "$1"
}

fail() {
    printf '\n\033[1;31mError:\033[0m %s\n' "$1" >&2
    exit 1
}

run_as_app() {
    sudo -u "${APP_USER}" env \
        HOME="${CACHE_DIR}" \
        COMPOSER_HOME="${CACHE_DIR}/composer" \
        "$@"
}

require_root() {
    if [[ "${EUID}" -ne 0 ]]; then
        fail "Run this script with sudo: sudo bash scripts/setup.sh"
    fi
}

require_ubuntu() {
    if ! grep -qi ubuntu /etc/os-release 2>/dev/null; then
        fail "This script targets Ubuntu. Detected a different distribution."
    fi
}

install_system_packages() {
    log "Installing system packages"

    export DEBIAN_FRONTEND=noninteractive

    apt-get update -qq
    apt-get install -y -qq --no-install-recommends \
        software-properties-common ca-certificates curl gnupg git unzip

    if ! grep -rq "ondrej/php" /etc/apt/sources.list.d 2>/dev/null; then
        add-apt-repository -y ppa:ondrej/php
        apt-get update -qq
    fi

    apt-get install -y -qq --no-install-recommends \
        "php${PHP_VERSION}-cli" \
        "php${PHP_VERSION}-fpm" \
        "php${PHP_VERSION}-mysql" \
        "php${PHP_VERSION}-mbstring" \
        "php${PHP_VERSION}-xml" \
        "php${PHP_VERSION}-curl" \
        "php${PHP_VERSION}-zip" \
        "php${PHP_VERSION}-bcmath" \
        "php${PHP_VERSION}-intl" \
        mysql-server

    systemctl enable --now mysql
    systemctl enable --now "php${PHP_VERSION}-fpm"
}

install_composer() {
    if command -v composer >/dev/null 2>&1; then
        log "Composer is already installed"
        return
    fi

    log "Installing Composer"

    local expected actual
    expected="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    actual="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"

    if [[ "${expected}" != "${actual}" ]]; then
        rm -f /tmp/composer-setup.php
        fail "Composer installer checksum mismatch."
    fi

    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
}

install_node() {
    if command -v node >/dev/null 2>&1 && [[ "$(node -v | cut -c2- | cut -d. -f1)" -ge "${NODE_MAJOR}" ]]; then
        log "Node $(node -v) is already installed"
        return
    fi

    log "Installing Node ${NODE_MAJOR}"

    curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash -
    apt-get install -y -qq nodejs
}

configure_database() {
    log "Configuring MySQL"

    if [[ -z "${DB_PASSWORD}" ]]; then
        DB_PASSWORD="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)"
        GENERATED_DB_PASSWORD=yes
    fi

    mysql --protocol=socket <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'localhost';
FLUSH PRIVILEGES;
SQL
}

write_env_value() {
    local key="$1" value="$2" file="${APP_DIR}/.env"

    if grep -q "^${key}=" "${file}"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "${file}"
    else
        printf '%s=%s\n' "${key}" "${value}" >>"${file}"
    fi
}

prepare_application() {
    log "Installing application dependencies"

    cd "${APP_DIR}"

    mkdir -p "${CACHE_DIR}"
    chown -R "${APP_USER}:${APP_USER}" "${CACHE_DIR}" "${APP_DIR}"

    run_as_app composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

    if [[ ! -f .env ]]; then
        cp .env.example .env
    fi

    write_env_value APP_ENV production
    write_env_value APP_DEBUG false
    write_env_value APP_URL "${APP_URL}"
    write_env_value DB_CONNECTION mysql
    write_env_value DB_HOST 127.0.0.1
    write_env_value DB_PORT 3306
    write_env_value DB_DATABASE "${DB_DATABASE}"
    write_env_value DB_USERNAME "${DB_USERNAME}"
    write_env_value DB_PASSWORD "${DB_PASSWORD}"

    chown "${APP_USER}:${APP_USER}" .env
    chmod 640 .env

    if ! grep -q '^APP_KEY=base64:' .env; then
        run_as_app php artisan key:generate --force
    fi

    mkdir -p storage/app/google

    log "Running migrations"
    run_as_app php artisan migrate --force --seed

    log "Building frontend assets"
    run_as_app npm ci --no-audit --no-fund
    run_as_app npm run build
    rm -f public/hot

    run_as_app php artisan config:cache
    run_as_app php artisan route:cache
    run_as_app php artisan view:cache

    chown -R "${APP_USER}:${APP_USER}" storage bootstrap/cache
    chmod -R 775 storage bootstrap/cache
    chmod 750 storage/app/google
}

configure_nginx() {
    if [[ "${INSTALL_NGINX}" != "yes" ]]; then
        log "Skipping nginx setup"
        return
    fi

    log "Configuring nginx"

    apt-get install -y -qq --no-install-recommends nginx

    cat >"/etc/nginx/sites-available/${SITE_NAME}" <<NGINX
server {
    listen 80;
    server_name ${SERVER_NAME};
    root ${APP_DIR}/public;

    index index.php;
    charset utf-8;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

    ln -sfn "/etc/nginx/sites-available/${SITE_NAME}" "/etc/nginx/sites-enabled/${SITE_NAME}"
    rm -f /etc/nginx/sites-enabled/default

    nginx -t
    systemctl enable --now nginx
    systemctl reload nginx
}

configure_queue_worker() {
    log "Installing the queue worker service"

    cat >"/etc/systemd/system/${SITE_NAME}-worker.service" <<UNIT
[Unit]
Description=Sheets sync queue worker
After=network.target mysql.service

[Service]
User=${APP_USER}
Group=${APP_USER}
Restart=always
RestartSec=5
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
UNIT

    systemctl daemon-reload
    systemctl enable --now "${SITE_NAME}-worker.service"
    systemctl restart "${SITE_NAME}-worker.service"
}

configure_scheduler() {
    log "Installing the scheduler cron entry"

    cat >"/etc/cron.d/${SITE_NAME}" <<CRON
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

${SCHEDULE_TIME} ${APP_USER} cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
CRON

    chmod 0644 "/etc/cron.d/${SITE_NAME}"
}

print_summary() {
    log "Done"

    cat <<SUMMARY

Application   ${APP_DIR}
URL           ${APP_URL}
Database      ${DB_DATABASE} (user ${DB_USERNAME})
Queue worker  systemctl status ${SITE_NAME}-worker
Scheduler     /etc/cron.d/${SITE_NAME}

Sign in with admin@example.com / password and change it right away.

One manual step is left before the import can run:

  1. Create a service account in Google Cloud and download its JSON key.
  2. Copy the key to ${APP_DIR}/storage/app/google/credentials.json
  3. chown ${APP_USER}:${APP_USER} ${APP_DIR}/storage/app/google/credentials.json
  4. chmod 640 ${APP_DIR}/storage/app/google/credentials.json
  5. Share every spreadsheet with the service account email as a Viewer.

See README.md for the detailed Google API walkthrough.
SUMMARY

    if [[ "${GENERATED_DB_PASSWORD:-no}" == "yes" ]]; then
        cat <<PASSWORD

The database password was generated and written to ${APP_DIR}/.env:

  ${DB_PASSWORD}

PASSWORD
    fi
}

main() {
    require_root
    require_ubuntu
    install_system_packages
    install_composer
    install_node
    configure_database
    prepare_application
    configure_nginx
    configure_queue_worker
    configure_scheduler
    print_summary
}

main "$@"
