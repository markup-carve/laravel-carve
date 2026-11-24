<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests\Service;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use MarkupCarve\Carve\Extension\Navigation;
use MarkupCarve\LaravelCarve\Service\CarveConverter;
use MarkupCarve\LaravelCarve\Service\CarveManager;
use PHPUnit\Framework\TestCase;

class AuthoringConverterTest extends TestCase
{
    public function testCacheSeparatesExtensionOptionsAndVersions(): void
    {
        $source = '[outside](https://example.test)';
        $plain = new CarveConverter(cache: new Repository(new ArrayStore()));
        $linked = new CarveConverter(options: ['extensions' => [Navigation::externalLinks()]], cache: new Repository(new ArrayStore()));
        self::assertStringNotContainsString('target="_blank"', $plain->toHtml($source));
        self::assertStringContainsString('target="_blank"', $linked->toHtml($source));
    }

    public function testCachePreservesMetadataAndRespectsTtlAndPrefix(): void
    {
        $store = new ArrayStore();
        $converter = new CarveConverter(cache: new Repository($store), cacheTtl: 60, cachePrefix: 'docs');
        $first = $converter->render("---json\n{\"a\":1}\n---\n\n# Title");
        $second = $converter->render($first->source);
        self::assertEquals($first, $second);
        self::assertCount(1, $second->toc());
        self::assertSame(1, $second->meta('a'));
        self::assertStringStartsWith('docs_render_', array_key_first($store->all(false)) ?? '');
    }

    public function testMutableEngineAccessAndCustomizersCannotServeStaleCache(): void
    {
        $converter = new CarveConverter(cache: new Repository(new ArrayStore()));
        self::assertStringContainsString('<h1>', $converter->toHtml('# Title'));
        // C2-M-066: changing an option must invalidate a cached render.
        $converter->setOption('rawHtml', true);
        self::assertStringContainsString('<b>raw</b>', $converter->toHtml('`<b>raw</b>`{=html}'));
        $manager = new CarveManager(['default' => $converter]);
        $manager->extend('default', static function ($engine): void {
            $engine->setOption('sections', false);
        });
        self::assertStringStartsWith('<h1', $manager->toHtml('# Title'));
    }

    public function testFlushDoesNotRepeatCustomizersOnExistingInstances(): void
    {
        $manager = new CarveManager(['default' => new CarveConverter()]);
        $calls = 0;
        $manager->extend('default', static function ($engine) use (&$calls): void {
            ++$calls;
            $engine->setOption('sections', false);
        });
        self::assertStringStartsWith('<h1', $manager->toHtml('# Title'));
        $manager->flush();
        self::assertStringStartsWith('<h1', $manager->toHtml('# Title'));
        self::assertSame(1, $calls);
    }

    public function testClosureExtensionsDoNotCollideWithoutExplicitVersion(): void
    {
        $converter = new CarveConverter(options: ['extensions' => [Navigation::headingNumbers()]]);
        self::assertStringContainsString('section-number', $converter->toHtml('= Title'));
    }

    public function testPendingFileOutputWriters(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'carve-writer-');
        self::assertNotFalse($file);
        file_put_contents($file, '*bold*');
        try {
            self::assertSame("bold\n", (new CarveConverter())->renderFileAs($file, 'text'));
        } finally {
            unlink($file);
        }
    }

    public function testFileResultKeepsDiagnosticsMetadataAndDependencyInvalidation(): void
    {
        $dir = sys_get_temp_dir() . '/carve-result-' . bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir . '/main.crv', "# Main\n\n{{ child.crv }}\n\n{{ missing.crv }}");
        file_put_contents($dir . '/child.crv', '# First');
        $converter = new CarveConverter(cache: new Repository(new ArrayStore()), includeRoot: $dir);
        try {
            $first = $converter->renderFile($dir . '/main.crv');
            self::assertCount(2, $first->toc());
            // C2-A-173: parse and render-loss channels both report the unresolved include.
            self::assertCount(2, $first->warnings);
            self::assertCount(2, $first->dependencies);
            self::assertEquals($first, $converter->renderFile($dir . '/main.crv'));
            file_put_contents($dir . '/child.crv', '# Second');
            self::assertStringContainsString('Second', $converter->renderFile($dir . '/main.crv')->html);
            // C2-T-005: the additional writers are pending carve-php issue 5235.
        } finally {
            unlink($dir . '/main.crv');
            unlink($dir . '/child.crv');
            rmdir($dir);
        }
    }
}
