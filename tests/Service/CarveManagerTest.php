<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests\Service;

use InvalidArgumentException;
use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\Diagnostics\Diagnostic;
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
            'trusted' => new CarveConverter(options: ['rawHtml' => true]), // C2-M-066
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
        self::assertSame("Hello world!\n", $this->manager->toText('Hello *world*!'));
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
            static fn (Diagnostic $warning): string => $warning->code,
            $manager->lint($source),
        );

        self::assertContains('unresolved-reference-link', $rules("![alt][missing]\n"));
        self::assertContains('broken-fragment-link', $rules("# Alpha\n\nSee [a](#nope).\n"));
        self::assertContains('unattached-block-attribute', $rules("{.a}\n\n"));
        self::assertContains('blockquote-marker-without-space', $rules(">no space\n"));
    }

    public function testFormatReturnsCanonicalSourceAndAlignsTablesOnRequest(): void
    {
        $table = "|= a |= bb |\n| ccc | d |\n";

        // C2-W-100: the 0.2 writer uses its canonical heading marker.
        self::assertSame("= T\n\ntext\n", $this->manager->format("# T\n\ntext  \n"));
        self::assertSame($table, $this->manager->format($table));
        self::assertSame("|= a  |= bb |\n| ccc | d   |\n", $this->manager->format($table, true));
    }

    public function testFormatRewritesRetiredSpellingsToTheCanonicalOnes(): void
    {
        // U-055: the former GFM separator is a data row in 0.2.
        self::assertSame("| a | bb |\n| --- | --- |\n| ccc | d |\n", $this->manager->format("| a | bb |\n|---|---|\n| ccc | d |\n"));
        // U-056: glued alignment marks are plain cell text in 0.2.
        self::assertSame("|= < a |= > bb |\n| ccc | d |\n", $this->manager->format("|=< a |=> bb |\n| ccc | d |\n"));
        // U-044: a hyphen run is typography, not a thematic break.
        self::assertSame("a\n\n---\n\nb\n", $this->manager->format("a\n\n---\n\nb\n"));
    }

    public function testLintReportsRetiredSpellingsAndHonorsSuppressionComments(): void
    {
        $manager = $this->manager;
        $rules = static fn (string $source): array => array_map(
            static fn (Diagnostic $warning): string => $warning->code,
            $manager->lint($source),
        );

        self::assertSame(['deprecated-cell-alignment'], $rules("|=< a |= b |\n| 1 | 2 |\n")); // U-056
        self::assertSame([], $rules("|={<} a |= b |\n| 1 | 2 |\n"));
        self::assertSame(['thematic-break-spelling'], $rules("a\n\n---\n\nb\n"));
        self::assertSame([], $rules("a\n\n%% carve-lint-disable-next-line thematic-break-spelling\n---\n\nb\n"));
    }

    public function testBracedCellAlignmentRenders(): void
    {
        $html = $this->manager->toHtml("|={<} a |={>} b |\n| 1 | 2 |\n");

        self::assertStringContainsString('<th scope="col" style="text-align: left;">a</th>', $html);
        self::assertStringContainsString('<td style="text-align: right;">2</td>', $html);
    }

    public function testFromMarkdownTurnsGitHubAlertsIntoAdmonitions(): void
    {
        self::assertSame("= Title\n", $this->manager->fromMarkdown("# Title\n"));
    }

    public function testFromMarkdownWithTheGfmFlavorMapsAnAlertToAnAdmonition(): void
    {
        $alert = "> [!TIP]\n> A **bold** reminder.\n";

        self::assertSame("::: tip\nA *bold* reminder.\n:::\n", $this->manager->fromMarkdown($alert, 'gfm'));
        self::assertStringStartsWith('> [!TIP]', $this->manager->fromMarkdown($alert));
    }

    public function testFromMarkdownRejectsAnUnknownFlavor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->fromMarkdown('text', 'djot');
    }

    public function testLintReportsTheRulesCarvePhp0114Added(): void
    {
        $manager = $this->manager;
        $rules = static fn (string $source): array => array_map(
            static fn (Diagnostic $warning): string => $warning->code,
            $manager->lint($source),
        );

        self::assertContains('list-item-body-below-content-column', $rules("9. a\n   - b\n10. c\n   - d\n"));
        self::assertContains('description-separator-below-content-column', $rules("::: box\n   :: t\n: d\n:::\n"));
        // C2-W-103: obsolete 0.1 rule is absent from the 0.2 catalog.
        self::assertNotContains('consumed-key-near-miss', $rules("{lose}\n- a\n- b\n"));
        self::assertContains('unclosed-code-fence', $rules("```js\nx\n"));
    }

    public function testLintUsesTheCarveRuleCatalog(): void
    {
        // C2-W-103: the public lint call owns the complete 0.2 catalog.
        $source = "![alt][missing]\n";
        self::assertEquals(Carve::lint($source), $this->manager->lint($source));
    }
}
