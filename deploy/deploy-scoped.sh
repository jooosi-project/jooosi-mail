#!/usr/bin/env bash

set -euo pipefail

deploy_directory=${1:-}
result_directory=${2:-}

if [[ ! "$deploy_directory" =~ ^[A-Za-z0-9_][A-Za-z0-9_.-]*$ || ! "$result_directory" =~ ^[A-Za-z0-9_][A-Za-z0-9_.-]*$ || "$deploy_directory" == "$result_directory" ]]; then
    echo "Usage: deploy-scoped.sh <deploy-directory> <result-directory>" >&2
    exit 1
fi

if [[ ! -d "$deploy_directory" ]]; then
    echo "Release source directory does not exist: $deploy_directory" >&2
    exit 1
fi

if [[ ! -f "$deploy_directory/deploy/scoper.inc.php" || ! -f "$deploy_directory/deploy/patch-scoper-autoload.php" ]]; then
    echo "Release source is missing its PHP-Scoper configuration or autoloader patch." >&2
    exit 1
fi

if [[ -d "$deploy_directory/tests" ]]; then
    echo "Refusing to scope a release source that contains test function stubs: $deploy_directory/tests" >&2
    exit 1
fi

rm -rf "$result_directory"
rm -rf "$deploy_directory/deploy/php-scoper-wordpress-excludes-master"

temporary_directory=$(mktemp -d "${TMPDIR:-/tmp}/jooosi-mail-scoper.XXXXXX")
action_scheduler_directory="$deploy_directory/vendor/woocommerce/action-scheduler"
action_scheduler_staged=false

cleanup()
{
    exit_code=$?
    trap - EXIT

    rm -rf "$deploy_directory/deploy/php-scoper-wordpress-excludes-master"

    if [[ "$action_scheduler_staged" == true ]]; then
        if [[ -d "$temporary_directory/action-scheduler" ]]; then
            mkdir -p "$deploy_directory/vendor/woocommerce"
            rm -rf "$action_scheduler_directory"
            mv "$temporary_directory/action-scheduler" "$action_scheduler_directory"
        elif [[ -d "$result_directory/vendor/woocommerce/action-scheduler" ]]; then
            mkdir -p "$deploy_directory/vendor/woocommerce"
            rm -rf "$action_scheduler_directory"
            mv "$result_directory/vendor/woocommerce/action-scheduler" "$action_scheduler_directory"
        fi
    fi

    rm -rf "$temporary_directory"
    exit "$exit_code"
}

trap cleanup EXIT

curl --fail --location --silent --show-error \
    https://github.com/snicco/php-scoper-wordpress-excludes/archive/refs/heads/master.zip \
    --output "$temporary_directory/php-scoper-wordpress-excludes-master.zip"
unzip -q "$temporary_directory/php-scoper-wordpress-excludes-master.zip" -d "$deploy_directory/deploy"

if [[ -d "$action_scheduler_directory" ]]; then
    mv "$action_scheduler_directory" "$temporary_directory/action-scheduler"
    action_scheduler_staged=true
fi

curl --fail --location --silent --show-error \
    https://github.com/humbug/php-scoper/releases/download/0.18.19/php-scoper.phar \
    --output "$temporary_directory/php-scoper.phar"

php -d memory_limit=-1 "$temporary_directory/php-scoper.phar" add-prefix \
    --output-dir "../$result_directory" \
    --config deploy/scoper.inc.php \
    --force \
    --ansi \
    --working-dir "$deploy_directory"

if [[ "$action_scheduler_staged" == true ]]; then
    mkdir -p "$result_directory/vendor/woocommerce"
    mv "$temporary_directory/action-scheduler" "$result_directory/vendor/woocommerce/action-scheduler"
fi

composer dump-autoload --working-dir "$result_directory" --ansi --no-dev --classmap-authoritative
php deploy/patch-scoper-autoload.php "$result_directory/vendor/scoper-autoload.php"

if grep -Fq 'JooosiMailDeps\dbDelta' "$result_directory/vendor/scoper-autoload.php"; then
    echo "The scoped autoloader contains a broken dbDelta() proxy." >&2
    exit 1
fi

action_scheduler_staged=false
rm -rf "$deploy_directory"
