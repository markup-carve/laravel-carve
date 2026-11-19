<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests;

use Illuminate\Support\Facades\Blade;

class BladeDirectiveTest extends TestCase
{
    public function testCarveDirectiveRendersHtml(): void
    {
        $compiled = Blade::compileString('@carve($content)');
        $content = 'Hello *world*!';

        ob_start();
        eval('?>' . $compiled);
        /** @var string $output */
        $output = ob_get_clean();

        $this->assertStringContainsString('<strong>world</strong>', $output);
    }

    public function testCarveDirectiveUsesSafeMode(): void
    {
        $compiled = Blade::compileString('@carve($content)');
        $content = '[Click](javascript:alert(1))';

        ob_start();
        eval('?>' . $compiled);
        /** @var string $output */
        $output = ob_get_clean();

        $this->assertStringNotContainsString('javascript:', $output);
    }

    public function testCarveRawDirectiveBypassesSafeMode(): void
    {
        $compiled = Blade::compileString('@carveRaw($content)');
        $content = '[Click](javascript:alert(1))';

        ob_start();
        eval('?>' . $compiled);
        /** @var string $output */
        $output = ob_get_clean();

        // Carve's URL scheme denylist is normative (spec SS25): the raw
        // profile skips safe-mode sanitization, but a javascript: destination
        // still renders as an empty href.
        $this->assertStringContainsString('href=""', $output);
    }

    public function testCarveTextDirectiveOutputsPlainText(): void
    {
        self::markTestSkipped('Carve2 text writer is pending carve-php issue 5235 (C2-T-005).');
    }
}
