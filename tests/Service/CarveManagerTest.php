<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests\Service;

use InvalidArgumentException;
use MarkupCarve\LaravelCarve\Service\CarveConverter;
use MarkupCarve\LaravelCarve\Service\CarveManager;
use PHPUnit\Framework\TestCase;

class CarveManagerTest extends TestCase
{
    private CarveManager $manager;

    protected function setUp(): void
    {
        $this->manager = new CarveManager([
            'default' => new CarveConverter(),
            'trusted' => new CarveConverter(safeMode: false),
        ]);
    }

    public function testToHtml(): void
    {
        $html = $this->manager->toHtml('Hello *world*!');

        $this->assertStringContainsString('<strong>world</strong>', $html);
    }

    public function testToHtmlWithNamedConverter(): void
    {
        $html = $this->manager->toHtml('Hello *world*!', 'trusted');

        $this->assertStringContainsString('<strong>world</strong>', $html);
    }

    public function testToHtmlRawBypassesSafeMode(): void
    {
        $html = $this->manager->toHtmlRaw('[Click](javascript:alert(1))');

        // normative scheme denylist (spec SS25): blanked even in raw mode
        $this->assertStringContainsString('href=""', $html);
    }

    public function testToText(): void
    {
        $text = $this->manager->toText('Hello *world*!');

        $this->assertStringContainsString('Hello world!', $text);
    }

    public function testConverterAccessor(): void
    {
        $this->assertSame('default', array_search($this->manager->converter('default'), $this->manager->getConverters(), true));
    }

    public function testUnknownConverterThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Carve converter "missing" not found');

        $this->manager->toHtml('test', 'missing');
    }
}
