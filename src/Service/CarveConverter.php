<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Service;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use MarkupCarve\Carve\CarveConverter as BaseCarveConverter;
use MarkupCarve\Carve\Node\Document;
use MarkupCarve\Carve\Renderer\AnsiRenderer;
use MarkupCarve\Carve\Renderer\MarkdownRenderer;
use MarkupCarve\Carve\Renderer\PlainTextRenderer;
use MarkupCarve\Carve\Renderer\RenderMode;
use MarkupCarve\Carve\Renderer\SoftBreakMode;

class CarveConverter implements CarveConverterInterface
{
    private BaseCarveConverter $converter;

    private PlainTextRenderer $textRenderer;

    /**
     * @param bool $safeMode
     * @param string $mode Render mode: 'interactive' (default) or 'static'
     *   (graceful degradation for print/email/PDF targets)
     * @param string|null $softBreakMode
     * @param bool $xhtml
     * @param \Illuminate\Contracts\Cache\Repository|null $cache
     * @param array<\MarkupCarve\Carve\Extension\ExtensionInterface> $extensions
     */
    public function __construct(
        bool $safeMode = true,
        string $mode = RenderMode::INTERACTIVE,
        ?string $softBreakMode = null,
        bool $xhtml = false,
        private ?CacheRepository $cache = null,
        array $extensions = [],
    ) {
        $this->converter = new BaseCarveConverter(
            xhtml: $xhtml,
            safeMode: $safeMode,
            mode: $mode,
            softBreakMode: $softBreakMode !== null ? SoftBreakMode::from($softBreakMode) : null,
        );
        $this->textRenderer = new PlainTextRenderer();

        foreach ($extensions as $extension) {
            $this->converter->addExtension($extension);
        }
    }

    public function toHtml(string $carve): string
    {
        if ($this->cache !== null) {
            $cacheKey = 'laravel_carve_html_' . hash('xxh3', $carve);

            /** @var string|null $cached */
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $html = $this->converter->convert($carve);
            $this->cache->forever($cacheKey, $html);

            return $html;
        }

        return $this->converter->convert($carve);
    }

    public function toText(string $carve): string
    {
        $document = $this->converter->parse($carve);

        return $this->textRenderer->render($document);
    }

    public function toMarkdown(string $carve): string
    {
        $document = $this->converter->parse($carve);

        return (new MarkdownRenderer())->render($document);
    }

    public function toAnsi(string $carve): string
    {
        $document = $this->converter->parse($carve);

        return (new AnsiRenderer())->render($document);
    }

    public function parse(string $carve): Document
    {
        return $this->converter->parse($carve);
    }

    public function getConverter(): BaseCarveConverter
    {
        return $this->converter;
    }
}
