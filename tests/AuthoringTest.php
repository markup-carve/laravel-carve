<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\Factory;
use InvalidArgumentException;
use MarkupCarve\Carve\Extension\HeadingLevelShiftExtension;
use MarkupCarve\LaravelCarve\Casts\AsCarve;
use MarkupCarve\LaravelCarve\Facades\Carve;
use MarkupCarve\LaravelCarve\RenderedCarve;
use MarkupCarve\LaravelCarve\Rules\ValidCarve;
use MarkupCarve\LaravelCarve\Service\CarveManager;
use MarkupCarve\LaravelCarve\View\Components\Carve as CarveComponent;

class AuthoringTest extends TestCase
{
    public function testRichResultMetadataAndSerialization(): void
    {
        $result = Carve::render("---json\n{\"title\":\"Page\",\"tags\":[\"docs\"]}\n---\n\n# Page\n\n## Install");
        self::assertSame('Page', $result->meta('title'));
        self::assertSame('docs', $result->meta('tags.0'));
        self::assertCount(2, $result->toc());
        self::assertSame('fallback', $result->meta('missing', 'fallback'));
        self::assertEquals($result, RenderedCarve::fromArray($result->toArray()));
        self::assertStringContainsString('<h1>Page</h1>', Blade::render('{{ $page }}', ['page' => $result]));
        self::assertSame($result->toArray(), json_decode((string)json_encode($result), true));
        $next = Carve::render('No headings');
        self::assertSame([], $next->toc());
        self::assertFalse($next->hasFrontmatter());
    }

    public function testStrictSafeModeAndPresetApplyToAllFormats(): void
    {
        $source = "# Heading\n\n```=html\n<script>alert(1)</script>\n```\n\n[x]{style=\"color:red\"}\n\n![alt](x.png)";
        self::assertStringNotContainsString('<h1', Carve::toHtml($source, 'comment'));
        self::assertStringNotContainsString('style=', Carve::toHtml($source, 'comment'));
        self::assertStringNotContainsString('<script>', Carve::toHtml($source, 'comment'));
        self::assertStringNotContainsString('x.png', Carve::toText($source, 'comment'));
        self::assertStringNotContainsString('x.png', Carve::toMarkdown($source, 'comment'));
    }

    public function testValidationReportsPresetsLintWarningsAndUnicodeLimits(): void
    {
        self::assertFalse(Validator::make(['body' => '# Heading'], ['body' => ValidCarve::preset('comment')])->passes());
        self::assertTrue(Validator::make(['body' => '*fine*'], ['body' => ValidCarve::preset('comment')])->passes());
        self::assertFalse(Validator::make(['body' => '**bold**'], ['body' => (new ValidCarve())->lint()])->passes());
        self::assertTrue(Validator::make(['body' => 'ção'], ['body' => (new ValidCarve())->maxLength(3)])->passes());
        self::assertFalse(Validator::make(['body' => 'ações'], ['body' => (new ValidCarve())->maxLength(3)])->passes());
        self::assertFalse(Validator::make(['body' => '[x][missing]'], ['body' => new ValidCarve(strict: true)])->passes());
        $validator = Validator::make(['body' => 123], ['body' => new ValidCarve(message: 'Custom: {error}')]);
        self::assertSame('Custom: value must be a string', $validator->errors()->first('body'));
    }

    public function testPresetLengthLimitFailsValidation(): void
    {
        self::assertFalse(Validator::make(['body' => str_repeat('a', 100001)], ['body' => ValidCarve::preset('comment')])->passes());
    }

    public function testRenderedComponentPreservesProfileUnlessExplicitlyOverridden(): void
    {
        $source = Carve::render('# Heading', 'comment');
        self::assertStringNotContainsString('<h1', Blade::render('<x-carve :source="$source" />', ['source' => $source]));
        self::assertStringContainsString('<h1', Blade::render('<x-carve :source="$source" profile="default" />', ['source' => $source]));
    }

