<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests\Service;

use InvalidArgumentException;
use MarkupCarve\Carve\Lint\LintWarning;
use MarkupCarve\LaravelCarve\Service\CarveConverter;
use MarkupCarve\LaravelCarve\Service\CarveManager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

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

    /**
     * Every rule here comes from a linter carve-php added after 0.1.9. The list
     * in CarveManager::lint() was complete against 0.1.9 and silently stopped
     * being complete; without this test the next engine release does the same.
     */
    public function testLintReportsTheRulesTheEngineAddedAfterTheOriginalLinterList(): void
    {
        $manager = $this->manager;
        $rules = static fn (string $source): array => array_map(
            static fn (LintWarning $warning): string => $warning->rule,
            $manager->lint($source),
        );

        self::assertContains('unresolved-reference-link', $rules("![alt][missing]\n"));
        self::assertContains('broken-fragment-link', $rules("# Alpha\n\nSee [a](#nope).\n"));
        self::assertContains('unattached-block-attribute', $rules("{.a}\n\n"));
        self::assertContains('blockquote-marker-without-space', $rules(">no space\n"));
    }

    /**
     * The list in CarveManager::lint() is hand-written, so an engine release
     * that adds a linter drops its rules without any test going red. 0.1.11
     * added four and the list still named the seven that 0.1.9 shipped.
     */
    public function testLintCallsEveryLinterTheInstalledEngineShips(): void
    {
        $reflection = new ReflectionClass(CarveManager::class);
        $file = $reflection->getFileName();
        self::assertIsString($file);
        $body = (string)file_get_contents($file);

        $engineLintDir = dirname((string)(new ReflectionClass(LintWarning::class))->getFileName());
        $shipped = [];
        foreach ((array)glob($engineLintDir . '/*Linter.php') as $path) {
            $shipped[] = basename((string)$path, '.php');
        }
        self::assertNotEmpty($shipped);

        $missing = array_values(array_filter(
            $shipped,
            static fn (string $class): bool => !str_contains($body, 'new ' . $class . '()'),
        ));
        self::assertSame([], $missing, 'CarveManager::lint() does not call: ' . implode(', ', $missing));
    }
}
