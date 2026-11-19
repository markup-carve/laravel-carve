<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use MarkupCarve\Carve2\Carve2;
use MarkupCarve\Carve2\Node;
use MarkupCarve\Carve2\Output\OutputWriter;
use MarkupCarve\LaravelCarve\Facades\Carve;
use MarkupCarve\LaravelCarve\Rules\ValidCarve;
use MarkupCarve\LaravelCarve\Service\CarveConverter;
use MarkupCarve\LaravelCarve\Service\CarveManager;

class Carve2PortTest extends TestCase
{
    public function testHtmlParseAndMetadataUseCarve2(): void
    {
        $source = "---json\n{\"title\":\"Page\"}\n---\n\n# Page\n";
        $result = Carve::render($source);
        self::assertSame('Page', $result->meta('title'));
        self::assertSame('Page', $result->toc()[0]['text']);
        self::assertSame(Carve2::render($source), $result->html);
        self::assertInstanceOf(Node::class, Carve::parse($source));
    }

    public function testSafeDefaultAndTrustedRawHtmlKeepUrlPolicy(): void
    {
        $source = 'Before ' . chr(96) . '<b>raw</b>' . chr(96) . '{=html} [bad](javascript:alert(1))';
        self::assertStringNotContainsString('<b>raw</b>', Carve::toHtml($source));
        $raw = Carve::toHtmlRaw($source);
        self::assertStringContainsString('<b>raw</b>', $raw);
        self::assertStringNotContainsString('href="javascript:', $raw);
    }

    public function testFileIncludesAndDependencyReport(): void
    {
        $dir = sys_get_temp_dir() . '/laravel-carve2-' . bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir . '/main.crv', "{{ child.crv }}\n");
        file_put_contents($dir . '/child.crv', "*included*\n");
        try {
            $converter = new CarveConverter(includeRoot: $dir, cache: new Repository(new ArrayStore()));
            self::assertStringContainsString('child.crv', $converter->toHtml('{{ child.crv }}'));
            self::assertStringNotContainsString('included', $converter->toHtml('{{ child.crv }}'));
            $rendered = $converter->renderFile($dir . '/main.crv');
            self::assertStringContainsString('<strong>included</strong>', $rendered->html);
            self::assertSame([['path' => 'child.crv', 'resolved' => true]], $rendered->dependencies);
            file_put_contents($dir . '/child.crv', '*changed*' . "\n");
            self::assertStringContainsString(
                '<strong>changed</strong>',
                $converter->renderFile($dir . '/main.crv')->html,
            );
        } finally {
            unlink($dir . '/main.crv');
            unlink($dir . '/child.crv');
            rmdir($dir);
        }
    }

    public function testLintHtmlImportAndFormatUseCarve2(): void
    {
        self::assertSame(Carve2::lint('**old**')[0]->code, Carve::lint('**old**')[0]->code);
        self::assertSame(
            Carve2::importHtml('<p><em>italic</em></p>')->source,
            Carve::fromHtml('<p><em>italic</em></p>'),
        );
        self::assertSame(Carve2::format("a  \nb\n"), Carve::format("a  \nb\n"));
    }

    public function testValidationUsesCarve2ProfileAndLint(): void
    {
        self::assertFalse(Validator::make(
            ['body' => '# Heading'],
            ['body' => ValidCarve::preset('comment')],
        )->passes());
        self::assertFalse(Validator::make(
            ['body' => '**old**'],
            ['body' => (new ValidCarve())->lint()],
        )->passes());
    }

    public function testWriterRegistryCanProvideAHostTarget(): void
    {
        $writer = new class implements OutputWriter {
            public function target(): string
            {
                return 'text';
            }

            public function write(Node $tree, array $options = []): string
            {
                return 'from v2 tree';
            }
        };
        $converter = new CarveConverter(writers: [$writer]);
        self::assertSame('from v2 tree', $converter->toText('hello'));
        $manager = new CarveManager(['default' => $converter]);
        self::assertSame('from v2 tree', $manager->toText('hello'));
    }

    public function testTabsRegistrationUsesCarve2Definitions(): void
    {
        config()->set('carve.converters.default.extensions', ['tabs']);
        app()->forgetInstance(CarveManager::class);
        Carve::clearResolvedInstances();
        $source = ":::: tabs\n::: tab \"One\"\nx\n:::\n::::\n";
        self::assertStringContainsString('class="tabs"', Carve::toHtml($source));
        self::assertStringContainsString('class="tab-panel"', Carve::toHtml($source));
    }

    public function testLegacySecurityOptionFailsExplicitly(): void
    {
        config()->set('carve.converters.default.safe_mode', false);
        app()->forgetInstance(CarveManager::class);
        Carve::clearResolvedInstances();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Carve 0.1 option "safe_mode"');
        Carve::toHtml('text');
    }

    public function testUnknownProfileSettingFailsExplicitly(): void
    {
        config()->set('carve.converters.default.souce_lines', true);
        app()->forgetInstance(CarveManager::class);
        Carve::clearResolvedInstances();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Carve2 profile setting "souce_lines".');
        Carve::toHtml('text');
    }

    public function testUnavailableWriterHasClearError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Carve markdown writer not available yet.');
        Carve::toMarkdown('hello');
    }

    public function testMarkdownTextAndAnsiWritersPendingEngineRegistration(): void
    {
        self::markTestSkipped('Carve2 markdown, text and ansi writers are pending carve-php issue 5235.');
    }

    public function testDocumentTransformExtensionPendingEngineApi(): void
    {
        self::markTestSkipped('Carve2 document transform extension hook is pending the D4 engine API.');
    }
}
