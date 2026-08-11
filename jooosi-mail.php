<?php

/**
 * @wordpress-plugin
 * Plugin Name:         Jooosi Mail
 * Plugin URI:          https://github.com/jooosi-project/jooosi-mail
 * Description:         A modern, robust email sending solution for WordPress sites with advanced features including multiple provider support, queue-based processing, and comprehensive monitoring.
 * Text Domain:         jooosi-mail
 * Version:             1.0.8
 * Requires at least:   7.0
 * Requires PHP:        8.1
 * Author:              Jooosi Mail
 * Author URI:          https://jooo.si
 * License:             GPL-3.0-or-later
 *
 * @package             JooosiMail
 * @author              Joshua Gugun Siagian <suabahasa@gmail.com>
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    if (file_exists(__DIR__ . '/vendor/scoper-autoload.php')) {
        require_once __DIR__ . '/vendor/scoper-autoload.php';
    } else {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    if (file_exists(__DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php')) {
        require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
    }

    require_once __DIR__ . '/constant.php';

    JooosiMail\Bootstrap\Plugin::boot(__FILE__);
}
