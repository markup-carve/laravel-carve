<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests\Service;

use InvalidArgumentException;
use MarkupCarve\Carve\Lint\DocumentLinter;
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

    public function testLintReportsTheRulesCarvePhp0114Added(): void
    {
        $manager = $this->manager;
        $rules = static fn (string $source): array => array_map(
            static fn (LintWarning $warning): string => $warning->rule,
            $manager->lint($source),
        );

        self::assertContains('list-marker-below-content-column', $rules("9. a\n   - b\n10. c\n   - d\n"));
        self::assertContains('description-separator-below-content-column', $rules("- a\n  :: term\n: detail\n"));
        self::assertContains('consumed-key-near-miss', $rules("{lose}\n- a\n- b\n"));
        self::assertContains('unclosed-code-fence', $rules("```js\nx\n"));
    }

    /**
     * CarveManager::lint() delegates to the engine's DocumentLinter. Guard that
     * the aggregate still covers every linter the installed engine ships, so a
     * new linter missing from it shows up here instead of being skipped silently.
     */
    public function testLintCoversEveryLinterTheInstalledEngineShips(): void
    {
        $manager = (string)file_get_contents((string)(new ReflectionClass(CarveManager::class))->getFileName());
        self::assertStringContainsString('new DocumentLinter()', $manager);

        $aggregate = (string)file_get_contents((string)(new ReflectionClass(DocumentLinter::class))->getFileName());
        $engineLintDir = dirname((string)(new ReflectionClass(LintWarning::class))->getFileName());
        $shipped = [];
        foreach ((array)glob($engineLintDir . '/*Linter.php') as $path) {
            $shipped[] = basename((string)$path, '.php');
        }
        self::assertNotEmpty($shipped);

        // IncludeLinter wraps DocumentLinter and needs an include resolver.
        $missing = array_values(array_filter(
            $shipped,
            static fn (string $class): bool => !in_array($class, ['DocumentLinter', 'IncludeLinter'], true)
                && !str_contains($aggregate, 'new ' . $class . '()'),
        ));
        self::assertSame([], $missing, 'DocumentLinter does not call: ' . implode(', ', $missing));
    }
}
