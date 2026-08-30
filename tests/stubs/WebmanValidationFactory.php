<?php

declare(strict_types=1);

namespace Webman\Validation\Factory;

/**
 * Test stand-in for webman/validation's ValidationFactory.
 *
 * php-blade's own test suite does not depend on webman/validation, so this
 * stub is loaded manually to prove that View::ensureFacadeServices() binds
 * the "validator" service into Blade's container. When the real package is
 * present (as in a webman project), the class_exists() guard in the adapter
 * picks the real factory instead and this file is never loaded.
 */
class ValidationFactory
{
    private static ?object $factory = null;

    public static function getFactory(): object
    {
        return self::$factory ??= new \stdClass();
    }
}
