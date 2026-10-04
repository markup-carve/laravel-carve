<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests\Service;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use MarkupCarve\Carve\Extension\HeadingLevelShiftExtension;
use MarkupCarve\Carve\Extension\WikilinksExtension;
use MarkupCarve\LaravelCarve\Service\CarveConverter;
use MarkupCarve\LaravelCarve\Service\CarveManager;
use PHPUnit\Framework\TestCase;

class AuthoringConverterTest extends TestCase
{
    public function testCacheSeparatesExtensionOptionsAndVersions(): void
    {
        $cache = new Repository(new ArrayStore());
        $a = new CarveConverter(cache: $cache, extensions: [new HeadingLevelShiftExtension(shift: 1)]);
        $b = new CarveConverter(cache: $cache, extensions: [new HeadingLevelShiftExtension(shift: 2)]);
        self::assertStringContainsString('<h2>', $a->toHtml('# Title'));
        self::assertStringContainsString('<h3>', $b->toHtml('# Title'));
        $v1 = new CarveConverter(cache: $cache, cacheVersion: '1', extensions: [new HeadingLevelShiftExtension(shift: 1)]);
        $v2 = new CarveConverter(cache: $cache, cacheVersion: '2', extensions: [new HeadingLevelShiftExtension(shift: 2)]);
        self::assertStringContainsString('<h2>', $v1->toHtml('# Title'));
        self::assertStringContainsString('<h3>', $v2->toHtml('# Title'));
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
        $converter->getConverter()->addExtension(new HeadingLevelShiftExtension(shift: 1));
        self::assertStringContainsString('<h2>', $converter->toHtml('# Title'));
        $manager = new CarveManager(['default' => $converter]);
        $manager->extend('default', static function ($engine): void {
            $engine->addOutputTransformer(static fn (string $html): string => 'custom:' . $html);
        });
        self::assertStringStartsWith('custom:', $manager->toHtml('# Title'));
    }

    public function testFlushDoesNotRepeatCustomizersOnExistingInstances(): void
    {
        $manager = new CarveManager(['default' => new CarveConverter()]);
        $manager->extend('default', static function ($engine): void {
            $engine->addOutputTransformer(static fn (string $html): string => 'once:' . $html);
        });
        self::assertStringStartsWith('once:', $manager->toHtml('text'));
        $manager->flush();
        self::assertStringNotContainsString('once:once:', $manager->toHtml('text'));
    }

    public function testClosureExtensionsDoNotCollideWithoutExplicitVersion(): void
    {
        $cache = new Repository(new ArrayStore());
        $a = new CarveConverter(cache: $cache, extensions: [new WikilinksExtension(static fn (string $page): string => '/a/' . $page)]);
        $b = new CarveConverter(cache: $cache, extensions: [new WikilinksExtension(static fn (string $page): string => '/b/' . $page)]);
        self::assertStringContainsString('href="/a/Page"', $a->toHtml('[[Page]]'));
        self::assertStringContainsString('href="/b/Page"', $b->toHtml('[[Page]]'));
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
            self::assertCount(1, $first->warnings);
            self::assertCount(2, $first->dependencies);
            self::assertEquals($first, $converter->renderFile($dir . '/main.crv'));
            file_put_contents($dir . '/child.crv', '# Second');
            self::assertStringContainsString('Second', $converter->renderFile($dir . '/main.crv')->html);
            foreach (['text', 'markdown', 'ansi'] as $format) {
                self::assertStringContainsString('Second', $converter->renderFileAs($dir . '/main.crv', $format));
                self::assertStringNotContainsString('{{ child.crv }}', $converter->renderFileAs($dir . '/main.crv', $format));
            }
        } finally {
            unlink($dir . '/main.crv');
            unlink($dir . '/child.crv');
            rmdir($dir);
        }
    }
}
