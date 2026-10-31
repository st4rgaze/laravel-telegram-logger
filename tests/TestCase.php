<?php

declare(strict_types=1);

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use St4rgaze\TelegramLogger\TelegramLoggerServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get the package providers.
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            TelegramLoggerServiceProvider::class,
        ];
    }
}
