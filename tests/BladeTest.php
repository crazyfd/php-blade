<?php

declare(strict_types=1);

namespace Jenssegers\Blade\Tests;

use Jenssegers\Blade\Blade;
use PHPUnit\Framework\TestCase;

class BladeTest extends TestCase
{
    private Blade $blade;
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

        foreach (glob($this->cachePath . '/*') ?: [] as $file) {
            unlink($file);
        }

        $this->blade = new Blade($this->viewPath, $this->cachePath);
    }

    protected function writeView(string $name, string $content): void
    {
        file_put_contents("{$this->viewPath}/{$name}.blade.php", $content);
    }

    public function test_renders_plain_html(): void
    {
        $this->writeView('plain', '<h1>Hello</h1>');

        $this->assertSame('<h1>Hello</h1>', $this->blade->render('plain'));
    }

    public function test_renders_variables(): void
    {
        $this->writeView('vars', '<p>{{ $name }}</p>');

        $this->assertSame('<p>John</p>', $this->blade->render('vars', ['name' => 'John']));
    }

    public function test_escapes_variables(): void
    {
        $this->writeView('escape', '{{ $evil }}');

        $this->assertSame('&lt;script&gt;', $this->blade->render('escape', ['evil' => '<script>']));
    }

    public function test_raw_output_does_not_escape(): void
    {
        $this->writeView('raw', '{!! $html !!}');

        $this->assertSame('<b>bold</b>', $this->blade->render('raw', ['html' => '<b>bold</b>']));
    }

    public function test_if_directive(): void
    {
        $this->writeView('cond', "@if(\$ok)
yes
@else
no
@endif");

        $this->assertSame('yes', trim($this->blade->render('cond', ['ok' => true])));
        $this->assertSame('no', trim($this->blade->render('cond', ['ok' => false])));
    }

    public function test_foreach_directive(): void
    {
        $this->writeView('loop', "@foreach(\$items as \$item)[{{ \$item }}]@endforeach");

        $result = $this->blade->render('loop', ['items' => ['a', 'b']]);

        $this->assertSame('[a][b]', $result);
    }

    public function test_layout_and_includes(): void
    {
        $this->writeView('partial', '- {{ $slot }} -');
        $this->writeView('with-include', 'start @include("partial", ["slot" => "mid"]) end');

        $this->assertSame('start - mid - end', $this->blade->render('with-include'));
    }

    public function test_custom_directive(): void
    {
        $this->blade->directive('shout', fn ($expression) => "<?php echo strtoupper($expression); ?>");
        $this->writeView('directive', "@shout('hello')");

        $this->assertSame('HELLO', $this->blade->render('directive'));
    }

    public function test_custom_if(): void
    {
        $this->blade->if('admin', fn ($value) => $value === 'admin');
        $this->writeView('customif', "@admin('admin')\nroot\n@else\nguest\n@endif");

        $this->assertSame('root', trim($this->blade->render('customif')));
    }

    public function test_shared_data(): void
    {
        $this->blade->share('shared', 'value');
        $this->writeView('shared', '{{ $shared }}');

        $this->assertSame('value', $this->blade->render('shared'));
    }

    public function test_exists(): void
    {
        $this->writeView('existing', 'ok');

        $this->assertTrue($this->blade->exists('existing'));
        $this->assertFalse($this->blade->exists('missing'));
    }

    public function test_make_returns_view_instance(): void
    {
        $this->writeView('plain', 'content');

        $view = $this->blade->make('plain');

        $this->assertSame('content', $view->render());
    }

    public function test_component(): void
    {
        $this->writeView('alert', '<div>{{ $slot }}</div>');
        $this->blade->component('alert');

        $this->writeView('page', '<x-alert>danger</x-alert>');

        $this->assertSame('<div>danger</div>', $this->blade->render('page'));
    }

    public function test_compiler_instance(): void
    {
        $this->assertInstanceOf(\Illuminate\View\Compilers\BladeCompiler::class, $this->blade->compiler());
    }

    public function test_compiled_files_are_cached(): void
    {
        $this->writeView('cached', '{{ $x }}');
        $this->blade->render('cached', ['x' => '1']);

        $this->assertNotEmpty(glob($this->cachePath . '/*.php'));
    }
}
