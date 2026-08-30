<?php

declare(strict_types=1);

namespace Jenssegers\Blade\Tests;

use Jenssegers\Blade\Webman\Command\BladeCache;
use Jenssegers\Blade\Webman\Command\BladeClear;
use Jenssegers\Blade\Webman\View;
use Jenssegers\Blade\Blade as BladeView;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Webman\Config;
use Webman\Context;
use Webman\Http\Request;

/**
 * Exercises the Webman integration layer without booting a server: the
 * framework helpers (config/base_path/request) are backed by a temporary
 * webman project skeleton.
 */
class WebmanIntegrationTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = __DIR__ . '/tmp/webman';

        foreach ([
            '/app/view',
            '/config/plugin/crazyfd/blade',
            '/runtime/viewcache',
        ] as $dir) {
            $path = $this->basePath . $dir;
            if (! is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }

        // compiled cache starts empty
        foreach (glob($this->basePath . '/runtime/viewcache/*') ?: [] as $file) {
            unlink($file);
        }

        file_put_contents($this->basePath . '/app/view/hello.blade.php', '<h1>Hello {{ $name }}</h1>');
        file_put_contents($this->basePath . '/app/view/shout.blade.php', '@shout($word)');

        file_put_contents($this->basePath . '/config/app.php', "<?php\n\nreturn ['enable' => true];\n");

        file_put_contents($this->basePath . '/config/plugin/crazyfd/blade/app.php', <<<PHP
<?php

return [
    'enable' => true,
    'cache_path' => '{$this->basePath}/runtime/viewcache',
    'cache' => true,
    'compiled_extension' => 'php',
    'check_timestamps' => true,
];
PHP);

        // view.extension registers a custom directive, proving that blade:cache
        // applies the same callbacks the runtime view handler uses.
        $viewConfig = <<<'PHP'
<?php

use Jenssegers\Blade\Blade;

return [
    'handler' => \Jenssegers\Blade\Webman\View::class,
    'extension' => function (Blade $blade): void {
        $blade->directive('shout', fn ($expression) => "<?= strtoupper((string) ($expression)) ?>");
    },
];
PHP;
        file_put_contents($this->basePath . '/config/view.php', $viewConfig);

        Config::load($this->basePath . '/config');

        Context::set(Request::class, new Request("GET /hello HTTP/1.1\r\nHost: localhost\r\n\r\n"));
    }

    protected function runCommand(string $name): string
    {
        $app = new Application('Test', 'dev');
        $app->addCommands([new BladeCache(), new BladeClear()]);
        $app->setAutoExit(false);

        $output = new BufferedOutput();
        $exit = $app->run(new ArrayInput(['command' => $name]), $output);

        $content = $output->fetch();
        if ($exit !== 0) {
            $content .= PHP_EOL . sprintf('[exit code: %d]', $exit);
        }

        return $content;
    }

    public function test_blade_config_reads_plugin_configuration(): void
    {
        $config = View::bladeConfig();

        $this->assertSame($this->basePath . '/runtime/viewcache', $config['cache_path']);
        $this->assertTrue($config['options']['view.cache']);
        $this->assertSame('php', $config['options']['view.compiled_extension']);
        $this->assertTrue($config['options']['view.check_cache_timestamps']);
    }

    public function test_webman_view_handler_renders_template(): void
    {
        $html = View::render('hello', ['name' => 'webman']);

        $this->assertSame('<h1>Hello webman</h1>', $html);
    }

    public function test_webman_view_handler_applies_extension_callbacks(): void
    {
        // The runtime handler must register custom directives from view.extension
        $html = View::render('shout', ['word' => 'hi']);

        $this->assertSame('HI', trim($html));
    }

    public function test_assign_merges_vars_into_render(): void
    {
        Context::set(Request::class, new Request("GET /hello HTTP/1.1\r\nHost: localhost\r\n\r\n"));

        View::assign('name', 'assigned');
        $html = View::render('hello', []);

        $this->assertSame('<h1>Hello assigned</h1>', $html);
    }

    public function test_blade_cache_compiles_templates_with_extension_callbacks(): void
    {
        $output = $this->runCommand('blade:cache');

        $this->assertStringContainsString('Compiled 2 blade template(s)', $output);
        $this->assertNotEmpty(glob($this->basePath . '/runtime/viewcache/*.php'));

        // The compiled @shout template must contain the expanded directive,
        // proving the extension callback ran during pre-compilation.
        $compiled = '';
        foreach (glob($this->basePath . '/runtime/viewcache/*.php') ?: [] as $file) {
            $compiled .= (string) file_get_contents($file);
        }
        $this->assertStringContainsString('strtoupper', $compiled);
    }

    public function test_blade_clear_removes_compiled_files(): void
    {
        $this->runCommand('blade:cache');
        $this->assertNotEmpty(glob($this->basePath . '/runtime/viewcache/*'));

        $output = $this->runCommand('blade:clear');

        $this->assertStringContainsString('Cleared 2 compiled view file(s)', $output);
        $this->assertEmpty(glob($this->basePath . '/runtime/viewcache/*'));
    }

    public function test_blade_clear_without_cache_directory_is_safe(): void
    {
        $cacheDir = $this->basePath . '/runtime/viewcache';
        rename($cacheDir, $cacheDir . '-backup');

        try {
            $output = $this->runCommand('blade:clear');

            $this->assertStringContainsString('Cleared 0 compiled view file(s)', $output);
        } finally {
            rename($cacheDir . '-backup', $cacheDir);
        }
    }

    public function test_webman_view_helper_takes_precedence_over_package_helper(): void
    {
        // With webman-framework loaded, its own view() returns a Response and
        // must not be shadowed by the package's standalone helper.
        $ref = new \ReflectionFunction('view');
        $this->assertStringContainsString(
            'webman-framework',
            $ref->getFileName(),
            'webman view() helper should take precedence'
        );
    }

    public function test_blade_hijacks_facade_root_and_validator_gets_rebound(): void
    {
        require_once __DIR__ . '/stubs/WebmanValidationFactory.php';

        $previousApp = Facade::getFacadeApplication();
        Facade::clearResolvedInstances();

        try {
            $blade = new BladeView(
                $this->basePath . '/app/view',
                $this->basePath . '/runtime/viewcache'
            );

            // 实例化即抢注 Facade 根
            $this->assertSame($blade->getContainer(), Facade::getFacadeApplication());

            // 抢注后 validator Facade 解析失败（Blade 容器未绑定该服务）
            $failed = false;
            try {
                Validator::make([], []);
            } catch (\Throwable $e) {
                $failed = true;
                $this->assertStringContainsString('Target class [validator] does not exist', $e->getMessage());
            }
            $this->assertTrue($failed, 'Validator facade should fail on the bare blade container');

            // 补绑后：容器与 Facade 根均能解析出同一个 validator 实例
            View::ensureFacadeServices($blade);

            $this->assertTrue($blade->getContainer()->bound('validator'));
            $this->assertSame(
                \Webman\Validation\Factory\ValidationFactory::getFactory(),
                $blade->getContainer()->make('validator')
            );
            $this->assertSame(
                \Webman\Validation\Factory\ValidationFactory::getFactory(),
                Validator::getFacadeRoot()
            );

            // 重复补绑是幂等的，不会覆盖已有绑定
            $blade->getContainer()->instance('validator', new \stdClass());
            View::ensureFacadeServices($blade);
            $this->assertInstanceOf(\stdClass::class, $blade->getContainer()->make('validator'));
        } finally {
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($previousApp);
        }
    }
}
