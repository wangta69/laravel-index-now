<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Pondol\IndexNow\IndexNowServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            IndexNowServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.url', 'https://example.com');
        $app['config']->set('app.env', 'production');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('pondol-indexnow.enabled', true);
        $app['config']->set('pondol-indexnow.environments', ['production']);
        $app['config']->set('pondol-indexnow.key', 'test-indexnow-key-12345678');
        $app['config']->set('pondol-indexnow.auto_serve_key', true);
        $app['config']->set('pondol-indexnow.log', false);
        $app['config']->set('pondol-indexnow.queue.enabled', false);
    }
}
