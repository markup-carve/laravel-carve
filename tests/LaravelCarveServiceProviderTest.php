<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Foundation\Application;
use MarkupCarve\LaravelCarve\Facades\Carve;
use MarkupCarve\LaravelCarve\Service\CarveConverterInterface;
use MarkupCarve\LaravelCarve\Service\CarveManager;

class LaravelCarveServiceProviderTest extends TestCase
{
    private function app(): Application
    {
        $app = $this->app;
        $this->assertNotNull($app);

        return $app;
    }

    public function testManagerIsRegistered(): void
    {
        $this->assertInstanceOf(CarveManager::class, $this->app()->make(CarveManager::class));
    }

    public function testManagerIsAliasedToCarve(): void
    {
        $this->assertInstanceOf(CarveManager::class, $this->app()->make('carve'));
    }

    public function testDefaultConverterIsBound(): void
    {
        $this->assertInstanceOf(CarveConverterInterface::class, $this->app()->make(CarveConverterInterface::class));
    }

    public function testFacadeToHtml(): void
    {
        $html = Carve::toHtml('Hello *world*!');

        $this->assertStringContainsString('<strong>world</strong>', $html);
    }

    public function testFacadeSafeModeDefault(): void
    {
        $html = Carve::toHtml('[Click](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function testFacadeToText(): void
    {
        self::markTestSkipped('Carve text writer is pending carve-php issue 5235 (C2-T-005).');
    }

    public function testDefaultConfigHasSafeMode(): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $this->app()->make(ConfigRepository::class);

        // C2-M-066: raw HTML is disabled by default in the 0.2 policy.
        $this->assertFalse($config->get('carve.converters.default.raw_html'));
    }

    public function testDefaultConfigHasSymbolsAndSourceLines(): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $this->app()->make(ConfigRepository::class);

        $this->assertSame([], $config->get('carve.converters.default.symbols'));
        $this->assertFalse($config->get('carve.converters.default.source_lines'));
    }

    public function testNamedConverterHonorsSymbolsAndSourceLines(): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $this->app()->make(ConfigRepository::class);
        $config->set('carve.converters.preview', [
            'symbols' => ['mark' => '<span class="mark">M</span>'],
            'source_lines' => true,
        ]);
        $this->app()->forgetInstance(CarveManager::class);

        /** @var \MarkupCarve\LaravelCarve\Service\CarveManager $manager */
        $manager = $this->app()->make(CarveManager::class);
        $html = $manager->toHtml(':mark: :unknown:', 'preview');

        $this->assertStringContainsString('<span class="mark">M</span>', $html);
        $this->assertStringContainsString(':unknown:', $html);
        $this->assertStringContainsString('data-source-line="1"', $html);
    }

    public function testMultipleConvertersAreRegistered(): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $this->app()->make(ConfigRepository::class);
        $config->set('carve.converters', [
            // C2-M-066 and C2-X-039: 0.2 uses raw_html and the tabs registry.
            'default' => ['raw_html' => false],
            'trusted' => ['raw_html' => true],
            'docs' => [
                'raw_html' => false,
                'extensions' => ['tabs'],
            ],
        ]);
        $this->app()->forgetInstance(CarveManager::class);

        /** @var \MarkupCarve\LaravelCarve\Service\CarveManager $manager */
        $manager = $this->app()->make(CarveManager::class);

        $this->assertCount(3, $manager->getConverters());
        $this->assertArrayHasKey('default', $manager->getConverters());
        $this->assertArrayHasKey('trusted', $manager->getConverters());
        $this->assertArrayHasKey('docs', $manager->getConverters());
    }

    public function testCacheCanBeEnabled(): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $this->app()->make(ConfigRepository::class);
        $config->set('carve.cache.enabled', true);
        $config->set('cache.default', 'array');
        $this->app()->forgetInstance(CarveManager::class);

        /** @var \MarkupCarve\LaravelCarve\Service\CarveManager $manager */
        $manager = $this->app()->make(CarveManager::class);

        // First render populates cache, second retrieves
        $first = $manager->toHtml('Hello *world*!');
        $second = $manager->toHtml('Hello *world*!');

        $this->assertSame($first, $second);
        $this->assertStringContainsString('<strong>world</strong>', $first);
    }
}
