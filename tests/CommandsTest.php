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
            // C2-T-005: only HTML is registered until the writers in issue 5235 arrive.
            foreach (['html'] as $format) {
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

    public function testPendingRenderCommandWriters(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'carve-render-');
        self::assertNotFalse($file);
        file_put_contents($file, '*bold*');
        try {
            foreach (['text', 'markdown', 'ansi'] as $format) {
                self::assertSame(0, Artisan::call('carve:render', ['path' => $file, '--format' => $format]));
                self::assertStringContainsString('bold', Artisan::output());
            }
        } finally {
            unlink($file);
        }
    }

    public function testConvertAndLintHaveUsefulExitStatuses(): void
    {
        $dir = sys_get_temp_dir() . '/carve-lint-' . bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir . '/source.html', '<p><strong>bold</strong> and <em>italic</em></p>');
        file_put_contents($dir . '/bad.crv', '**bold**');
        try {
            // C2-T-003: HTML is the registered 0.2 importer.
            self::assertSame(0, Artisan::call('carve:convert', ['path' => $dir . '/source.html']));
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

    public function testPendingMarkdownConvertCommand(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'carve-import-');
        self::assertNotFalse($file);
        file_put_contents($file, "# Title\n");
        try {
            self::assertSame(0, Artisan::call('carve:convert', ['path' => $file, '--from' => 'markdown']));
            self::assertStringContainsString('= Title', Artisan::output());
        } finally {
            unlink($file);
        }
    }

    public function testConvertCommandImportsGitHubFlavoredMarkdown(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'carve-import-');
        self::assertNotFalse($file);
        file_put_contents($file, "> [!NOTE]\n> Read this.\n");
        try {
            self::assertSame(0, Artisan::call('carve:convert', ['path' => $file, '--from' => 'gfm']));
            self::assertStringContainsString("::: note\nRead this.\n:::", Artisan::output());
        } finally {
            unlink($file);
        }
    }

    public function testLintChecksIncludedFilesBelowAnIncludeRoot(): void
    {
        $dir = sys_get_temp_dir() . '/carve-lint-include-' . bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir . '/main.crv', "# Main\n\n{{ child.crv }}\n");
        file_put_contents($dir . '/child.crv', "**bold**\n");
        try {
            // C2-N-085: lint reports a missing resolver for an unresolved include.
            self::assertSame(1, Artisan::call('carve:lint', ['paths' => [$dir . '/main.crv']]));
            self::assertStringContainsString('include-no-resolver', Artisan::output());
            self::assertSame(1, Artisan::call('carve:lint', ['paths' => [$dir . '/main.crv'], '--include-root' => $dir]));
            self::assertStringContainsString('child.crv:1:', Artisan::output());
            self::assertSame(1, Artisan::call('carve:lint', ['paths' => [$dir . '/main.crv'], '--include-root' => $dir . '/missing']));
            self::assertStringContainsString('is not a directory', Artisan::output());
            self::assertSame(1, Artisan::call('carve:lint', ['paths' => [$dir], '--include-root' => $dir]));
            self::assertStringContainsString('1 Carve problem(s) found.', Artisan::output());
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }

    public function testFormatPrintsChecksAndWritesWithAlignedTables(): void
    {
        $dir = sys_get_temp_dir() . '/carve-fmt-' . bin2hex(random_bytes(6));
        mkdir($dir);
        $table = "|= a |= bb |\n| ccc | d |\n";
        $aligned = "|= a  |= bb |\n| ccc | d   |\n";
        file_put_contents($dir . '/table.crv', $table);
        try {
            self::assertSame(0, Artisan::call('carve:fmt', ['paths' => [$dir . '/table.crv']]));
            self::assertSame($table, Artisan::output());
            self::assertSame(0, Artisan::call('carve:fmt', ['paths' => [$dir], '--check' => true]));
            self::assertSame(1, Artisan::call('carve:fmt', ['paths' => [$dir], '--check' => true, '--align-tables' => true]));
            self::assertStringContainsString('table.crv is not formatted', Artisan::output());
            self::assertSame($table, file_get_contents($dir . '/table.crv'));
            self::assertSame(0, Artisan::call('carve:fmt', ['paths' => [$dir], '--write' => true, '--align-tables' => true]));
            self::assertSame($aligned, file_get_contents($dir . '/table.crv'));
            self::assertSame(0, Artisan::call('carve:fmt', ['paths' => [$dir], '--check' => true, '--align-tables' => true]));
            self::assertSame(1, Artisan::call('carve:fmt', ['paths' => [$dir], '--check' => true, '--write' => true]));
            self::assertSame(1, Artisan::call('carve:fmt', ['paths' => [$dir . '/missing']]));
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }
}
