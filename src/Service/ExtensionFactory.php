<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Service;

use MarkupCarve\Carve\Extension\AdmonitionExtension;
use MarkupCarve\Carve\Extension\AutolinkExtension;
use MarkupCarve\Carve\Extension\CitationsExtension;
use MarkupCarve\Carve\Extension\CodeCalloutsExtension;
use MarkupCarve\Carve\Extension\CodeGroupExtension;
use MarkupCarve\Carve\Extension\ColorSwatchExtension;
use MarkupCarve\Carve\Extension\DefaultAttributesExtension;
use MarkupCarve\Carve\Extension\DetailsExtension;
use MarkupCarve\Carve\Extension\ExtensionInterface;
use MarkupCarve\Carve\Extension\ExternalLinksExtension;
use MarkupCarve\Carve\Extension\FencedRenderExtension;
use MarkupCarve\Carve\Extension\FrontmatterExtension;
use MarkupCarve\Carve\Extension\GlossaryExtension;
use MarkupCarve\Carve\Extension\HeadingLevelShiftExtension;
use MarkupCarve\Carve\Extension\HeadingNumbersExtension;
use MarkupCarve\Carve\Extension\HeadingPermalinksExtension;
use MarkupCarve\Carve\Extension\HeadingReferenceExtension;
use MarkupCarve\Carve\Extension\IndexExtension;
use MarkupCarve\Carve\Extension\InlineFootnotesExtension;
use MarkupCarve\Carve\Extension\ListTableExtension;
use MarkupCarve\Carve\Extension\MathBlockExtension;
use MarkupCarve\Carve\Extension\MentionsExtension;
use MarkupCarve\Carve\Extension\SemanticSpanExtension;
use MarkupCarve\Carve\Extension\SmartQuotesExtension;
use MarkupCarve\Carve\Extension\SpoilerExtension;
use MarkupCarve\Carve\Extension\TableOfContentsExtension;
use MarkupCarve\Carve\Extension\TabNormalizeExtension;
use MarkupCarve\Carve\Extension\TabsExtension;
use MarkupCarve\Carve\Extension\TocPlacementExtension;
use MarkupCarve\Carve\Extension\WikilinksExtension;

