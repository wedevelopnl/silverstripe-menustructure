#!/bin/sh
set -e

composer install --no-interaction

# FrankenPHP ships a default phpinfo() index.php. Replace it with the proper
# SilverStripe bootstrap after composer install makes the recipe available.
cp -f vendor/silverstripe/recipe-core/public/index.php /app/public/index.php

# Ensure all vendor package resources are exposed. composer install skips the
# vendor-expose step when the named Docker volume already has packages from a
# previous run (nothing new to install → no post-install event fires).
composer vendor-expose

# vendor-plugin uses realpath() to resolve library paths, which follows the
# symlink from vendor/wedevelopnl/silverstripe-menustructure → /module.
# Because /module is outside /app, getRelativePath() produces a broken path
# and the exposed resources are never created. Create them manually.
_res=/app/public/_resources/vendor/wedevelopnl/silverstripe-menustructure
mkdir -p "$_res"

vendor/bin/sake dev/build flush=1

touch /tmp/.app-ready

exec frankenphp run --config /etc/caddy/Caddyfile
