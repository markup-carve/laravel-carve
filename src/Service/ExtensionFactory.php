<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Service;

use InvalidArgumentException;
use MarkupCarve\Carve2\Extension\ExtensionDefinition;
use MarkupCarve\Carve2\Extension\ExtensionProvider;
use MarkupCarve\Carve2\Extension\Tabs;

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
            return array_values(Tabs::extensions(true));
        }

        throw new InvalidArgumentException(sprintf(
            'Carve 0.2 extension "%s" is not available yet.',
            is_string($type) ? $type : get_debug_type($type),
        ));
    }
}
