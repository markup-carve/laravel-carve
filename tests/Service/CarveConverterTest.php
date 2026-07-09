<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests\Service;

use MarkupCarve\Carve\Node\Document;
use MarkupCarve\LaravelCarve\Service\CarveConverter;
use PHPUnit\Framework\TestCase;

class CarveConverterTest extends TestCase
{
    public function testToHtml(): void
    {
        $converter = new CarveConverter();

        $html = $converter->toHtml('Hello *world*!');

        $this->assertStringContainsString('<strong>world</strong>', $html);
    }

    public function testToHtmlWithEmphasis(): void
    {
        $converter = new CarveConverter();

        $html = $converter->toHtml('Hello /world/!');

        $this->assertStringContainsString('<em>world</em>', $html);
    }

    public function testToText(): void
    {
        $converter = new CarveConverter();

        $text = $converter->toText('Hello *world*!');

        $this->assertStringContainsString('Hello world!', $text);
    }

    public function testSafeModeEnabledByDefault(): void
    {
        $converter = new CarveConverter();

        $html = $converter->toHtml('[Click me](javascript:alert("xss"))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function testSafeModeDisabled(): void
    {
        $converter = new CarveConverter(safeMode: false);

        $html = $converter->toHtml('[Click me](javascript:alert(1))');

        // Carve's URL scheme denylist is normative (spec SS25): even with
        // safe mode off, a javascript: destination renders as an empty href.
        $this->assertStringContainsString('href=""', $html);
    }

    public function testParseReturnsDocument(): void
    {
        $converter = new CarveConverter();

        $document = $converter->parse('# Heading');

        $this->assertInstanceOf(Document::class, $document);
    }
}
