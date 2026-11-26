<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests\Tiptap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\ProseMirror\ProseMirrorRenderer;
use MarkupCarve\Carve\ProseMirror\ProseMirrorToCarve;
use MarkupCarve\LaravelCarve\Casts\AsCarveDocument;
use MarkupCarve\LaravelCarve\Events\TiptapAttributesIgnored;
use MarkupCarve\LaravelCarve\Tests\TestCase;
use MarkupCarve\LaravelCarve\Tiptap\CarveDocument;
use MarkupCarve\LaravelCarve\Tiptap\LossyTiptapDocument;
use MarkupCarve\LaravelCarve\Tiptap\TiptapConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

class TiptapTest extends TestCase
{
    /**
     * @var string
     */
    private const SOURCE = "== Title\n\nSome *bold* text with a [link](https://example.com).\n\n- one\n- two\n";

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }

    /**
     * An editor document as the Tiptap kit loads it.
     *
     * @return array<string, mixed>
     */
    private static function document(): array
    {
        return (new ProseMirrorRenderer())->renderSource(self::SOURCE);
    }

    /**
     * A stock Tiptap document with an attribute the bridge does not keep.
     *
     * @return array<string, mixed>
     */
    private static function lossyDocument(): array
    {
        return [

            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'attrs' => ['textAlign' => 'center'], 'content' => [['type' => 'text', 'text' => 'Hi']]],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @param string $cast
     */
    private function model(array $attributes = [], string $cast = AsCarveDocument::class): Model
    {
        $model = new class extends Model {
            /**
             * @var string
             */
            protected $table = 'posts';

            /**
             * @var array<string>
             */
            protected $guarded = [];

            /**
             * @var bool
             */
            public $timestamps = false;
        };
        $model->mergeCasts(['body' => $cast]);
        $model->forceFill($attributes);

        return $model;
    }

    private function service(): TiptapConverter
    {
        return app(TiptapConverter::class);
    }

    public function testServiceIsBoundAsSingleton(): void
    {
        $this->assertInstanceOf(TiptapConverter::class, app('carve.tiptap'));
        $this->assertSame($this->service(), app('carve.tiptap'));
    }

    public function testToCarveEqualsTheBridge(): void
    {
        $service = $this->service();
        $expected = (new ProseMirrorToCarve())->convert(self::document());

        $this->assertSame($expected, $service->toCarve(self::document()));
        $this->assertSame($expected, $service->toCarve(json_encode(self::document(), JSON_THROW_ON_ERROR)));
        $this->assertSame($expected, $service->toCarve(CarveDocument::from(self::document())));
        $this->assertSame(Carve::format(self::SOURCE), $expected);
    }

    public function testFromCarveEqualsTheRenderer(): void
    {
        $this->assertSame(
            (new ProseMirrorRenderer())->renderSource(self::SOURCE),
            $this->service()->fromCarve(self::SOURCE),
        );
    }

    public function testExportsEqualTheCarvePhpCalls(): void
    {
        $service = $this->service();
        $source = Carve::format(self::SOURCE);
        $tree = Carve::parse($source);

        $this->assertSame(Carve::writeTo('plain', $tree), $service->plainText(self::document()));
        $this->assertSame(Carve::writeTo('markdown', $tree), $service->markdown(self::document()));
        $this->assertSame(Carve::render($source), $service->html(self::document()));
        $this->assertStringContainsString('<strong>bold</strong>', $service->html(self::document()));
    }

    public function testExportsUseTheNamedProfile(): void
    {
        config()->set('carve.converters.default.heading_shift', 2);
        $service = $this->service();

        $this->assertStringContainsString('<h4', $service->html(self::document()));
    }

    public function testLintEqualsCarveLintAndPointsIntoTheEditor(): void
    {
        $doc = [

            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Title']]],
                ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Too deep']]],
            ],
        ];
        $service = $this->service();
        $findings = $service->lint($doc);

        $this->assertEquals(array_values(Carve::lint((new ProseMirrorToCarve())->convert($doc))), $findings);
        $this->assertSame('heading-level-skip', $findings[0]->code);
        $this->assertSame('/content/1', $service->pointerFor($doc, (string)$findings[0]->path));
    }

    public function testIgnoredAttributesPassThrough(): void
    {
        $bridge = new ProseMirrorToCarve();
        $bridge->convert(self::lossyDocument());
        $service = $this->service();

        $this->assertSame($bridge->ignoredAttributes(), $service->ignoredAttributes(self::lossyDocument()));
        $this->assertSame([['path' => '/content/0', 'type' => 'paragraph', 'attribute' => 'textAlign', 'value' => 'center']], $service->ignoredAttributes(self::lossyDocument()));
        $this->assertSame([], $service->ignoredAttributes(self::document()));
    }

    public function testMappingConfigReachesBothDirections(): void
    {
        config()->set('carve.tiptap.mapping', [
            'nodes' => ['placeholderToken' => ['as' => 'span', 'class' => 'placeholder', 'attributes' => ['name']]],
        ]);
        $doc = [

            'type' => 'doc',
            'content' => [
                [

                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Dear '],
                        ['type' => 'placeholderToken', 'attrs' => ['name' => 'first']],
                    ],
                ],
            ],
        ];
        $service = $this->service();
        $source = $service->toCarve($doc);

        $this->assertSame("Dear []{.placeholder name=first}\n", $source);
        $loaded = $service->fromCarve($source);
        $this->assertSame($doc, ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => Arr::get($loaded, 'content.0.content')]]]);
    }

    public function testUnmappedNodeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->toCarve(['type' => 'doc', 'content' => [['type' => 'mention']]]);
    }

    /**
     * @return array<string, array{array<string, mixed>|string}>
     */
    public static function invalidDocuments(): array
    {
        return [
            'invalid JSON' => ['{"type": "doc"'],
            'not a doc' => ['{"type": "paragraph"}'],
            'JSON scalar' => ['"doc"'],
            'array without type' => [['content' => []]],
        ];
    }

    /**
     * @param array<string, mixed>|string $input
     */
    #[DataProvider('invalidDocuments')]
    public function testDocumentRejectsInvalidInput(array|string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        CarveDocument::from($input);
    }

    public function testDocumentShortcuts(): void
    {
        $document = CarveDocument::fromCarve(self::SOURCE, 'comment');
        $service = $this->service();

        $this->assertSame(self::document(), $document->toArray());
        $this->assertSame('comment', $document->converter());
        $this->assertSame(Carve::format(self::SOURCE), $document->carve());
        $this->assertSame($service->html(self::document(), 'comment'), $document->html());
        $this->assertSame($service->plainText(self::document(), 'comment'), $document->plainText());
        $this->assertSame($service->markdown(self::document(), 'comment'), $document->markdown());
        $this->assertEquals($service->lint(self::document()), $document->lint());
        $this->assertSame([], $document->ignoredAttributes());
        $this->assertSame(json_encode(self::document(), JSON_THROW_ON_ERROR), $document->toJson());
        $this->assertSame(json_encode(self::document(), JSON_THROW_ON_ERROR), json_encode($document, JSON_THROW_ON_ERROR));
    }

    public function testCastGetNull(): void
    {
        $this->assertNull((new AsCarveDocument())->get($this->model(), 'body', null, []));
    }

    public function testCastGetRejectsNonString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AsCarveDocument())->get($this->model(), 'body', 42, []);
    }

    public function testCastGetRejectsInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AsCarveDocument())->get($this->model(), 'body', '{not json', []);
    }

    public function testCastGetCarriesTheProfile(): void
    {
        $json = json_encode(self::document(), JSON_THROW_ON_ERROR);
        $document = (new AsCarveDocument('comment'))->get($this->model(), 'body', $json, []);

        $this->assertInstanceOf(CarveDocument::class, $document);
        $this->assertSame('comment', $document->converter());
        $this->assertSame(self::document(), $document->toArray());
    }

    public function testCastSetNull(): void
    {
        $this->assertNull((new AsCarveDocument())->set($this->model(), 'body', null, []));
    }

    public function testCastSetEmptyDocument(): void
    {
        $empty = ['type' => 'doc', 'content' => [['type' => 'paragraph']]];
        $stored = (new AsCarveDocument())->set($this->model(), 'body', $empty, []);

        $this->assertSame(json_encode($empty, JSON_THROW_ON_ERROR), $stored);
        $this->assertSame("\n", CarveDocument::from((string)$stored)->carve());
    }

    public function testCastSetAcceptsEveryForm(): void
    {
        $cast = new AsCarveDocument();
        $json = json_encode(self::document(), JSON_THROW_ON_ERROR);

        $this->assertSame($json, $cast->set($this->model(), 'body', self::document(), []));
        $this->assertSame($json, $cast->set($this->model(), 'body', $json, []));
        $this->assertSame($json, $cast->set($this->model(), 'body', str($json), []));
        $this->assertSame($json, $cast->set($this->model(), 'body', CarveDocument::from(self::document()), []));
    }

    public function testCastSetStoresJsonVerbatim(): void
    {
        $json = '{"type":"doc","attrs":{},"content":[{"type":"paragraph","content":[{"type":"text","text":"Grüße / ok"}]}]}';

        $this->assertSame($json, (new AsCarveDocument())->set($this->model(), 'body', $json, []));
        $this->assertSame($json, CarveDocument::from($json, 'comment')->toJson());
    }

    public function testCastSetRejectsOtherValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AsCarveDocument())->set($this->model(), 'body', new stdClass(), []);
    }

    public function testCastSetRejectsInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AsCarveDocument())->set($this->model(), 'body', '{"type":', []);
    }

    public function testCastSetRejectsUnmappedNode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AsCarveDocument())->set($this->model(), 'body', ['type' => 'doc', 'content' => [['type' => 'mention']]], []);
    }

    public function testLossThrowsByDefault(): void
    {
        try {
            (new AsCarveDocument())->set($this->model(), 'body', self::lossyDocument(), []);
            $this->fail('A lossy save must throw.');
        } catch (LossyTiptapDocument $exception) {
            $this->assertSame('body', $exception->key);
            $this->assertSame('textAlign', $exception->ignored[0]['attribute']);
            $this->assertStringContainsString('"textAlign" on paragraph at /content/0', $exception->getMessage());
        }
    }

    public function testLossDispatchesEventAndStores(): void
    {
        Event::fake([TiptapAttributesIgnored::class]);
        config()->set('carve.tiptap.on_loss', 'event');
        $model = $this->model();
        $stored = (new AsCarveDocument())->set($model, 'body', self::lossyDocument(), []);

        $this->assertSame(json_encode(self::lossyDocument(), JSON_THROW_ON_ERROR), $stored);
        Event::assertDispatched(TiptapAttributesIgnored::class, static fn (TiptapAttributesIgnored $event): bool => $event->model === $model
            && $event->key === 'body'
            && $event->ignored[0]['attribute'] === 'textAlign');
    }

    public function testLossIgnored(): void
    {
        Event::fake([TiptapAttributesIgnored::class]);
        config()->set('carve.tiptap.on_loss', 'ignore');
        $stored = (new AsCarveDocument())->set($this->model(), 'body', self::lossyDocument(), []);

        $this->assertSame(json_encode(self::lossyDocument(), JSON_THROW_ON_ERROR), $stored);
        Event::assertNotDispatched(TiptapAttributesIgnored::class);
    }

    public function testUnknownLossSignalFails(): void
    {
        config()->set('carve.tiptap.on_loss', 'warn');
        $this->expectException(InvalidArgumentException::class);
        (new AsCarveDocument())->set($this->model(), 'body', self::lossyDocument(), []);
    }

    public function testEloquentModelRoundTrip(): void
    {
        Schema::create('posts', static function (Blueprint $table): void {
            $table->id();
            $table->json('body')->nullable();
        });
        $model = $this->model(['body' => self::document()], AsCarveDocument::class . ':comment');
        $model->save();

        $fresh = $model->newQuery()->whereKey($model->getKey())->firstOrFail();
        $body = $fresh->getAttribute('body');
        $this->assertInstanceOf(CarveDocument::class, $body);
        $this->assertSame(self::document(), $body->toArray());
        $this->assertSame('comment', $body->converter());
        $this->assertSame(Carve::format(self::SOURCE), $body->carve());
        $this->assertSame(json_encode(self::document(), JSON_THROW_ON_ERROR), $fresh->getRawOriginal('body'));

        $fresh->setAttribute('body', null);
        $fresh->save();
        $this->assertNull($model->newQuery()->whereKey($model->getKey())->firstOrFail()->getAttribute('body'));
    }
}
