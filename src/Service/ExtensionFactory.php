<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Service;

use InvalidArgumentException;
use MarkupCarve\Carve\Extension\Claim;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\Carve\Extension\ExtensionProvider;
use MarkupCarve\Carve\Extension\Navigation;
use MarkupCarve\Carve\Extension\RenderHost;
use MarkupCarve\Carve\Extension\Tabs;
use MarkupCarve\Carve\Node;

class ExtensionFactory
{
    /**
     * @param \MarkupCarve\Carve\Extension\ExtensionDefinition|\MarkupCarve\Carve\Extension\ExtensionProvider|array<mixed, mixed>|string $config
     *
     * @throws \InvalidArgumentException
     *
     * @return list<\MarkupCarve\Carve\Extension\ExtensionDefinition|\MarkupCarve\Carve\Extension\ExtensionProvider>
     */
    public function create(ExtensionDefinition|ExtensionProvider|array|string $config): array
    {
        if ($config instanceof ExtensionDefinition || $config instanceof ExtensionProvider) {
            return [$config];
        }
        if (is_string($config)) {
            $config = ['type' => $config];
        }
        $type = $config['type'] ?? null;
        if ($type === 'tabs') {
            self::acceptKeys($config, ['type']);

            return array_values(Tabs::extensions(true));
        }
        if ($type === 'plantuml') {
            self::acceptKeys($config, ['type', 'css_class']);
            $class = $config['css_class'] ?? 'plantuml';
            if (!is_string($class) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $class)) {
                throw new InvalidArgumentException('Carve plantuml css_class must be one CSS class name.');
            }

            return [self::plantuml('plantuml', $class), self::plantuml('puml', $class)];
        }
        if ($type === 'external_links') {
            self::acceptKeys($config, ['type', 'rel', 'target']);
            $target = $config['target'] ?? '_blank';
            $rel = $config['rel'] ?? 'noopener noreferrer';
            if (!is_string($target) || !is_string($rel)) {
                throw new InvalidArgumentException('External link target and rel must be strings.');
            }

            return [Navigation::externalLinks($target, $rel)];
        }
        if ($type === 'heading_levels') {
            self::acceptKeys($config, ['type', 'offset']);
            $offset = $config['offset'] ?? 0;
            if (!is_int($offset)) {
                throw new InvalidArgumentException('Heading level offset must be an integer.');
            }

            return [Navigation::headingLevelShift($offset)];
        }

        throw new InvalidArgumentException(sprintf(
            'Carve 0.2 extension "%s" is not available yet.',
            is_string($type) ? $type : get_debug_type($type),
        ));
    }

    /**
     * @param array<mixed, mixed> $config
     * @param list<string> $accepted
     *
     * @throws \InvalidArgumentException
     */
    private static function acceptKeys(array $config, array $accepted): void
    {
        foreach (array_keys($config) as $key) {
            if (!is_string($key) || !in_array($key, $accepted, true)) {
                throw new InvalidArgumentException(sprintf('Unknown Carve extension option "%s".', (string)$key));
            }
        }
    }

    private static function plantuml(string $language, string $class): ExtensionDefinition
    {
        return new ExtensionDefinition(
            'laravel.plantuml.' . $language,
            '1',
            [],
            matcher: ['form' => 'fence', 'name' => $language],
            transform: static function (Claim $claim): Node {
                $node = new Node('block_extension', []);
                $node->f = [
                    'name' => 'laravel.plantuml.' . $claim->name,
                    'version' => '1',
                    'slots' => [],
                    'props' => ['body' => $claim->source['body'] ?? ''],
                    'fallback' => [$claim->copyNode()],
                ];

                return $node;
            },
            renderer: static function (Node $node, RenderHost $host) use ($class): string {
                $props = $node->f['props'] ?? [];
                $body = is_array($props) ? ($props['body'] ?? '') : '';

                return '<pre class="' . RenderHost::escapeAttribute($class) . '">'
                    . RenderHost::escapeText(is_string($body) ? $body : '') . '</pre>';
            },
        );
    }
}
