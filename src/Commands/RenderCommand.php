<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Commands;

use Illuminate\Console\Command;
use MarkupCarve\LaravelCarve\Service\CarveConverter;
use MarkupCarve\LaravelCarve\Service\CarveManager;
use Throwable;

class RenderCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'carve:render
        {path : The .crv file to render}
        {--format=html : html, text, markdown or ansi}
        {--profile= : The render profile (defaults to carve.default)}
        {--output= : Write to this file instead of the console}';

    /**
     * @var string
     */
    protected $description = 'Render a Carve file as HTML, plain text, Markdown or ANSI';

    public function handle(CarveManager $carve): int
    {
        $path = $this->argument('path');

        if (!is_string($path)) {
            return self::FAILURE;
        }
        $profile = $this->option('profile');
        $format = $this->option('format');

        if (!is_file($path) || !is_readable($path)) {
            $this->components->error("File [{$path}] is not readable.");

            return self::FAILURE;
        }

        try {
            $converter = $carve->profile(is_string($profile) ? $profile : null);
            if (!$converter instanceof CarveConverter) {
                $this->components->error('This converter does not support file format rendering.');

                return self::FAILURE;
            }
            $output = $converter->renderFileAs($path, is_string($format) ? $format : 'html');
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->write($output);
    }

    private function write(string $output): int
    {
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
