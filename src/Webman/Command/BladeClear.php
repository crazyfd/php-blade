<?php

namespace Jenssegers\Blade\Webman\Command;

use Illuminate\Filesystem\Filesystem;
use Jenssegers\Blade\Webman\View;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function is_dir;

#[AsCommand('blade:clear', 'Clear all compiled view files')]
class BladeClear extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cachePath = View::bladeConfig()['cache_path'];
        $cleared = 0;

        if (is_dir($cachePath)) {
            foreach (glob($cachePath . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $cleared++;
                }
            }
        }

        $output->writeln(sprintf('<info>Cleared %d compiled view file(s) from %s.</info>', $cleared, $cachePath));

        return 0;
    }
}
