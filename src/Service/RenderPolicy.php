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
     * @return array<string, mixed>
     */
    public static function options(array $config): array
    {
        foreach (['safe_mode', 'preset', 'on_disallowed', 'soft_break_mode', 'xhtml'] as $oldName) {
            if (array_key_exists($oldName, $config)) {
                throw new InvalidArgumentException(sprintf(
                    'Carve 0.1 option "%s" has no direct Carve2 mapping; update this profile.',
                    $oldName,
                ));
            }
        }
        $names = ['profile' => 'profile', 'mode' => 'mode', 'raw_html' => 'rawHtml',
            'url_policy' => 'urlPolicy', 'smart_typography' => 'smartTypography',
            'source_lines' => 'sourceLines', 'sections' => 'sections',
            'heading_ids' => 'headingIds', 'strict_losses' => 'strictLosses',
            'symbols' => 'symbols', 'labels' => 'labels',
            'social' => 'social'];
        $options = [];
        foreach ($names as $setting => $name) {
            if (array_key_exists($setting, $config)) {
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
