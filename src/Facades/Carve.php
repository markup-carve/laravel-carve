<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Facades;

use Illuminate\Support\Facades\Facade;
use MarkupCarve\LaravelCarve\Service\CarveManager;

/**
 * @see \MarkupCarve\LaravelCarve\Service\CarveManager
 * @method static string toHtml(string $carve, string $converter = 'default')
 * @method static string toHtmlRaw(string $carve)
 * @method static string toText(string $carve, string $converter = 'default')
 * @method static string toMarkdown(string $carve, string $converter = 'default')
 * @method static string toAnsi(string $carve, string $converter = 'default')
 * @method static \MarkupCarve\LaravelCarve\Service\CarveConverterInterface converter(string $name = 'default')
 * @method static array<string, \MarkupCarve\LaravelCarve\Service\CarveConverterInterface> getConverters()
 */
class Carve extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CarveManager::class;
    }
}
