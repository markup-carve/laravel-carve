<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests\Service;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use InvalidArgumentException;
use MarkupCarve\Carve\Node\Document;
use MarkupCarve\LaravelCarve\Service\CarveConverter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CarveConverterTest extends TestCase
{
    public function testFileIncludesAreContainedReportedAndLoggedSafely(): void
    {
        $root = sys_get_temp_dir() . '/laravel-carve-' . bin2hex(random_bytes(6));
        mkdir($root . '/chapters', 0777, true);
        file_put_contents($root . '/main.crv', "{{ chapters/one.crv }}\n");
        file_put_contents($root . '/chapters/one.crv', "Included.\n");
        try {
            $result = (new CarveConverter(includeRoot: $root, logger: new NullLogger()))
                ->toHtmlFileWithReport($root . '/main.crv');
            self::assertStringContainsString('Included.', $result['value']);
            self::assertSame([['path' => 'chapters/one.crv', 'resolved' => true]], $result['dependencies']);
        } finally {
            unlink($root . '/chapters/one.crv');
            unlink($root . '/main.crv');
            rmdir($root . '/chapters');
            rmdir($root);
        }
    }

    public function testFileCacheChangesWhenIncludedFileChanges(): void
    {
        $root = sys_get_temp_dir() . '/laravel-carve-' . bin2hex(random_bytes(6));
        mkdir($root);
        file_put_contents($root . '/main.crv', "{{ child.crv }}\n");
        file_put_contents($root . '/child.crv', "FIRST\n");
        $converter = new CarveConverter(cache: new Repository(new ArrayStore()), includeRoot: $root);
        try {
            self::assertStringContainsString('FIRST', $converter->toHtmlFile($root . '/main.crv'));
            file_put_contents($root . '/child.crv', "SECOND\n");
            self::assertStringContainsString('SECOND', $converter->toHtmlFile($root . '/main.crv'));
        } finally {
            unlink($root . '/child.crv');
            unlink($root . '/main.crv');
            rmdir($root);
        }
    }

    public function testTraversalAndSymlinkEscapeStayLiteral(): void
    {
        $root = sys_get_temp_dir() . '/laravel-carve-' . bin2hex(random_bytes(6));
        $outside = tempnam(sys_get_temp_dir(), 'laravel-carve-secret-');
        mkdir($root);
        file_put_contents($outside, "SECRET\n");
        symlink($outside, $root . '/linked.crv');
        file_put_contents($root . '/main.crv', '{{ ../' . basename($outside) . " }}\n\n{{ linked.crv }}\n");
        try {
            $result = (new CarveConverter(includeRoot: $root))->toHtmlFileWithReport($root . '/main.crv');
            self::assertStringNotContainsString('SECRET', $result['value']);
            self::assertCount(2, $result['dependencies']);
            self::assertSame([false, false], array_column($result['dependencies'], 'resolved'));
        } finally {
            unlink($root . '/linked.crv');
            unlink($root . '/main.crv');
            unlink($outside);
            rmdir($root);
        }
    }

    public function testStringRenderLeavesIncludesLiteralAndRelativeRootIsRejected(): void
    {
        self::assertStringContainsString('{{ child.crv }}', (new CarveConverter())->toHtml('{{ child.crv }}'));
        $this->expectException(InvalidArgumentException::class);
        new CarveConverter(includeRoot: 'content');
    }

    public function testToHtml(): void
    {
        $converter = new CarveConverter();

        $html = $converter->toHtml('Hello *world*!');

        $this->assertStringContainsString('<strong>world</strong>', $html);
    }

    public function testToHtmlWithEmphasis(): void
    {
        $converter = new CarveConverter();

        $html = $converter->toHtml('Hello /world/!');

        $this->assertStringContainsString('<em>world</em>', $html);
    }

    public function testToText(): void
    {
        $converter = new CarveConverter();

        $text = $converter->toText('Hello *world*!');

        $this->assertStringContainsString('Hello world!', $text);
    }

    public function testSafeModeEnabledByDefault(): void
    {
        $converter = new CarveConverter();

        $html = $converter->toHtml('[Click me](javascript:alert("xss"))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function testSafeModeDisabled(): void
    {
        $converter = new CarveConverter(safeMode: false);

        $html = $converter->toHtml('[Click me](javascript:alert(1))');

        // Carve's URL scheme denylist is normative (spec SS25): even with
        // safe mode off, a javascript: destination renders as an empty href.
        $this->assertStringContainsString('href=""', $html);
    }

    public function testConfiguredSymbolIsAppliedAndUnmappedSymbolRemainsLiteral(): void
    {
        $converter = new CarveConverter(symbols: [
            'mark' => '<span class="mark">M</span>',
        ]);

        $html = $converter->toHtml(':mark: :unknown:');

        $this->assertStringContainsString('<span class="mark">M</span>', $html);
        $this->assertStringContainsString(':unknown:', $html);
    }

    public function testSourceLinesCanBeEnabled(): void
    {
        $html = (new CarveConverter(sourceLines: true))->toHtml("# Heading\n\nParagraph");

        $this->assertStringContainsString('data-source-line="1"', $html);
        $this->assertStringContainsString('data-source-line="3"', $html);
    }

    public function testSourceLinesAreDisabledByDefault(): void
    {
        $html = (new CarveConverter())->toHtml('# Heading');

        $this->assertStringNotContainsString('data-source-line=', $html);
    }

    public function testToMarkdownRendersMarkdown(): void
    {
        $converter = new CarveConverter();

        $markdown = $converter->toMarkdown('# Hi

/em/ and *strong*
');

        $this->assertStringContainsString('# Hi', $markdown);
        $this->assertStringContainsString('**strong**', $markdown);
    }

    public function testToAnsiRendersTerminalOutput(): void
    {
        $converter = new CarveConverter();

        $ansi = $converter->toAnsi('*strong*');

        $this->assertStringContainsString("\e[", $ansi);
        $this->assertStringContainsString('strong', $ansi);
    }

    public function testParseReturnsDocument(): void
    {
        $converter = new CarveConverter();

        $document = $converter->parse('# Heading');

        $this->assertInstanceOf(Document::class, $document);
    }

    /**
     * Two named profiles share one cache store. Before the key carried a
     * converter signature, the first render of a given source won and served
     * its HTML to the other profile - so an admin profile with safe mode off
     * could hand raw HTML to the comment profile that had asked for safe mode.
     */
    public function testCacheDoesNotLeakBetweenConvertersWithDifferentSettings(): void
    {
        $cache = new Repository(new ArrayStore());
        $source = 'a `<b>raw</b>`{=html} span';

        $safe = new CarveConverter(safeMode: true, cache: $cache);
        $unsafe = new CarveConverter(safeMode: false, cache: $cache);

        $this->assertStringNotContainsString('<b>raw</b>', $safe->toHtml($source));
        $this->assertStringContainsString('<b>raw</b>', $unsafe->toHtml($source));
    }

    public function testCacheDoesNotLeakBetweenConvertersWithDifferentSymbols(): void
    {
        $cache = new Repository(new ArrayStore());

        $rocket = new CarveConverter(symbols: ['x' => 'ROCKET'], cache: $cache);
        $tada = new CarveConverter(symbols: ['x' => 'TADA'], cache: $cache);

        $this->assertStringContainsString('ROCKET', $rocket->toHtml('see :x: here'));
        $this->assertStringContainsString('TADA', $tada->toHtml('see :x: here'));
    }
}
