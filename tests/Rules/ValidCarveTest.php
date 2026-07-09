<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests\Rules;

use Illuminate\Support\Facades\Validator;
use MarkupCarve\LaravelCarve\Rules\ValidCarve;
use MarkupCarve\LaravelCarve\Tests\TestCase;

class ValidCarveTest extends TestCase
{
    public function testValidCarvePasses(): void
    {
        $validator = Validator::make(
            ['body' => 'Hello *world*!'],
            ['body' => [new ValidCarve()]],
        );

        $this->assertTrue($validator->passes());
    }

    public function testNullValuePasses(): void
    {
        $validator = Validator::make(
            ['body' => null],
            ['body' => [new ValidCarve()]],
        );

        $this->assertTrue($validator->passes());
    }

    public function testEmptyStringPasses(): void
    {
        $validator = Validator::make(
            ['body' => ''],
            ['body' => [new ValidCarve()]],
        );

        $this->assertTrue($validator->passes());
    }

    public function testComplexValidCarvePasses(): void
    {
        $carve = <<<'DJOT'
        # Heading

        A paragraph with *strong* and _emphasis_.

        - List item 1
        - List item 2

        > A blockquote
        DJOT;

        $validator = Validator::make(
            ['body' => $carve],
            ['body' => [new ValidCarve()]],
        );

        $this->assertTrue($validator->passes());
    }

    public function testNonStringFails(): void
    {
        $validator = Validator::make(
            ['body' => 123],
            ['body' => [new ValidCarve()]],
        );

        $this->assertFalse($validator->passes());
    }
}
