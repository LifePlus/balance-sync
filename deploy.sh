#!/bin/bash
set -e

SERVER="lp-apps03-new"
APP_PATH="/home/autocomm/balance-sync"

echo "Deploying to $SERVER..."

ssh $SERVER << 'DEPLOY'
set -e

sudo -u autocomm bash << 'AUTOCOMM'
set -e

cd /home/autocomm/balance-sync

echo "Pulling latest changes..."
git pull origin main

echo "Installing dependencies..."
composer install --no-dev --optimize-autoloader

echo "Clearing caches..."
php application optimize:clear 2>/dev/null || true

echo "Deployment complete!"
echo "Run manually with: php application make:csv"
AUTOCOMM
DEPLOY

echo "Deployment finished successfully"
