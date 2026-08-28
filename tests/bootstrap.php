<?php

declare(strict_types=1);

/*
 * Test bootstrap: define BASE_PATH before the autoloader pulls in the
 * webman-framework helpers, which otherwise auto-detect a BASE_PATH of
 * their own. The Webman integration tests run against this skeleton.
 */

if (! defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__ . '/tmp/webman');
}

require __DIR__ . '/../vendor/autoload.php';
