<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests\Service;

use InvalidArgumentException;
use MarkupCarve\Carve\Carve;
use MarkupCarve\Carve\Extension\Claim;
use MarkupCarve\Carve\Extension\ExtensionDefinition;
use MarkupCarve\Carve\Extension\RenderHost;
use MarkupCarve\Carve\Node;
use MarkupCarve\LaravelCarve\Service\ExtensionFactory;
use PHPUnit\Framework\TestCase;

class ExtensionFactoryTest extends TestCase
{
    private ExtensionFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ExtensionFactory();
    }

    public function testShorthandStringCreatesExtension(): void
    {
        // C2-X-039: tabs are the standard named extension definitions in 0.2.
        self::assertCount(2, $this->factory->create('tabs'));
        // C2-X-057: explicit autolinks are core syntax, so they need no extension registration.
        self::assertStringContainsString('href="https://example.test"', Carve::render('<https://example.test>'));
    }

    public function testArrayConfigCreatesExtension(): void
    {
        // C2-X-001: a host factory returns definitions, not 0.1 extension objects.
        self::assertContainsOnlyInstancesOf(ExtensionDefinition::class, $this->factory->create(['type' => 'tabs']));
        $this->expectException(InvalidArgumentException::class);
        $this->factory->create(['type' => 'tabs', 'misspelled_option' => true]);
    }

    public function testInlineRendererRunsThroughHostFactory(): void
    {
        $definition = new ExtensionDefinition(
            'host.embed',
            '1',
            matcher: ['form' => 'inline', 'name' => 'embed'],
            transform: static function (Claim $claim): Node {
                $node = new Node('inline_extension', []);
                $node->f['name'] = 'host.embed';
                $node->f['version'] = '1';
                $node->f['fallback'] = $claim->node->children ?? [];

                return $node;
            },
            renderer: static fn (Node $node, RenderHost $host): string => '<b>handled</b>',
        );
        self::assertStringContainsString(
            '<b>handled</b>',
            Carve::render('Watch :embed[abc].', ['extensions' => $this->factory->create($definition)]),
        );
    }

    public function testUnknownTypeReturnsNull(): void
    {
        // C2-X-001: an unavailable 0.1 shorthand must be reported explicitly.
        $this->expectException(InvalidArgumentException::class);
        $this->factory->create(['type' => 'nope']);
    }

    public function testCustomClassesInstancesAndWrongOptions(): void
    {
        // C2-X-001: applications pass definitions directly, without 0.1 extension classes.
        $definition = new ExtensionDefinition('host.example', '1');
        self::assertSame([$definition], $this->factory->create($definition));
        $this->expectException(InvalidArgumentException::class);
        $this->factory->create(['type' => 'plantuml', 'css_class' => 'bad class']);
    }

    public function testEveryTypeConstantCreatesExtension(): void
    {
        // C2-X-001: only shorthands with registered 0.2 definitions are exposed.
        foreach (['plantuml', 'tabs'] as $type) {
            self::assertNotEmpty($this->factory->create($type));
        }
    }

    public function testTypesAreUniqueAndSorted(): void
    {
        // C2-X-001: each 0.2 definition has a unique qualified name.
        $names = [];
        foreach ($this->factory->create('plantuml') as $definition) {
            self::assertInstanceOf(ExtensionDefinition::class, $definition);
            $names[] = $definition->name;
        }
        self::assertSame(count($names), count(array_unique($names)));
    }

    public function testExternalLinksAcceptsOptions(): void
    {
        self::markTestSkipped('External link document transform is pending carve-php issue 5238 (D4).');
    }

    public function testPlantumlShorthandIsAFencedRenderExtension(): void
    {
        // C2-X-005: both supported fence names have qualified definitions.
        $extensions = $this->factory->create('plantuml');
        self::assertCount(2, $extensions);
        self::assertInstanceOf(ExtensionDefinition::class, $extensions[0]);
        self::assertInstanceOf(ExtensionDefinition::class, $extensions[1]);
        self::assertSame(
            ['laravel.plantuml.plantuml', 'laravel.plantuml.puml'],
            [$extensions[0]->name, $extensions[1]->name],
        );
    }

    public function testPlantumlShorthandRendersPlantumlBlock(): void
    {
        $html = Carve::render("```plantuml\nA -> B\n```\n", ['extensions' => $this->factory->create('plantuml')]);
        // C2-X-034: the trusted host renderer escapes code text in its HTML.
        self::assertStringContainsString('<pre class="plantuml">A -&gt; B', $html);
    }

    public function testPumlLanguageAlsoRendersPlantumlBlock(): void
    {
        // C2-X-005: the alias is a second matcher with the same host renderer.
        $html = Carve::render("```puml\nA -> B\n```\n", ['extensions' => $this->factory->create('plantuml')]);
        self::assertStringContainsString('<pre class="plantuml">A -&gt; B', $html);
    }

    public function testPlantumlShorthandHonorsCssClassOverride(): void
    {
        // C2-X-034: renderer HTML uses the configured host class.
        $html = Carve::render("```plantuml\nA -> B\n```\n", [

            'extensions' => $this->factory->create([
                'type' => 'plantuml',
                'css_class' => 'uml',
            ]),
        ]);
        self::assertStringContainsString('<pre class="uml">', $html);
    }
}
