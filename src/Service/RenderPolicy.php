<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Service;

use InvalidArgumentException;

final class RenderPolicy
{
    public static function preset(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }
        if (!in_array($name, ['full', 'article', 'comment', 'minimal'], true)) {
            throw new InvalidArgumentException(sprintf('Unknown Carve profile "%s".', $name));
        }

        return $name;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    public static function options(array $config): array
    {
        foreach (['safe_mode', 'preset', 'on_disallowed', 'xhtml'] as $oldName) {
            if (array_key_exists($oldName, $config)) {
                throw new InvalidArgumentException(sprintf(
                    'Carve 0.1 option "%s" has no direct Carve mapping; update this profile.',
                    $oldName,
                ));
            }
        }
        $names = [

            'profile' => 'profile',
            'mode' => 'mode',
            'raw_html' => 'rawHtml',
            'url_policy' => 'urlPolicy',
            'smart_typography' => 'smartTypography',
            'source_lines' => 'sourceLines',
            'sections' => 'sections',
            'heading_ids' => 'headingIds',
            'heading_shift' => 'headingShift',
            'strict_losses' => 'strictLosses',
            'symbols' => 'symbols',
            'labels' => 'labels',
            'social' => 'social',
            'soft_break_mode' => 'softBreak',
        ];
        foreach (array_keys($config) as $key) {
            if (
                !array_key_exists($key, $names)
                && !in_array($key, ['extensions', 'writers', 'cache_version', 'include_resolver'], true)
            ) {
                throw new InvalidArgumentException(sprintf('Unknown Carve profile setting "%s".', $key));
            }
        }
        $options = [];
        foreach ($names as $setting => $name) {
            if (array_key_exists($setting, $config) && !($setting === 'soft_break_mode' && $config[$setting] === null)) {
                $options[$name] = $config[$setting];
            }
        }
        if (isset($options['profile'])) {
            if (!is_string($options['profile'])) {
                throw new InvalidArgumentException('carve profile must be a string.');
            }
            self::preset($options['profile']);
        }

        return $options;
    }
}
