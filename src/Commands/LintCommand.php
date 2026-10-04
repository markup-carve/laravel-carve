<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Commands;

use Illuminate\Console\Command;
use MarkupCarve\LaravelCarve\Service\CarveManager;
use Symfony\Component\Finder\Finder;

class LintCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'carve:lint
        {paths* : Files or directories (directories are searched for *.crv)}';

    /**
     * @var string
     */
    protected $description = 'Report Carve constructs that probably do not mean what the author intended';

    public function handle(CarveManager $carve): int
    {
        $rows = [];
        $missing = false;
        $files = $this->files($missing);

        foreach ($files as $file) {
            $source = @file_get_contents($file);
            if ($source === false) {
                $missing = true;
                $this->components->error("File [{$file}] is not readable.");

                continue;
            }
            foreach ($carve->lint($source) as $warning) {
                $rows[] = ["{$file}:{$warning->line}:{$warning->column}", $warning->rule, $warning->message];
            }
        }

        if ($rows === []) {
            $this->components->info('No Carve problems found.');

            return $missing ? self::FAILURE : self::SUCCESS;
        }

        $this->table(['Location', 'Rule', 'Message'], $rows);
        $this->components->error(sprintf('%d Carve problem(s) found.', count($rows)));

        return self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function files(bool &$missing): array
    {
        $files = [];

        foreach ((array)$this->argument('paths') as $path) {
            if (is_dir($path)) {
                foreach (Finder::create()->files()->in($path)->name('*.crv')->sortByName() as $file) {
                    $files[] = $file->getPathname();
                }
            } elseif (is_file($path)) {
                $files[] = $path;
            } else {
                $missing = true;
                $this->components->warn("Skipping [{$path}]: not found.");
            }
        }

        return array_values(array_unique($files));
    }
}
