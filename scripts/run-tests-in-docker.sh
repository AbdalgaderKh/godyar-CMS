#!/usr/bin/env bash
set -euo pipefail

# Run project's tests inside an official PHP Docker image (useful if local PHP is incompatible)
# Usage: ./scripts/run-tests-in-docker.sh

docker run --rm -v "$PWD":/app -w /app -e COMPOSER_ALLOW_SUPERUSER=1 php:8.1 bash -lc '
  apt-get update -y && apt-get install -y git unzip default-mysql-client && \
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
  composer install --no-interaction --prefer-dist --optimize-autoloader && \
  php -v && \
  composer run lint || true && \
  composer run psalm || true && \
  vendor/bin/phpunit --configuration phpunit.xml.dist || true
'
