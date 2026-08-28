<?php

declare(strict_types=1);

namespace Jenssegers\Blade\Tests;

use Illuminate\Contracts\View\View as ViewContract;
use Jenssegers\Blade\Blade;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    private string $viewPath;
    private string $cachePath;

    protected function setUp(): void
    {
        $this->viewPath = __DIR__ . '/views';
        $this->cachePath = __DIR__ . '/tmp/cache';

        if (! is_dir($this->viewPath)) {
            mkdir($this->viewPath, 0777, true);
        }
        if (! is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0777, true);
        }
    }

    public function test_blade_helper_returns_shared_instance(): void
    {
        blade($this->viewPath, $this->cachePath);

        $this->assertInstanceOf(Blade::class, blade());
        $this->assertSame(blade(), blade());
    }

    /**
     * Whether the package's standalone view() helper is active. When the
     * webman-framework is loaded (dev/integration environment) its own
     * view() helper takes precedence by design.
     */
    private function packageViewHelperIsActive(): bool
    {
        $file = (new \ReflectionFunction('view'))->getFileName();

        return $file !== false && ! str_contains($file, 'webman-framework');
    }

    public function test_view_helper_returns_renderable_view(): void
    {
        if (! $this->packageViewHelperIsActive()) {
            $this->markTestSkipped("webman's view() helper takes precedence in this environment");
        }

        file_put_contents("{$this->viewPath}/helper.blade.php", '<p>{{ $name }}</p>');

        blade($this->viewPath, $this->cachePath);
        $view = view('helper', ['name' => 'John']);

        $this->assertInstanceOf(ViewContract::class, $view);
        $this->assertSame('<p>John</p>', $view->render());
    }

    public function test_view_helper_echoes_as_string(): void
    {
        if (! $this->packageViewHelperIsActive()) {
            $this->markTestSkipped("webman's view() helper takes precedence in this environment");
        }

        file_put_contents("{$this->viewPath}/echo.blade.php", 'Hello {{ $who }}');

        blade($this->viewPath, $this->cachePath);

        $this->assertSame('Hello webman', (string) view('echo', ['who' => 'webman']));
    }
}
