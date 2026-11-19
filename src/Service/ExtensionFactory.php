<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Service;

use InvalidArgumentException;
use MarkupCarve\Carve2\Extension\Claim;
use MarkupCarve\Carve2\Extension\ExtensionDefinition;
use MarkupCarve\Carve2\Extension\ExtensionProvider;
use MarkupCarve\Carve2\Extension\RenderHost;
use MarkupCarve\Carve2\Extension\Tabs;
use MarkupCarve\Carve2\Node;

class ExtensionFactory
{
    /**
     * @param \MarkupCarve\Carve2\Extension\ExtensionDefinition|\MarkupCarve\Carve2\Extension\ExtensionProvider|array<mixed, mixed>|string $config
     *
     * @throws \InvalidArgumentException
     *
     * @return list<\MarkupCarve\Carve2\Extension\ExtensionDefinition|\MarkupCarve\Carve2\Extension\ExtensionProvider>
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
