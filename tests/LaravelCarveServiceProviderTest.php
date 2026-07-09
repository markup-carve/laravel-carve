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
        $text = Carve::toText('Hello *world*!');

        $this->assertStringContainsString('Hello world!', $text);
    }

    public function testDefaultConfigHasSafeMode(): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $this->app()->make(ConfigRepository::class);

        $this->assertTrue($config->get('carve.converters.default.safe_mode'));
    }

    public function testMultipleConvertersAreRegistered(): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $this->app()->make(ConfigRepository::class);
        $config->set('carve.converters', [
            'default' => ['safe_mode' => true],
            'trusted' => ['safe_mode' => false],
            'docs' => [
                'safe_mode' => false,
                'extensions' => ['autolink', 'smart_quotes'],
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
