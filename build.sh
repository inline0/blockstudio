#!/bin/bash

# Exit immediately if a command exits with a non-zero status.
set -e

# Ensure script is run from the directory where it resides
cd "$(dirname "$0")"

composer require scssphp/scssphp
composer require matthiasmullie/minify
composer require humbug/php-scoper

# 1. Install all dependencies (including dev)
echo "Installing all Composer dependencies..."
composer install

# 2. Run PHP Scoper to prefix dependencies
echo "Scoping dependencies..."
composer run-script scope

# 3. Dump optimized autoloader for production (excluding dev dependencies)
echo "Generating optimized production autoloader..."
composer dump-autoload -o --no-dev

echo "Build process completed successfully!"

composer remove scssphp/scssphp
composer remove matthiasmullie/minify
composer remove humbug/php-scoper
