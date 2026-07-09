<?php

declare(strict_types=1);

namespace MarkupCarve\LaravelCarve\Tests;

use MarkupCarve\LaravelCarve\Facades\Carve;
use MarkupCarve\LaravelCarve\LaravelCarveServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelCarveServiceProvider::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Carve' => Carve::class,
        ];
    }
}
