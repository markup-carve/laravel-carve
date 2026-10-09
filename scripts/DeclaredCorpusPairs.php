<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Scripts;

/**
 * How many corpus pairs the spec's example pages DECLARE.
 *
 * This lives in a file rather than inside the engine-drift workflow so it can
 * be tested. The counter it replaces was a copy of this logic embedded in a
 * YAML `run:` block, which no test could reach, and it counted one pair per
 * `::: compare` block for years while the spec's generator was already writing
 * one pair per `carve` fence (carve#2824).
 */
final class DeclaredCorpusPairs
{
    /**
     * Port of the spec's scripts/lib/example-pair-census.mjs.
     *
     * Nothing inside an open fence is markup, so a four-backtick example
     * holding a three-backtick `carve` line declares no pair of its own.
     *
     * @param array<int, string> $lines source lines of one example page
     *
     * @return int one pair per `carve` fence inside a `::: compare` block
     */
    public static function count(array $lines): int
    {
        $pairs = 0;
        $marker = null;
        $fence = null;

        foreach ($lines as $line) {
            if ($fence !== null) {
                if (str_starts_with($line, $fence) && trim(substr($line, strlen($fence))) === '') {
                    $fence = null;
                }

                continue;
            }

            $ticks = strspn($line, '`');
            if ($ticks >= 3) {
                $fence = substr($line, 0, $ticks);
                if ($marker !== null && trim(substr($line, $ticks)) === 'carve') {
                    $pairs++;
                }

                continue;
            }

            $trimmed = trim($line);
            $colons = strspn($trimmed, ':');
            if ($colons < 3) {
                continue;
            }

            if ($marker === null) {
                if (preg_match('/^[ \t]+compare(?:[ \t]|$)/', substr($trimmed, $colons)) === 1) {
                    $marker = substr($trimmed, 0, $colons);
                }

                continue;
            }

            if ($trimmed === $marker) {
                $marker = null;
            }
        }

        return $pairs;
    }
}
