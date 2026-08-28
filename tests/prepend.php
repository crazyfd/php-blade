<?php

declare(strict_types=1);

/*
 * Prepended before PHPUnit boots (see the composer "test" script), so that
 * BASE_PATH is defined before the webman-framework helpers are autoloaded
 * and auto-detect a BASE_PATH of their own.
 */

if (! defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__ . '/tmp/webman');
}

/*
 * webman-framework 1.x does not register its support helpers through
 * composer's "files" autoload (2.x does), and the package's own helpers.php
 * is loaded with composer before any manual require could run. Load the
 * framework helpers here, before the autoloader, so the framework's view()
 * takes precedence exactly like in a real webman application.
 */

$webmanHelpers = __DIR__ . '/../vendor/workerman/webman-framework/src/support/helpers.php';

if (is_file($webmanHelpers) && ! function_exists('config')) {
    require $webmanHelpers;
}
