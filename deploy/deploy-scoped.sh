#!/usr/bin/env bash

# see https://stackoverflow.com/questions/66644233/how-to-propagate-colors-from-bash-script-to-github-action?noredirect=1#comment117811853_66644233
export TERM=xterm-color

set -e
set -u

note()
{
    MESSAGE=$1;

    printf "\n";
    echo "[NOTE] $MESSAGE";
    printf "\n";
}

DEPLOY_DIRECTORY=$1
RESULT_DIRECTORY=$2
ACTION_SCHEDULER_DIRECTORY="$DEPLOY_DIRECTORY/vendor/woocommerce/action-scheduler"
ACTION_SCHEDULER_STAGING_DIRECTORY=""

note "Starts"

note "Cleaning directories"
rm -rf "$RESULT_DIRECTORY"

note "Downloading whitelist of php-scoper"
wget https://github.com/snicco/php-scoper-wordpress-excludes/archive/refs/heads/master.zip -O "php-scoper-wordpress-excludes-master.zip"

note "Extracting whitelist of php-scoper"
unzip "php-scoper-wordpress-excludes-master.zip" -d "$DEPLOY_DIRECTORY/deploy"
rm -f "php-scoper-wordpress-excludes-master.zip"

if [ -d "$ACTION_SCHEDULER_DIRECTORY" ]; then
    note "Temporarily excluding Action Scheduler from scoping"
    ACTION_SCHEDULER_STAGING_DIRECTORY=$(mktemp -d "${TMPDIR:-/tmp}/jooosi-mail-action-scheduler.XXXXXX")
    mv "$ACTION_SCHEDULER_DIRECTORY" "$ACTION_SCHEDULER_STAGING_DIRECTORY/action-scheduler"
fi

note "Download php-scoper"
wget https://github.com/humbug/php-scoper/releases/download/0.18.19/php-scoper.phar -N --no-verbose

note "Running scoper to $RESULT_DIRECTORY"
php -d memory_limit=-1 php-scoper.phar add-prefix --output-dir "../$RESULT_DIRECTORY" --config "deploy/scoper.inc.php" --force --ansi --working-dir "$DEPLOY_DIRECTORY";
rm -f "$RESULT_DIRECTORY/php-scoper.phar"

if [ -n "$ACTION_SCHEDULER_STAGING_DIRECTORY" ]; then
    note "Restoring unscoped Action Scheduler"
    mkdir -p "$RESULT_DIRECTORY/vendor/woocommerce"
    rm -rf "$RESULT_DIRECTORY/vendor/woocommerce/action-scheduler"
    mv "$ACTION_SCHEDULER_STAGING_DIRECTORY/action-scheduler" "$RESULT_DIRECTORY/vendor/woocommerce/action-scheduler"
    rmdir "$ACTION_SCHEDULER_STAGING_DIRECTORY"
fi

note "Dumping Composer Autoload"
composer dump-autoload --working-dir "$RESULT_DIRECTORY" --ansi --no-dev --classmap-authoritative

note "Isolating the scoped Composer file registry"
php deploy/patch-scoper-autoload.php "$RESULT_DIRECTORY/vendor/scoper-autoload.php"

rm -rf "$DEPLOY_DIRECTORY"

note "Finished"
