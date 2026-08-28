<?php

declare(strict_types=1);

use Jenssegers\Blade\Blade;

/*
 * Laravel style global helpers for standalone (non-webman) usage.
 *
 * When running inside webman, the framework's own view() helper (which
 * returns a Response) is loaded first and takes precedence automatically,
 * so these definitions are skipped. blade() is always safe to define.
 */

if (! function_exists('blade')) {
    /**
     * Get the shared Blade instance.
     *
     * @param  string|null  $viewPath  Defaults to `<cwd>/views`
     * @param  string|null  $cachePath Defaults to the system temp directory
     * @return \Jenssegers\Blade\Blade
     */
    function blade(?string $viewPath = null, ?string $cachePath = null): Blade
    {
        static $instance = null;

        if ($instance === null) {
            $viewPath ??= (string) getcwd().'/views';
            $cachePath ??= sys_get_temp_dir().'/blade-cache-'.md5($viewPath);
            $instance = new Blade($viewPath, $cachePath);
        }

        return $instance;
    }
}

if (! function_exists('view')) {
    /**
     * Get the evaluated view contents for the given template (Laravel style).
     *
     * @param  string  $template
     * @param  array  $data
     * @return \Illuminate\Contracts\View\View
     */
    function view(string $template, array $data = []): \Illuminate\Contracts\View\View
    {
        return blade()->make($template, $data);
    }
}
