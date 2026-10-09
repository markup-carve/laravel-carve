<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests;

use MarkupCarve\LaravelCarve\Scripts\DeclaredCorpusPairs;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../scripts/DeclaredCorpusPairs.php';

/**
 * The counter the engine-drift workflow's corpus population guard reads its
 * reference from. It used to live inside that workflow's `run:` block, where
 * nothing could test it.
 */
class DeclaredCorpusPairsTest extends TestCase
{
    public function testCountsEveryCarveFenceInACompareBlock(): void
    {
        $page = [
            '::: compare',
            '```carve',
            'one',
            '```',
            '```html',
            '<p>one</p>',
            '```',
            '````carve',
            '```carve',
            'nested, not a pair',
            '```',
            '````',
            '```html',
            '<pre>two</pre>',
            '```',
            '```carve',
            'three',
            '```',
            '```html',
            '<p>three</p>',
            '```',
            ':::',
            '```carve',
            'outside any block',
            '```',
        ];

        $got = DeclaredCorpusPairs::count($page);
        $this->assertSame(3, $got, "got {$got} pairs, want 3");
    }

    public function testACarveFenceOutsideAnyBlockDeclaresNoPair(): void
    {
        $this->assertSame(0, DeclaredCorpusPairs::count([
            '```carve',
            'prose example',
            '```',
        ]));
    }

    public function testAWiderFenceHoldingACarveLineDeclaresOnlyItself(): void
    {
        $this->assertSame(1, DeclaredCorpusPairs::count([
            '::: compare',
            '````carve',
            '```carve',
            'content, not markup',
            '```',
            '````',
            ':::',
        ]));
    }

    public function testABlockClosesOnItsOwnMarkerOnly(): void
    {
        // A four-colon block is not closed by a three-colon line, so the carve
        // fence after it is still inside the block.
        $this->assertSame(2, DeclaredCorpusPairs::count([
            ':::: compare',
            '```carve',
            'one',
            '```',
            ':::',
            '```carve',
            'two',
            '```',
            '::::',
            '```carve',
            'outside',
            '```',
        ]));
    }
}
