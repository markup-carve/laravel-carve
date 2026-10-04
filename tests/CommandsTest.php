<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests;

use Illuminate\Support\Facades\Artisan;

class CommandsTest extends TestCase
{
    public function testRenderIncludesInEveryFormatAndWritesOutput(): void
    {
        $dir = sys_get_temp_dir() . '/carve-command-' . bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir . '/main.crv', "# Main\n\n{{ child.crv }}");
        file_put_contents($dir . '/child.crv', 'Included *text*');
        config()->set('carve.include_root', $dir);
        try {
            foreach (['html', 'text', 'markdown', 'ansi'] as $format) {
                self::assertSame(0, Artisan::call('carve:render', ['path' => $dir . '/main.crv', '--format' => $format]));
                self::assertStringContainsString('Included', Artisan::output());
                self::assertStringNotContainsString('{{ child.crv }}', Artisan::output());
            }
            self::assertSame(0, Artisan::call('carve:render', ['path' => $dir . '/main.crv', '--output' => $dir . '/out.html']));
            self::assertStringContainsString('<strong>text</strong>', (string)file_get_contents($dir . '/out.html'));
            self::assertSame(1, Artisan::call('carve:render', ['path' => $dir . '/main.crv', '--format' => 'pdf']));
            self::assertSame(1, Artisan::call('carve:render', ['path' => $dir . '/main.crv', '--output' => $dir . '/missing/out.html']));
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }

    public function testConvertAndLintHaveUsefulExitStatuses(): void
    {
        $dir = sys_get_temp_dir() . '/carve-lint-' . bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir . '/source.md', '**bold** and *italic*');
        file_put_contents($dir . '/bad.crv', '**bold**');
        try {
            self::assertSame(0, Artisan::call('carve:convert', ['path' => $dir . '/source.md']));
            self::assertStringContainsString('*bold* and /italic/', Artisan::output());
            self::assertSame(1, Artisan::call('carve:lint', ['paths' => [$dir]]));
            self::assertStringContainsString('bad.crv:1:', Artisan::output());
            self::assertSame(1, Artisan::call('carve:lint', ['paths' => [$dir . '/missing']]));
            self::assertSame(1, Artisan::call('carve:convert', ['path' => $dir . '/missing']));
            self::assertSame(1, Artisan::call('carve:render', ['path' => $dir . '/missing']));
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }
}
