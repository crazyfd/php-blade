<?php

namespace Jenssegers\Blade\Webman;

use Jenssegers\Blade\Blade as BladeView;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

use function array_merge;
use function base_path;
use function config;
use function is_array;
use function request;
use function runtime_path;

/**
 * Webman view handler backed by php-blade.
 *
 * Reads its configuration from config/plugin/crazyfd/blade/app.php, so the
 * compiled-view cache path and compiler options are configurable, e.g.:
 *
 * return [
 *     'cache_path'         => runtime_path() . '/views',
 *     'cache'              => true,
 *     'compiled_extension' => 'php',
 *     'check_timestamps'   => true,
 * ];
 */
class View implements \Webman\View
{
    /**
     * Assign.
     * @param string|array $name
     * @param mixed $value
     */
    public static function assign(string|array $name, mixed $value = null): void
    {
        $request = request();
        $request->_view_vars = array_merge((array) $request->_view_vars, is_array($name) ? $name : [$name => $value]);
    }

    /**
     * Render.
     * @param string $template
     * @param array $vars
     * @param string|null $app
     * @param string|null $plugin
     * @return string
     */
    public static function render(string $template, array $vars, ?string $app = null, ?string $plugin = null): string
    {
        static $views = [];
        $request = request();
        $plugin = $plugin === null ? ($request->plugin ?? '') : $plugin;
        $app = $app === null ? ($request->app ?? '') : $app;
        $configPrefix = $plugin ? "plugin.$plugin." : '';
        $baseViewPath = $plugin ? base_path() . "/plugin/$plugin/app" : base_path() . '/app';
        if ($template[0] === '/') {
            if (strpos($template, '/view/') !== false) {
                [$viewPath, $template] = explode('/view/', $template, 2);
                $viewPath = base_path("$viewPath/view");
            } else {
                $viewPath = base_path();
                $template = ltrim($template, '/');
            }
        } else {
            $viewPath = $app === '' ? "$baseViewPath/view" : "$baseViewPath/$app/view";
        }
        if (! isset($views[$viewPath])) {
            $bladeConfig = self::bladeConfig();
            $views[$viewPath] = new BladeView(
                $viewPath,
                $bladeConfig['cache_path'],
                null,
                $bladeConfig['options']
            );
            self::ensureFacadeServices($views[$viewPath]);
            $extension = config("{$configPrefix}view.extension");
            if ($extension) {
                $extension($views[$viewPath]);
            }
        }
        if (isset($request->_view_vars)) {
            $vars = array_merge((array) $request->_view_vars, $vars);
        }

        return $views[$viewPath]->render($template, $vars);
    }

    /**
     * Re-bind framework facade services into Blade's private container.
     *
     * Blade 抢注 Facade 根：每次实例化都会执行 Facade::setFacadeApplication($this->container)，
     * 该容器未绑定 validator，会导致 ValidationException::withMessages()、Validator::make()
     * 等 Facade 调用抛 Target class [validator] does not exist。这里在每个 Blade 实例
     * 创建后向其容器补绑 validator（存在 webman/validation 时）。
     *
     * @param BladeView $blade
     * @return void
     */
    public static function ensureFacadeServices(BladeView $blade): void
    {
        $container = $blade->getContainer();

        if (! $container instanceof Container) {
            return;
        }

        if (! $container->bound('validator')
            && class_exists(\Webman\Validation\Factory\ValidationFactory::class)) {
            $container->instance('validator', \Webman\Validation\Factory\ValidationFactory::getFactory());
        }
    }

    /**
     * Resolve the blade configuration for this plugin.
     *
     * @return array{cache_path: string, options: array<string, mixed>}
     */
    public static function bladeConfig(): array
    {
        $config = config('plugin.crazyfd.blade.app', []);

        return [
            'cache_path' => (string) ($config['cache_path'] ?? runtime_path() . '/views'),
            'options' => [
                'view.cache' => (bool) ($config['cache'] ?? true),
                'view.compiled_extension' => (string) ($config['compiled_extension'] ?? 'php'),
                'view.check_cache_timestamps' => (bool) ($config['check_timestamps'] ?? true),
            ],
        ];
    }
}
