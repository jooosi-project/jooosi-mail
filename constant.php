<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

define('JOOOSI_MAIL_VERSION', '1.0.8');
define('JOOOSI_MAIL_PLUGIN_FILE', __DIR__ . '/jooosi-mail.php');
define('JOOOSI_MAIL_PLUGIN_BASENAME', plugin_basename(JOOOSI_MAIL_PLUGIN_FILE));
define('JOOOSI_MAIL_PLUGIN_DIR', __DIR__);
define('JOOOSI_MAIL_PLUGIN_URL', plugin_dir_url(JOOOSI_MAIL_PLUGIN_FILE));
define('JOOOSI_MAIL_CACHE_DIR', JOOOSI_MAIL_PLUGIN_DIR . '/var/cache');
