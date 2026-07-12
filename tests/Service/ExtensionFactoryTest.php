<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests\Service;

use MarkupCarve\Carve\Extension\AutolinkExtension;
use MarkupCarve\Carve\Extension\ExternalLinksExtension;
use MarkupCarve\Carve\Extension\MentionsExtension;
use MarkupCarve\Carve\Extension\SemanticSpanExtension;
use MarkupCarve\Carve\Extension\SmartQuotesExtension;
use MarkupCarve\Carve\Extension\WikilinksExtension;
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
        $this->assertInstanceOf(AutolinkExtension::class, $this->factory->create('autolink'));
    }

    public function testArrayConfigCreatesExtension(): void
    {
        $this->assertInstanceOf(
            SmartQuotesExtension::class,
            $this->factory->create(['type' => 'smart_quotes', 'locale' => 'de']),
        );
    }

    public function testSemanticSpanHasNoOptions(): void
    {
        $this->assertInstanceOf(SemanticSpanExtension::class, $this->factory->create('semantic_span'));
    }

    public function testUnknownTypeReturnsNull(): void
    {
        $this->assertNull($this->factory->create(['type' => 'nope']));
    }

    public function testEveryTypeConstantCreatesExtension(): void
    {
        foreach (ExtensionFactory::types() as $type) {
            $this->assertNotNull($this->factory->create($type), sprintf('Type `%s` did not create an extension.', $type));
        }
    }

    public function testEveryBundledCarvePhpExtensionHasAType(): void
    {
        $files = glob(__DIR__ . '/../../vendor/markup-carve/carve-php/src/Extension/*Extension.php') ?: [];
        $this->assertNotEmpty($files);

        $missing = [];
        foreach ($files as $file) {
            $class = basename($file, '.php');
            if ($class === 'ExtensionInterface') {
                continue;
            }
            $stem = (string)preg_replace('/Extension$/', '', $class);
            $type = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $stem));
            if (!in_array($type, ExtensionFactory::types(), true)) {
                $missing[] = $class;
            }
        }

        $this->assertSame([], $missing, 'carve-php bundles extensions without a config type shorthand.');
    }

    public function testTypesAreUniqueAndSorted(): void
    {
        $types = ExtensionFactory::types();

        $this->assertSame(array_unique($types), $types);

        $sorted = $types;
        sort($sorted);
        $this->assertSame($sorted, $types);
    }

    public function testExternalLinksAcceptsOptions(): void
    {
        $ext = $this->factory->create([
            'type' => 'external_links',
            'internal_hosts' => ['example.com'],
            'target' => '_blank',
            'rel' => 'noopener',
            'nofollow' => true,
        ]);

        $this->assertInstanceOf(ExternalLinksExtension::class, $ext);
    }

    public function testMentionsAcceptsOptions(): void
    {
        $ext = $this->factory->create([
            'type' => 'mentions',
            'user_url_template' => '/users/{username}',
            'user_class' => 'mention',
        ]);

        $this->assertInstanceOf(MentionsExtension::class, $ext);
    }

    public function testWikilinksWithUrlTemplate(): void
    {
        $ext = $this->factory->create([
            'type' => 'wikilinks',
            'url_template' => '/wiki/{page}',
            'link_class' => 'wiki-link',
        ]);

        $this->assertInstanceOf(WikilinksExtension::class, $ext);
    }

    public function testWikilinksWithoutUrlTemplate(): void
    {
        $ext = $this->factory->create('wikilinks');

        $this->assertInstanceOf(WikilinksExtension::class, $ext);
    }
}
