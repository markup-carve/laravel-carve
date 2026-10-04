<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Facades;

use Illuminate\Support\Facades\Facade;
use MarkupCarve\LaravelCarve\Service\CarveManager;

/**
 * @see \MarkupCarve\LaravelCarve\Service\CarveManager
 * @method static \MarkupCarve\LaravelCarve\RenderedCarve render(string $source, ?string $converter = null)
 * @method static \MarkupCarve\LaravelCarve\RenderedCarve renderFile(string $path, ?string $converter = null)
 * @method static \MarkupCarve\Carve\Node\Document parse(string $source, ?string $converter = null)
 * @method static string fromMarkdown(string $source)
 * @method static string fromHtml(string $source)
 * @method static list<\MarkupCarve\Carve\Lint\LintWarning> lint(string $source)
 * @method static \MarkupCarve\LaravelCarve\Service\CarveConverterInterface profile(?string $name = null)
 * @method static static extend(string $name, \Closure $callback)
 * @method static void flush()
 * @method static list<string> profiles()
 * @method static string getDefaultProfile()
 * @method static string toHtml(string $carve, ?string $converter = null)
 * @method static string toHtmlFile(string $path, ?string $converter = null)
 * @method static array<string, mixed> toHtmlFileWithReport(string $path, ?string $converter = null)
 * @method static string toHtmlRaw(string $carve)
 * @method static string toText(string $carve, ?string $converter = null)
 * @method static string toMarkdown(string $carve, ?string $converter = null)
 * @method static string toAnsi(string $carve, ?string $converter = null)
 * @method static \MarkupCarve\LaravelCarve\Service\CarveConverterInterface converter(?string $name = null)
 * @method static array<string, \MarkupCarve\LaravelCarve\Service\CarveConverterInterface> getConverters()
 */
class Carve extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CarveManager::class;
    }
}
