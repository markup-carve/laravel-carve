<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\View\Factory;
use InvalidArgumentException;
use MarkupCarve\Carve\Extension\ExtensionInterface;
use MarkupCarve\LaravelCarve\Commands\ConvertCommand;
use MarkupCarve\LaravelCarve\Commands\LintCommand;
use MarkupCarve\LaravelCarve\Commands\RenderCommand;
use MarkupCarve\LaravelCarve\Service\CarveConverter;
use MarkupCarve\LaravelCarve\Service\CarveConverterInterface;
use MarkupCarve\LaravelCarve\Service\CarveManager;
use MarkupCarve\LaravelCarve\Service\ExtensionFactory;
use MarkupCarve\LaravelCarve\Service\RenderPolicy;
use MarkupCarve\LaravelCarve\View\CarveEngine;
use MarkupCarve\LaravelCarve\View\Components\Carve as CarveComponent;
use Psr\Log\LoggerInterface;

class LaravelCarveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/carve.php', 'carve');

        $this->app->singleton(ExtensionFactory::class, static fn (): ExtensionFactory => new ExtensionFactory());

        $this->app->singleton(CarveManager::class, function (Container $app): CarveManager {
            /** @var \Illuminate\Contracts\Config\Repository $configRepository */
            $configRepository = $app->make(ConfigRepository::class);
            /** @var array{default?: string, include_root?: string|null, converters?: array<string, array<string, mixed>>, cache?: array{enabled?: bool, store?: string|null}} $config */
            $config = $configRepository->get('carve', []);

            /** @var \MarkupCarve\LaravelCarve\Service\ExtensionFactory $factory */
            $factory = $app->make(ExtensionFactory::class);
            /** @var \Psr\Log\LoggerInterface $logger */
            $logger = $app->make(LoggerInterface::class);

            $cache = null;
            if (!empty($config['cache']['enabled'])) {
                /** @var \Illuminate\Contracts\Cache\Factory $cacheFactory */
                $cacheFactory = $app->make(CacheFactory::class);
                $cache = $cacheFactory->store($config['cache']['store'] ?? null);
            }

            $converters = [];
            foreach ($config['converters'] ?? [] as $name => $converterConfig) {
                $converters[$name] = function () use ($configRepository, $name, $cache, $factory, $logger): CarveConverter {
                    /** @var array<string, mixed> $settings */
                    $settings = $configRepository->get('carve.converters.' . $name, []);
                    $includeRoot = $configRepository->get('carve.include_root');

                    return $this->buildConverter($settings, $cache, $factory, is_string($includeRoot) ? $includeRoot : null, $logger, $configRepository->get('carve.cache', []));
                };
            }

            if ($converters === []) {
                $converters['default'] = new CarveConverter(cache: $cache, includeRoot: $config['include_root'] ?? null, logger: $logger);
            }

            return new CarveManager($converters, (string)($config['default'] ?? 'default'));
        });

        $this->app->alias(CarveManager::class, 'carve');

        $this->app->bind(CarveConverterInterface::class, static function (Container $app): CarveConverterInterface {
            /** @var \MarkupCarve\LaravelCarve\Service\CarveManager $manager */
            $manager = $app->make(CarveManager::class);

            return $manager->converter();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/carve.php' => $this->app->configPath('carve.php'),
        ], 'carve-config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'carve');
        Blade::component('carve', CarveComponent::class);
        if ($this->app->runningInConsole()) {
            $this->commands([RenderCommand::class, ConvertCommand::class, LintCommand::class]);
        }
        if (config('carve.views.enabled', true)) {
            $this->callAfterResolving('view', function (Factory $view): void {
                $view->addExtension('crv', 'carve', fn (): CarveEngine => new CarveEngine($this->app->make(CarveManager::class), is_string(config('carve.views.converter')) ? config('carve.views.converter') : null));
            });
        }
        Str::macro('carve', fn (string $source, ?string $converter = null): string => app(CarveManager::class)->toHtml($source, $converter));
        Str::macro('carveText', fn (string $source, ?string $converter = null): string => app(CarveManager::class)->toText($source, $converter));
        Stringable::macro('carve', function (?string $converter = null): Stringable {
            return new Stringable(app(CarveManager::class)->toHtml($this->value(), $converter));
        });
        Stringable::macro('carveText', function (?string $converter = null): Stringable {
            return new Stringable(app(CarveManager::class)->toText($this->value(), $converter));
        });
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
     * @param \Psr\Log\LoggerInterface $logger
     * @param mixed $cacheConfig
     * @param string|null $includeRoot
     *
     * @throws \InvalidArgumentException
     */
    private function buildConverter(
        array $config,
        ?CacheRepository $cache,
        ExtensionFactory $factory,
        ?string $includeRoot,
        LoggerInterface $logger,
        mixed $cacheConfig = [],
    ): CarveConverter {
        if (!is_array($cacheConfig)) {
            throw new InvalidArgumentException('carve.cache must be a configuration array.');
        }
        $extensions = [];
        $extConfigs = $config['extensions'] ?? [];
        if (is_array($extConfigs)) {
            foreach ($extConfigs as $extConfig) {
                if (is_string($extConfig)) {
                    $normalized = $extConfig;
                } elseif (is_array($extConfig)) {
                    /** @var array<string, mixed> $normalized */
                    $normalized = $extConfig;
                } elseif ($extConfig instanceof ExtensionInterface) {
                    $normalized = $extConfig;
                } else {
                    throw new InvalidArgumentException('Carve extensions must be names, configuration arrays or extension instances.');
                }
                $extension = $factory->create($normalized);
                if ($extension === null) {
                    throw new InvalidArgumentException('Unknown Carve extension in converter configuration.');
                }
                $extensions[] = $extension;
            }
        }

        $softBreakMode = $config['soft_break_mode'] ?? null;
        $symbols = [];
        $symbolConfig = $config['symbols'] ?? [];
        if (is_array($symbolConfig)) {
            foreach ($symbolConfig as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $symbols[$name] = $value;
                }
            }
        }

        $labels = [];
        if (isset($config['labels']) && is_array($config['labels'])) {
            foreach ($config['labels'] as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $labels[$key] = $value;
                }
            }
        }

        return new CarveConverter(
            safeMode: RenderPolicy::safeMode($config['safe_mode'] ?? true),
            mode: is_string($config['mode'] ?? null) ? $config['mode'] : 'interactive',
            softBreakMode: is_string($softBreakMode) ? $softBreakMode : null,
            xhtml: (bool)($config['xhtml'] ?? false),
            symbols: $symbols,
            sourceLines: (bool)($config['source_lines'] ?? false),
            cache: $cache,
            extensions: $extensions,
            includeRoot: $includeRoot,
            logger: $logger,
            preset: isset($config['preset']) && is_string($config['preset']) ? $config['preset'] : null,
            onDisallowed: isset($config['on_disallowed']) && is_string($config['on_disallowed']) ? $config['on_disallowed'] : null,
            smartTypography: isset($config['smart_typography']) ? (bool)$config['smart_typography'] : null,
            labels: $labels,
            cacheTtl: isset($cacheConfig['ttl']) && is_numeric($cacheConfig['ttl']) ? (int)$cacheConfig['ttl'] : null,
            cachePrefix: isset($cacheConfig['prefix']) && is_string($cacheConfig['prefix']) ? $cacheConfig['prefix'] : 'laravel_carve',
            cacheVersion: isset($config['cache_version']) && is_string($config['cache_version']) ? $config['cache_version'] : null,
        );
    }
}
