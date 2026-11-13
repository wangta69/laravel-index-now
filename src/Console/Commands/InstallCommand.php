<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Console\Commands;

use Illuminate\Console\Command;
use Pondol\IndexNow\Services\IndexNowService;

class InstallCommand extends Command
{
    protected $signature = 'pondol:install-index-now
                            {type=full : Install mode (full or only)}
                            {--force : Overwrite existing configuration file}
                            {--init : Send an initial homepage ping to activate IndexNow without prompting}';

    protected $description = "Install Pondol's Laravel IndexNow & Google Indexing package";

    protected bool $keyUpdated = false;

    public function handle(IndexNowService $service): int
    {
        $type = (string) $this->argument('type');
        $configPath = config_path('pondol-indexnow.php');
        $force = (bool) $this->option('force');

        $shouldPublish = true;

        if (file_exists($configPath) && ! $force) {
            if ($type === 'only') {
                $shouldPublish = false;
            } else {
                $shouldPublish = $this->confirm(
                    'config/pondol-indexnow.php already exists. Overwrite it?',
                    false
                );
            }
        }

        if ($shouldPublish) {
            $this->call('vendor:publish', [
                '--provider' => 'Pondol\\IndexNow\\IndexNowServiceProvider',
                '--tag' => 'pondol-indexnow-config',
                '--force' => true,
            ]);
        }

        if ($type === 'only') {
            $this->info('Pondol IndexNow configuration published.');

            return self::SUCCESS;
        }

        $this->setupIndexNowKey();
        $this->setupGoogleIndexing();
        $this->clearCacheIfNeeded();
        $this->sendInitialActivationPing($service);

        $this->newLine();
        $this->info("Pondol's Laravel IndexNow has been installed successfully!");
        $this->line('Run <comment>php artisan indexnow:doctor</comment> to verify your setup.');

        return self::SUCCESS;
    }

    protected function setupIndexNowKey(): void
    {
        $currentKey = trim((string) config('pondol-indexnow.key', ''));

        if ($currentKey !== '') {
            $this->line("Existing <info>INDEXNOW_KEY</info> detected: <comment>{$currentKey}</comment>");

            return;
        }

        if (! $this->confirm('INDEXNOW_KEY is not set. Generate a random key and save it to .env?', true)) {
            return;
        }

        $key = bin2hex(random_bytes(16));

        if ($this->writeEnvValue('INDEXNOW_KEY', $key)) {
            $this->info("Generated and saved INDEXNOW_KEY={$key} to .env");
            config()->set('pondol-indexnow.key', $key);
            $this->keyUpdated = true;
        } else {
            $this->warn(".env file not found. Please add INDEXNOW_KEY={$key} manually.");
        }

        $this->line('Note: By default, <info>auto_serve_key</info> is enabled, so <comment>/{key}.txt</comment> is served automatically without creating a static file.');

        if ($this->confirm('Would you also like to create a static file in public/ as a fallback?', false)) {
            $publicFile = public_path("{$key}.txt");
            if (file_put_contents($publicFile, $key) !== false) {
                $this->info("Created {$publicFile}");
            } else {
                $this->warn("Failed to create {$publicFile}");
            }
        }
    }

    protected function setupGoogleIndexing(): void
    {
        if (config('pondol-indexnow.google.enabled')) {
            return;
        }

        if (! $this->confirm('Do you want to enable Google Indexing API now?', false)) {
            return;
        }

        if ($this->writeEnvValue('INDEXNOW_GOOGLE_ENABLED', 'true')) {
            config()->set('pondol-indexnow.google.enabled', true);
            $this->info('Enabled INDEXNOW_GOOGLE_ENABLED=true in .env');
        }

        $keyFile = (string) config('pondol-indexnow.google.key_file', 'google-api-key.json');
        $this->line("Please place your Google service account JSON at: <comment>storage/app/{$keyFile}</comment>");
    }

    protected function clearCacheIfNeeded(): void
    {
        if (! $this->keyUpdated) {
            return;
        }

        if ($this->laravel->configurationIsCached() || $this->laravel->routesAreCached()) {
            $this->warn('Cached config/routes detected. Clearing cache so the new /{key}.txt route becomes active...');
            $this->call('optimize:clear');
        }
    }

    protected function sendInitialActivationPing(IndexNowService $service): void
    {
        $key = trim((string) config('pondol-indexnow.key', ''));
        $appUrl = trim((string) config('app.url', ''));

        if ($key === '' || filter_var($appUrl, FILTER_VALIDATE_URL) === false) {
            return;
        }

        if ($this->isLocalUrl($appUrl)) {
            $this->newLine();
            $this->line('Note: Once deployed to production, run <comment>php artisan indexnow:submit / --sync</comment> to send your first ping and activate IndexNow in Bing/Naver.');

            return;
        }

        $shouldInit = (bool) $this->option('init')
            || $this->confirm("Send an initial ping for [{$appUrl}] to activate IndexNow in search engines now?", false);

        if (! $shouldInit) {
            return;
        }

        $this->line("Sending initial activation ping for <comment>{$appUrl}</comment>...");
        $results = $service->force()->now()->update($appUrl);

        foreach ($results as $result) {
            if ($result->successful) {
                $this->info("  [OK] {$result->engine}: HTTP {$result->statusCode} — {$result->message}");
            } else {
                $this->error("  [FAIL] {$result->engine}: {$result->message}");
            }
        }

        $this->line('Note: It may take a few minutes for Bing Webmaster Tools to reflect the IndexNow activation.');
    }

    protected function isLocalUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        return str_ends_with($host, '.local')
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.localhost');
    }

    protected function writeEnvValue(string $key, string $value): bool
    {
        $envPath = base_path('.env');
        if (! is_file($envPath) || ! is_writable($envPath)) {
            return false;
        }

        $contents = file_get_contents($envPath);
        if ($contents === false) {
            return false;
        }

        $line = "{$key}={$value}";
        $pattern = '/^'.preg_quote($key, '/').'=.*/m';

        if (preg_match($pattern, $contents)) {
            $updated = (string) preg_replace($pattern, $line, $contents, 1);
        } else {
            $separator = $contents === '' || str_ends_with($contents, "\n") ? '' : PHP_EOL;
            $updated = $contents.$separator.$line.PHP_EOL;
        }

        return file_put_contents($envPath, $updated) !== false;
    }
}
