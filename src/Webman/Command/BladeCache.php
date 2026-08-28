<?php

namespace Jenssegers\Blade\Webman\Command;

use Illuminate\Filesystem\Filesystem;
use Jenssegers\Blade\Blade;
use Jenssegers\Blade\Webman\View;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function base_path;
use function glob;
use function is_dir;

#[AsCommand('blade:cache', 'Compile all of the application\'s Blade templates')]
class BladeCache extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = View::bladeConfig();
        $compiled = 0;

        foreach ($this->viewPaths() as $viewPath) {
            $blade = new Blade($viewPath, $config['cache_path'], null, $config['options']);

            // Apply the same custom directives/extension callbacks the runtime
            // view handler applies, so pre-compiled output matches runtime.
            $extension = $this->extensionFor($viewPath);
            if ($extension) {
                $extension($blade);
            }

            foreach (glob($viewPath . '/**/*.blade.php') ?: [] as $file) {
                $blade->compiler()->compile($file);
                $compiled++;
            }
            foreach (glob($viewPath . '/*.blade.php') ?: [] as $file) {
                $blade->compiler()->compile($file);
                $compiled++;
            }
        }

        $output->writeln(sprintf('<info>Compiled %d blade template(s) to %s.</info>', $compiled, $config['cache_path']));

        return 0;
    }

    /**
     * Resolve the view.extension callback configured for the given view path,
     * mirroring Jenssegers\Blade\Webman\View (main app vs plugin config).
     *
     * @return callable|null
     */
    protected function extensionFor(string $viewPath): ?callable
    {
        $base = base_path();
        if (preg_match('#^' . preg_quote($base, '#') . '/plugin/([^/]+)/app#', $viewPath, $m)) {
            $extension = config("plugin.{$m[1]}.view.extension");
        } else {
            $extension = config('view.extension');
        }

        return is_callable($extension) ? $extension : null;
    }

    /**
     * Collect all view directories of the application.
     *
     * @return string[]
     */
    protected function viewPaths(): array
    {
        $paths = [];

        foreach (glob(base_path() . '/app/*/view') ?: [] as $dir) {
            $paths[] = $dir;
        }
        foreach (glob(base_path() . '/plugin/*/app/view') ?: [] as $dir) {
            $paths[] = $dir;
        }
        foreach (glob(base_path() . '/plugin/*/app/*/view') ?: [] as $dir) {
            $paths[] = $dir;
        }
        if (is_dir(base_path() . '/app/view')) {
            $paths[] = base_path() . '/app/view';
        }

        return array_unique($paths);
    }
}
