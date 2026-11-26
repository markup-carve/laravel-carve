<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Commands;

use Illuminate\Console\Command;
use MarkupCarve\LaravelCarve\Service\CarveManager;
use Throwable;

class ConvertCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'carve:convert
        {path : The Markdown or HTML file to convert}
        {--from= : gfm, markdown (strict CommonMark) or html (guessed from the file extension; .md reads as gfm)}
        {--output= : Write to this file instead of the console}';

    /**
     * @var string
     */
    protected $description = 'Convert a Markdown or HTML file into Carve';

    public function handle(CarveManager $carve): int
    {
        $path = $this->argument('path');

        if (!is_string($path)) {
            return self::FAILURE;
        }

        if (!is_file($path) || !is_readable($path)) {
            $this->components->error("File [{$path}] is not readable.");

            return self::FAILURE;
        }

        $from = $this->option('from') ?: match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'md', 'markdown' => 'gfm',
            'html', 'htm' => 'html',
            default => null,
        };

        $source = @file_get_contents($path);
        if ($source === false) {
            $this->components->error("File [{$path}] is not readable.");

            return self::FAILURE;
        }

        try {
            $output = match ($from) {
                'markdown', 'gfm' => $carve->fromMarkdown($source, $from),
                'html' => $carve->fromHtml($source),
                default => null,
            };
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($output === null) {
            $this->components->error('Cannot tell the input format. Pass --from=markdown, --from=gfm or --from=html.');

            return self::FAILURE;
        }

        $target = $this->option('output');

        if (is_string($target) && $target !== '') {
            if (@file_put_contents($target, $output) === false) {
                $this->components->error("Cannot write to [{$target}].");

                return self::FAILURE;
            }
            $this->components->info("Written to [{$target}].");
        } else {
            $this->output->write($output);
        }

        return self::SUCCESS;
    }
}