class ExtensionFactory
{
    /**
     * Create a carve extension instance from a config entry.
     *
     * Accepts either a shorthand string (`'autolink'`) or a full array
     * (`['type' => 'autolink', ...]`). Only options explicitly set in the
     * config are forwarded — unspecified options keep the library defaults.
     * Returns null for unknown types.
     *
     * @param array<string, mixed>|string $config
     */
    public function create(array|string $config): ?ExtensionInterface
    {
        if (is_string($config)) {
            $config = ['type' => $config];
        }

        $type = $config['type'] ?? null;

        return match ($type) {
            'admonition' => $this->admonition($config),
            'autolink' => $this->autolink($config),
            'code_group' => $this->codeGroup($config),
            'default_attributes' => $this->defaultAttributes($config),
            'external_links' => $this->externalLinks($config),
            'frontmatter' => $this->frontmatter($config),
            'heading_level_shift' => $this->headingLevelShift($config),
            'heading_permalinks' => $this->headingPermalinks($config),
            'heading_reference' => $this->headingReference($config),
            'inline_footnotes' => $this->inlineFootnotes($config),
            'mentions' => $this->mentions($config),
            'citations' => $this->citations($config),
            'code_callouts' => new CodeCalloutsExtension(),
            'color_swatch' => new ColorSwatchExtension(),
            'details' => new DetailsExtension(),
            'fenced_render' => $this->fencedRender($config),
            'glossary' => new GlossaryExtension(),
            'heading_numbers' => $this->headingNumbers($config),
            'index' => new IndexExtension(),
            'list_table' => new ListTableExtension(),
            'math_block' => $this->mathBlock($config),
            'mermaid' => $this->fencedRender(['language' => 'mermaid'] + $config),
            'spoiler' => new SpoilerExtension(),
            'tab_normalize' => $this->tabNormalize($config),
            'toc_placement' => new TocPlacementExtension(),
            'semantic_span' => new SemanticSpanExtension(),
            'smart_quotes' => $this->smartQuotes($config),
            'table_of_contents' => $this->tableOfContents($config),
            'tabs' => $this->tabs($config),
            'wikilinks' => $this->wikilinks($config),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function admonition(array $config): AdmonitionExtension
    {
        /** @var array{types?: array<string>, defaultTitle?: bool, titleTag?: string, titleClass?: string, containerClass?: string, icons?: bool|array<string, string>, iconClass?: string} $args */
        $args = [];
        $this->setStringList($args, 'types', $config, 'types');
        $this->setBool($args, 'defaultTitle', $config, 'default_title');
        $this->setString($args, 'titleTag', $config, 'title_tag');
        $this->setString($args, 'titleClass', $config, 'title_class');
        $this->setString($args, 'containerClass', $config, 'container_class');
        if (array_key_exists('icons', $config)) {
            $icons = $config['icons'];
            if (is_bool($icons) || is_array($icons)) {
                $args['icons'] = $icons;
            }
        }
        $this->setString($args, 'iconClass', $config, 'icon_class');

        return new AdmonitionExtension(...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function autolink(array $config): AutolinkExtension
    {
        /** @var array{allowedSchemes?: array<string>} $args */
        $args = [];
        $this->setStringList($args, 'allowedSchemes', $config, 'allowed_schemes');

        return new AutolinkExtension(...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function codeGroup(array $config): CodeGroupExtension
    {
        /** @var array{wrapperClass?: string, panelClass?: string, labelClass?: string, radioClass?: string, idPrefix?: string} $args */
        $args = [];
        $this->setString($args, 'wrapperClass', $config, 'wrapper_class');
        $this->setString($args, 'panelClass', $config, 'panel_class');
        $this->setString($args, 'labelClass', $config, 'label_class');
        $this->setString($args, 'radioClass', $config, 'radio_class');
        $this->setString($args, 'idPrefix', $config, 'id_prefix');

        return new CodeGroupExtension(...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function defaultAttributes(array $config): DefaultAttributesExtension
    {
        /** @var array{defaults?: array<string, array<string, string>>} $args */
        $args = [];
        if (isset($config['defaults']) && is_array($config['defaults']) && $config['defaults'] !== []) {
            /** @var array<string, array<string, string>> $defaults */
            $defaults = $config['defaults'];
            $args['defaults'] = $defaults;
        }

        return new DefaultAttributesExtension(...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function externalLinks(array $config): ExternalLinksExtension
    {
        /** @var array{internalHosts?: array<string>, target?: string, rel?: string, nofollow?: bool} $args */
        $args = [];
        $this->setStringList($args, 'internalHosts', $config, 'internal_hosts');
        $this->setString($args, 'target', $config, 'target');
        $this->setString($args, 'rel', $config, 'rel');
        $this->setBool($args, 'nofollow', $config, 'nofollow');

        return new ExternalLinksExtension(...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function frontmatter(array $config): FrontmatterExtension
    {
        /** @var array{defaultFormat?: string, renderAsComment?: bool} $args */
        $args = [];
        $this->setString($args, 'defaultFormat', $config, 'default_format');
        $this->setBool($args, 'renderAsComment', $config, 'render_as_comment');

        return new FrontmatterExtension(...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function headingLevelShift(array $config): HeadingLevelShiftExtension
    {
        /** @var array{shift?: int} $args */
        $args = [];
        $this->setInt($args, 'shift', $config, 'shift');

        return new HeadingLevelShiftExtension(...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function headingPermalinks(array $config): HeadingPermalinksExtension
    {
        /** @var array{symbol?: string, position?: string, cssClass?: string, ariaLabel?: string, showOnHover?: bool} $args */
        $args = [];
        $this->setString($args, 'symbol', $config, 'symbol');
        $this->setString($args, 'position', $config, 'position');
        $this->setString($args, 'cssClass', $config, 'class');
        $this->setString($args, 'ariaLabel', $config, 'aria_label');
        $this->setBool($args, 'showOnHover', $config, 'show_on_hover');

        return new HeadingPermalinksExtension(...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function headingReference(array $config): HeadingReferenceExtension
    {
        /** @var array{cssClass?: string} $args */
        $args = [];
        $this->setString($args, 'cssClass', $config, 'css_class');

        return new HeadingReferenceExtension(...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function inlineFootnotes(array $config): InlineFootnotesExtension
    {
        /** @var array{cssClass?: string} $args */
        $args = [];
        $this->setString($args, 'cssClass', $config, 'css_class');

        return new InlineFootnotesExtension(...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mentions(array $config): MentionsExtension
    {
        /** @var array{mentionUrl?: string, tagUrl?: string, mentionClass?: string, tagClass?: string} $args */
        $args = [];
        $this->setString($args, 'mentionUrl', $config, 'mention_url');
        $this->setString($args, 'tagUrl', $config, 'tag_url');
        $this->setString($args, 'mentionClass', $config, 'mention_class');
        $this->setString($args, 'tagClass', $config, 'tag_class');

        return new MentionsExtension(...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function fencedRender(array $config): FencedRenderExtension
    {
        /** @var array{cssClass?: string, tag?: string, contentMode?: string, wrapInFigure?: bool, figureClass?: string} $args */
        $args = [];
        $this->setString($args, 'cssClass', $config, 'css_class');
        $this->setString($args, 'tag', $config, 'tag');
        $this->setString($args, 'contentMode', $config, 'content_mode');
        $this->setBool($args, 'wrapInFigure', $config, 'wrap_in_figure');
        $this->setString($args, 'figureClass', $config, 'figure_class');
        $language = $config['language'] ?? 'mermaid';
        /** @var array<string>|string $language */

        return new FencedRenderExtension($language, ...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function citations(array $config): CitationsExtension
    {
        $mode = is_string($config['mode'] ?? null) ? $config['mode'] : 'numbered';
        $bibliography = is_array($config['bibliography'] ?? null) ? $config['bibliography'] : null;

        return new CitationsExtension($mode, $bibliography);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function headingNumbers(array $config): HeadingNumbersExtension
    {
        /** @var array{minLevel?: int, label?: string} $args */
        $args = [];
        $this->setInt($args, 'minLevel', $config, 'min_level');
        $this->setString($args, 'label', $config, 'label');

        return new HeadingNumbersExtension(...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mathBlock(array $config): MathBlockExtension
    {
        $language = is_string($config['language'] ?? null) ? $config['language'] : 'math';

        return new MathBlockExtension($language);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function tabNormalize(array $config): TabNormalizeExtension
    {
        $width = is_int($config['width'] ?? null) ? $config['width'] : 2;

        return new TabNormalizeExtension($width);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function smartQuotes(array $config): SmartQuotesExtension
    {
        /** @var array{locale?: string} $args */
        $args = [];
        $this->setString($args, 'locale', $config, 'locale');

        return new SmartQuotesExtension(...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function tableOfContents(array $config): TableOfContentsExtension
    {
        /** @var array{minLevel?: int, maxLevel?: int, cssClass?: string} $args */
        $args = [];
        $this->setInt($args, 'minLevel', $config, 'min_level');
        $this->setInt($args, 'maxLevel', $config, 'max_level');
        $this->setString($args, 'cssClass', $config, 'toc_class');

        return new TableOfContentsExtension(...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function tabs(array $config): TabsExtension
    {
        /** @var array{mode?: string, wrapperClass?: string, tabClass?: string, labelClass?: string, radioClass?: string, idPrefix?: string} $args */
        $args = [];
        $this->setString($args, 'mode', $config, 'mode');
        $this->setString($args, 'wrapperClass', $config, 'wrapper_class');
        $this->setString($args, 'tabClass', $config, 'tab_class');
        $this->setString($args, 'labelClass', $config, 'label_class');
        $this->setString($args, 'radioClass', $config, 'radio_class');
        $this->setString($args, 'idPrefix', $config, 'id_prefix');

        return new TabsExtension(...$args);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function wikilinks(array $config): WikilinksExtension
    {
        $cssClass = isset($config['link_class']) && is_string($config['link_class'])
            ? $config['link_class']
            : 'wikilink';
        $template = $config['url_template'] ?? null;

        if (is_string($template)) {
            $urlGenerator = static function (string $page) use ($template): string {
                $slug = strtolower(trim($page));
                $slug = (string)preg_replace('/\s+/', '-', $slug);
                $slug = (string)preg_replace('/[^a-z0-9\-_\/]/', '', $slug);
                $slug = (string)preg_replace('/-+/', '-', $slug);

                return str_replace('{page}', $slug, $template);
            };

            return new WikilinksExtension($urlGenerator, $cssClass);
        }

        if (isset($config['link_class'])) {
            return new WikilinksExtension(cssClass: $cssClass);
        }

        return new WikilinksExtension();
    }

    /**
     * @param array<string, mixed> $args
     * @param string $argKey
     * @param array<string, mixed> $config
     * @param string $configKey
     */
    private function setString(array &$args, string $argKey, array $config, string $configKey): void
    {
        if (isset($config[$configKey]) && is_string($config[$configKey])) {
            $args[$argKey] = $config[$configKey];
        }
    }

    /**
     * @param array<string, mixed> $args
     * @param string $argKey
     * @param array<string, mixed> $config
     * @param string $configKey
     */
    private function setBool(array &$args, string $argKey, array $config, string $configKey): void
    {
        if (array_key_exists($configKey, $config) && is_bool($config[$configKey])) {
            $args[$argKey] = $config[$configKey];
        }
    }

    /**
     * @param array<string, mixed> $args
     * @param string $argKey
     * @param array<string, mixed> $config
     * @param string $configKey
     */
    private function setInt(array &$args, string $argKey, array $config, string $configKey): void
    {
        if (isset($config[$configKey]) && is_int($config[$configKey])) {
            $args[$argKey] = $config[$configKey];
        }
    }

    /**
     * @param array<string, mixed> $args
     * @param string $argKey
     * @param array<string, mixed> $config
     * @param string $configKey
     */
    private function setStringList(array &$args, string $argKey, array $config, string $configKey): void
    {
        if (!isset($config[$configKey]) || !is_array($config[$configKey]) || $config[$configKey] === []) {
            return;
        }
        $list = [];
        foreach ($config[$configKey] as $item) {
            if (!is_string($item)) {
                return;
            }
            $list[] = $item;
        }
        $args[$argKey] = $list;
    }
}