    public function testValidationStateDoesNotLeakBetweenValues(): void
    {
        $rule = (new ValidCarve())->strict();
        self::assertFalse(Validator::make(['body' => '[x][missing]'], ['body' => $rule])->passes());
        self::assertTrue(Validator::make(['body' => 'fine'], ['body' => $rule])->passes());
    }

    public function testImportsAndStringMacros(): void
    {
        self::assertStringContainsString('*bold*', Carve::fromMarkdown('**bold**'));
        self::assertStringContainsString('/italic/', Carve::fromHtml('<p><em>italic</em></p>'));
        self::assertStringContainsString('<strong>bold</strong>', Str::carve('*bold*'));
        self::assertSame('bold', trim((string)str('*bold*')->carveText()));
        $findings = Carve::lint("fine\n\n**bold**");
        self::assertNotEmpty($findings);
        self::assertSame(3, $findings[0]->line);
    }

    public function testBladeComponentEscapesSourceAndAcceptsRenderedValues(): void
    {
        $html = Blade::render('<x-carve :source="$source" class="body" />', ['source' => '*bold*']);
        self::assertStringContainsString('class="body"', $html);
        self::assertStringContainsString('<strong>bold</strong>', $html);
        self::assertStringNotContainsString('&lt;strong&gt;', Blade::render('<x-carve :source="$source" />', ['source' => Carve::render('*bold*')]));
        $html = Blade::render('<x-carve>Hello {{ $name }}</x-carve>', ['name' => '<script>x</script>']);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('&amp;lt;', $html);
        $html = Blade::render('<x-carve :source="$source" />', ['source' => '@php echo "EXECUTED"; @endphp']);
        self::assertStringNotContainsString('<p>EXECUTED</p>', $html);
        self::assertStringContainsString('@php', $html);
        self::assertSame('*bold*', CarveComponent::dedent("\n    *bold*\n"));
    }

    public function testCastStoresSourceAndAppliesSelectedProfile(): void
    {
        $model = new class extends Model {
            /**
             * @var array<string, string>
             */
            protected $casts = ['body' => AsCarve::class, 'comment' => AsCarve::class . ':comment'];
        };
        $model->setAttribute('body', Carve::render('*bold*'));
        $model->setAttribute('comment', '# Heading');
        self::assertSame('*bold*', $model->getAttributes()['body']);
        $body = $model->getAttribute('body');
        self::assertInstanceOf(RenderedCarve::class, $body);
        self::assertSame('*bold*', $body->source);
        $comment = $model->getAttribute('comment');
        self::assertInstanceOf(RenderedCarve::class, $comment);
        self::assertStringNotContainsString('<h1', $comment->html);
        $model->setAttribute('body', null);
        self::assertNull($model->getAttribute('body'));
    }

    public function testDefaultSelectionAndLazyConfiguration(): void
    {
        config()->set('carve.default', 'comment');
        app()->forgetInstance(CarveManager::class);
        Carve::clearResolvedInstances();
        self::assertStringNotContainsString('<h1', Carve::toHtml('# Heading'));
        self::assertStringContainsString('<h1', Carve::toHtml('# Heading', 'default'));
        config()->set('carve.converters.default.extensions', [['type' => HeadingLevelShiftExtension::class, 'shift' => 1]]);
        Carve::flush();
        self::assertStringContainsString('<h2', Carve::toHtml('# Heading', 'default'));
    }

    public function testUnknownExtensionConfigurationFailsExplicitly(): void
    {
        config()->set('carve.converters.default.extensions', ['misspelled']);
        $this->expectException(InvalidArgumentException::class);
        Carve::toHtml('text');
    }

    public function testStaticCarveViewUsesFileRendererWithoutBladeEvaluation(): void
    {
        $dir = sys_get_temp_dir() . '/carve-view-' . bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir . '/intro.crv', "# Title\n\n@php echo 'EXECUTED'; @endphp");
        try {
            $view = app(Factory::class);
            $view->addLocation($dir);
            $html = view('intro', ['unused' => 'value'])->render();
            self::assertStringContainsString('<h1>Title</h1>', $html);
            self::assertStringContainsString('@php', $html);
        } finally {
            unlink($dir . '/intro.crv');
            rmdir($dir);
        }
    }
}
