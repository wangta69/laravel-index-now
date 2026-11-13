<?php

declare(strict_types=1);

namespace Pondol\IndexNow;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Pondol\IndexNow\Console\Commands\DoctorCommand;
use Pondol\IndexNow\Console\Commands\InstallCommand;
use Pondol\IndexNow\Console\Commands\SubmitCommand;
use Pondol\IndexNow\Http\Controllers\KeyVerificationController;
use Pondol\IndexNow\Services\GoogleIndexingClient;
use Pondol\IndexNow\Services\IndexNowClient;
use Pondol\IndexNow\Services\IndexNowService;

class IndexNowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/pondol-indexnow.php',
            'pondol-indexnow'
        );

        $this->app->singleton(IndexNowClient::class);
        $this->app->singleton(GoogleIndexingClient::class);
        $this->app->singleton(IndexNowService::class, function ($app) {
            return new IndexNowService(
                indexNowClient: $app->make(IndexNowClient::class),
                googleClient: $app->make(GoogleIndexingClient::class),
            );
        });

        $this->app->alias(IndexNowService::class, 'pondol-indexnow');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config/pondol-indexnow.php' => config_path('pondol-indexnow.php'),
        ], ['pondol-indexnow-config', 'config']);

        $this->registerKeyVerificationRoute();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                DoctorCommand::class,
                SubmitCommand::class,
            ]);
        }
    }

    protected function registerKeyVerificationRoute(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        if (! (bool) config('pondol-indexnow.auto_serve_key', true)) {
            return;
        }

        $key = trim((string) config('pondol-indexnow.key', ''));
        if ($key === '') {
            return;
        }

        Route::get('/'.ltrim($key, '/').'.txt', KeyVerificationController::class)
            ->name('pondol-indexnow.key-verification');
    }
}
