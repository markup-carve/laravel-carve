<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Service;

use Illuminate\Support\Str;
use InvalidArgumentException;
use MarkupCarve\Carve\Extension\ExtensionInterface;
use ReflectionClass;
use Throwable;

class ExtensionFactory
{
    /**
     * @var string
     */
    public const TYPE_ADMONITION = 'admonition';

    /**
     * @var string
     */
    public const TYPE_ASCII_HEADING_IDS = 'ascii_heading_ids';

    /**
     * @var string
     */
    public const TYPE_AUTOLINK = 'autolink';

    /**
     * @var string
     */
    public const TYPE_CITATIONS = 'citations';

    /**
     * @var string
     */
    public const TYPE_CODE_CALLOUTS = 'code_callouts';

    /**
     * @var string
     */
    public const TYPE_CODE_GROUP = 'code_group';

    /**
     * @var string
     */
    public const TYPE_COLOR_SWATCH = 'color_swatch';

    /**
     * @var string
     */
    public const TYPE_DEFAULT_ATTRIBUTES = 'default_attributes';

    /**
     * @var string
     */
    public const TYPE_DETAILS = 'details';

    /**
     * @var string
     */
    public const TYPE_EXTERNAL_LINKS = 'external_links';

    /**
     * @var string
     */
    public const TYPE_FENCED_RENDER = 'fenced_render';

    /**
     * @var string
     */
    public const TYPE_FRONTMATTER = 'frontmatter';

    /**
     * @var string
     */
    public const TYPE_GLOSSARY = 'glossary';

    /**
     * @var string
     */
    public const TYPE_HEADING_LEVEL_SHIFT = 'heading_level_shift';

    /**
     * @var string
     */
    public const TYPE_HEADING_NUMBERS = 'heading_numbers';

    /**
     * @var string
     */
    public const TYPE_HEADING_PERMALINKS = 'heading_permalinks';

    /**
     * @var string
     */
    public const TYPE_HEADING_REFERENCE = 'heading_reference';

    /**
     * @var string
     */
    public const TYPE_IMG_FENCE = 'img_fence';

    /**
     * @var string
     */
    public const TYPE_INDEX = 'index';

    /**
     * @var string
     */
    public const TYPE_INLINE_FOOTNOTES = 'inline_footnotes';

    /**
     * @var string
     */
    public const TYPE_LIST_TABLE = 'list_table';

    /**
     * @var string
     */
    public const TYPE_LOWERCASE_HEADING_IDS = 'lowercase_heading_ids';

    /**
     * @var string
     */
    public const TYPE_MATH_BLOCK = 'math_block';

    /**
     * @var string
     */
    public const TYPE_MENTIONS = 'mentions';

    /**
     * @var string
     */
    public const TYPE_MERMAID = 'mermaid';

    /**
     * @var string
     */
    public const TYPE_PLANTUML = 'plantuml';

    /**
     * @var string
     */
    public const TYPE_PLUS_BULLET = 'plus_bullet';

    /**
     * @var string
     */
    public const TYPE_SEMANTIC_SPAN = 'semantic_span';

    /**
     * @var string
     */
    public const TYPE_SMART_QUOTES = 'smart_quotes';

    /**
     * @var string
     */
    public const TYPE_SPOILER = 'spoiler';

    /**
     * @var string
     */
    public const TYPE_TAB_NORMALIZE = 'tab_normalize';

    /**
     * @var string
     */
    public const TYPE_TABLE_OF_CONTENTS = 'table_of_contents';

    /**
     * @var string
     */
    public const TYPE_TABS = 'tabs';

    /**
     * @var string
     */
    public const TYPE_TOC_PLACEMENT = 'toc_placement';

    /**
     * @var string
     */
    public const TYPE_WIKILINKS = 'wikilinks';

