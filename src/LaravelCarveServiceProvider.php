<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use MarkupCarve\LaravelCarve\Service\CarveConverter;
use MarkupCarve\LaravelCarve\Service\CarveConverterInterface;
use MarkupCarve\LaravelCarve\Service\CarveManager;
use MarkupCarve\LaravelCarve\Service\ExtensionFactory;

class LaravelCarveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/carve.php', 'carve');

        $this->app->singleton(ExtensionFactory::class, static fn (): ExtensionFactory => new ExtensionFactory());

        $this->app->singleton(CarveManager::class, function (Container $app): CarveManager {
            /** @var \Illuminate\Contracts\Config\Repository $configRepository */
            $configRepository = $app->make(ConfigRepository::class);
            /** @var array{converters?: array<string, array<string, mixed>>, cache?: array{enabled?: bool, store?: string|null}} $config */
            $config = $configRepository->get('carve', []);

            /** @var \MarkupCarve\LaravelCarve\Service\ExtensionFactory $factory */
            $factory = $app->make(ExtensionFactory::class);

            $cache = null;
            if (!empty($config['cache']['enabled'])) {
                /** @var \Illuminate\Contracts\Cache\Factory $cacheFactory */
                $cacheFactory = $app->make(CacheFactory::class);
                $cache = $cacheFactory->store($config['cache']['store'] ?? null);
            }

            $converters = [];
            foreach ($config['converters'] ?? [] as $name => $converterConfig) {
                $converters[$name] = $this->buildConverter($converterConfig, $cache, $factory);
            }

            if ($converters === []) {
                $converters['default'] = new CarveConverter(cache: $cache);
            }

            return new CarveManager($converters);
        });

        $this->app->alias(CarveManager::class, 'carve');

        $this->app->bind(CarveConverterInterface::class, static function (Container $app): CarveConverterInterface {
            /** @var \MarkupCarve\LaravelCarve\Service\CarveManager $manager */
            $manager = $app->make(CarveManager::class);

            return $manager->converter('default');
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/carve.php' => $this->app->configPath('carve.php'),
        ], 'carve-config');

        $this->registerBladeDirectives();
    }

    private function registerBladeDirectives(): void
    {
        Blade::directive('carve', static function (string $expression): string {
            return "<?php echo app(\MarkupCarve\LaravelCarve\Service\CarveManager::class)->toHtml({$expression}); ?>";
        });

        Blade::directive('carveRaw', static function (string $expression): string {
            return "<?php echo app(\MarkupCarve\LaravelCarve\Service\CarveManager::class)->toHtmlRaw({$expression}); ?>";
        });

        Blade::directive('carveText', static function (string $expression): string {
            return "<?php echo e(app(\MarkupCarve\LaravelCarve\Service\CarveManager::class)->toText({$expression})); ?>";
        });
    }

    /**
     * @param array<string, mixed> $config
     * @param \Illuminate\Contracts\Cache\Repository|null $cache
     * @param \MarkupCarve\LaravelCarve\Service\ExtensionFactory $factory
     */
    private function buildConverter(
        array $config,
        ?CacheRepository $cache,
        ExtensionFactory $factory,
    ): CarveConverter {
        $extensions = [];
        $extConfigs = $config['extensions'] ?? [];
        if (is_array($extConfigs)) {
            foreach ($extConfigs as $extConfig) {
                if (is_string($extConfig)) {
                    $normalized = $extConfig;
                } elseif (is_array($extConfig)) {
                    /** @var array<string, mixed> $normalized */
                    $normalized = $extConfig;
                } else {
                    continue;
                }
                $extension = $factory->create($normalized);
                if ($extension !== null) {
                    $extensions[] = $extension;
                }
            }
        }

        $softBreakMode = $config['soft_break_mode'] ?? null;

        return new CarveConverter(
            safeMode: (bool)($config['safe_mode'] ?? true),
            mode: is_string($config['mode'] ?? null) ? $config['mode'] : 'interactive',
            softBreakMode: is_string($softBreakMode) ? $softBreakMode : null,
            xhtml: (bool)($config['xhtml'] ?? false),
            cache: $cache,
            extensions: $extensions,
        );
    }
}
