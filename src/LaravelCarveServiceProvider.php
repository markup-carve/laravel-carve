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
use MarkupCarve\Carve2\Extension\ExtensionDefinition;
use MarkupCarve\Carve2\Extension\ExtensionProvider;
use MarkupCarve\Carve2\Output\OutputWriter;
use MarkupCarve\LaravelCarve\Commands\ConvertCommand;
use MarkupCarve\LaravelCarve\Commands\FormatCommand;
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
            $this->commands([RenderCommand::class, ConvertCommand::class, LintCommand::class, FormatCommand::class]);
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
        $options = RenderPolicy::options($config);
        $extensions = [];
        $extensionConfig = $config['extensions'] ?? [];
        if (!is_array($extensionConfig)) {
            throw new InvalidArgumentException('carve extensions must be an array.');
        }
        foreach ($extensionConfig as $entry) {
            if (!is_string($entry) && !is_array($entry)
                && !$entry instanceof ExtensionDefinition && !$entry instanceof ExtensionProvider) {
                throw new InvalidArgumentException('Carve extensions must be names, configuration arrays or Carve2 definitions.');
            }
            array_push($extensions, ...$factory->create($entry));
        }
        if ($extensions !== []) {
            $options['extensions'] = $extensions;
        }
        $writers = [];
        $writerConfig = $config['writers'] ?? [];
        if (!is_array($writerConfig)) {
            throw new InvalidArgumentException('carve writers must be an array.');
        }
        foreach ($writerConfig as $writer) {
            if (!$writer instanceof OutputWriter) {
                throw new InvalidArgumentException('Carve writers must implement the Carve2 OutputWriter interface.');
            }
            $writers[] = $writer;
        }

        return new CarveConverter(
            options: $options,
            cache: $cache,
            includeRoot: $includeRoot,
            logger: $logger,
            cacheTtl: isset($cacheConfig['ttl']) && is_numeric($cacheConfig['ttl']) ? (int)$cacheConfig['ttl'] : null,
            cachePrefix: isset($cacheConfig['prefix']) && is_string($cacheConfig['prefix']) ? $cacheConfig['prefix'] : 'laravel_carve',
            cacheVersion: isset($config['cache_version']) && is_string($config['cache_version']) ? $config['cache_version'] : null,
            writers: $writers,
        );
    }
}