    /**
     * All supported extension type identifiers.
     *
     * @return array<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_ADMONITION,
            self::TYPE_ASCII_HEADING_IDS,
            self::TYPE_AUTOLINK,
            self::TYPE_CITATIONS,
            self::TYPE_CODE_CALLOUTS,
            self::TYPE_CODE_GROUP,
            self::TYPE_COLOR_SWATCH,
            self::TYPE_DEFAULT_ATTRIBUTES,
            self::TYPE_DETAILS,
            self::TYPE_EXTERNAL_LINKS,
            self::TYPE_FENCED_RENDER,
            self::TYPE_FRONTMATTER,
            self::TYPE_GLOSSARY,
            self::TYPE_HEADING_LEVEL_SHIFT,
            self::TYPE_HEADING_NUMBERS,
            self::TYPE_HEADING_PERMALINKS,
            self::TYPE_HEADING_REFERENCE,
            self::TYPE_IMG_FENCE,
            self::TYPE_INDEX,
            self::TYPE_INLINE_FOOTNOTES,
            self::TYPE_LIST_TABLE,
            self::TYPE_LOWERCASE_HEADING_IDS,
            self::TYPE_MATH_BLOCK,
            self::TYPE_MENTIONS,
            self::TYPE_MERMAID,
            self::TYPE_PLANTUML,
            self::TYPE_PLUS_BULLET,
            self::TYPE_SEMANTIC_SPAN,
            self::TYPE_SMART_QUOTES,
            self::TYPE_SPOILER,
            self::TYPE_TAB_NORMALIZE,
            self::TYPE_TABLE_OF_CONTENTS,
            self::TYPE_TABS,
            self::TYPE_TOC_PLACEMENT,
            self::TYPE_WIKILINKS,
        ];
    }

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
    public function create(ExtensionInterface|array|string $config): ?ExtensionInterface
    {
        if ($config instanceof ExtensionInterface) {
            return $config;
        }
        if (is_string($config)) {
            $config = ['type' => $config];
        }
        $type = $config['type'] ?? null;
        if (!is_string($type)) {
            return null;
        }
        unset($config['type']);
        if ($type === 'mermaid' || $type === 'plantuml') {
            $config += ['language' => $type === 'mermaid' ? 'mermaid' : ['plantuml', 'puml']];
            $type = 'fenced_render';
        }
        if ($type === 'fenced_render') {
            $config += ['language' => 'mermaid'];
        }
        $class = is_a($type, ExtensionInterface::class, true)
            ? $type
            : 'MarkupCarve\\Carve\\Extension\\' . Str::studly($type) . 'Extension';
        if (!is_a($class, ExtensionInterface::class, true)) {
            return null;
        }
        if ($type === 'wikilinks' && isset($config['url_template']) && is_string($config['url_template'])) {
            $config['url_generator'] = (new WikiLinkUrlTemplate($config['url_template']))(...);
            unset($config['url_template']);
        }
        $aliases = match ($type) {
            'heading_permalinks' => ['class' => 'css_class'],
            'table_of_contents' => ['toc_class' => 'css_class'],
            'wikilinks' => ['link_class' => 'css_class'],
            'mentions' => ['user_url_template' => 'mention_url', 'user_class' => 'mention_class'],
            default => [],
        };
        $arguments = [];
        foreach ($config as $key => $value) {
            $arguments[Str::camel($aliases[$key] ?? $key)] = $value;
        }
        $constructor = (new ReflectionClass($class))->getConstructor();
        $parameters = $constructor === null ? [] : array_map(static fn ($parameter): string => $parameter->getName(), $constructor->getParameters());
        foreach (array_keys($arguments) as $argument) {
            if (!in_array($argument, $parameters, true)) {
                throw new InvalidArgumentException(sprintf('Unknown option "%s" for Carve extension "%s".', $argument, $type));
            }
        }
        try {
            return new $class(...$arguments);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(sprintf('Invalid options for Carve extension "%s": %s', $type, $exception->getMessage()), 0, $exception);
        }
    }
}
