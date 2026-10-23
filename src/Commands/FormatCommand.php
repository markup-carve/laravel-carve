<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Commands;

use Illuminate\Console\Command;
use MarkupCarve\LaravelCarve\Service\CarveManager;
use Symfony\Component\Finder\Finder;

class FormatCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'carve:fmt
        {paths* : Files or directories (directories are searched for *.crv)}
        {--write : Rewrite each file in place}
        {--check : Exit unsuccessfully if a file is not formatted, without changing it}
        {--align-tables : Pad pipe table cells so the columns line up}';

    /**
     * @var string
     */
    protected $description = 'Print Carve files in canonical form, or rewrite or check them';

    public function handle(CarveManager $carve): int
    {
        $write = (bool)$this->option('write');
        $check = (bool)$this->option('check');
        if ($write && $check) {
            $this->components->error('--write and --check are mutually exclusive.');

            return self::FAILURE;
        }

        $failed = false;
        $unformatted = 0;
        foreach ($this->files($failed) as $file) {
            $source = @file_get_contents($file);
            if ($source === false) {
                $failed = true;
                $this->components->error("File [{$file}] is not readable.");

                continue;
            }
            $formatted = $carve->format($source, (bool)$this->option('align-tables'));
            if ($check) {
                if ($formatted !== $source) {
                    $unformatted++;
                    $this->line("{$file} is not formatted");
                }

                continue;
            }
            if ($write) {
                if ($formatted !== $source && @file_put_contents($file, $formatted) === false) {
                    $failed = true;
                    $this->components->error("File [{$file}] could not be written.");
                }

                continue;
            }
            $this->output->write($formatted);
        }

        if ($unformatted > 0) {
            $this->components->error(sprintf('%d file(s) not formatted.', $unformatted));

            return self::FAILURE;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
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
