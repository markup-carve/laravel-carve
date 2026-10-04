<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Service;

use InvalidArgumentException;
use MarkupCarve\Carve\Profile;
use MarkupCarve\Carve\SafeMode;

final class RenderPolicy
{
    public static function safeMode(mixed $value): SafeMode|bool
    {
        return match ($value) {
            'strict' => SafeMode::strict(),
            true, 'true', 1, '1' => true,
            false, 'false', 0, '0' => false,
            default => $value instanceof SafeMode
                ? $value
                : throw new InvalidArgumentException('carve safe_mode must be true, false or "strict".'),
        };
    }

    public static function preset(?string $name, ?string $onDisallowed = null): ?Profile
    {
        if ($name === null) {
            return null;
        }
        if (!in_array($name, ['full', 'article', 'comment', 'minimal'], true)) {
            throw new InvalidArgumentException(sprintf('Unknown Carve preset "%s".', $name));
        }
        $profile = Profile::{$name}();

        return $onDisallowed === null ? $profile : $profile->onDisallowed($onDisallowed);
    }
}
