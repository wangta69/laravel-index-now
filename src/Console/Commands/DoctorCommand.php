<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Pondol\IndexNow\Services\GoogleIndexingClient;
use Pondol\IndexNow\Services\IndexNowClient;
use Throwable;

class DoctorCommand extends Command
{
    protected $signature = 'indexnow:doctor
                            {--skip-http : Skip live HTTP verification checks}';

    protected $description = 'Diagnose IndexNow and Google Indexing API configuration';

    protected bool $hasFailure = false;

    public function handle(IndexNowClient $indexNowClient, GoogleIndexingClient $googleClient): int
    {
        $this->info('Pondol IndexNow Diagnostics');
        $this->newLine();

        $this->checkEnvironment();
        $this->checkIndexNowKey((bool) $this->option('skip-http'));
        $this->checkSearchEngines($indexNowClient);
        $this->checkGoogleIndexing($googleClient, (bool) $this->option('skip-http'));
        $this->checkQueue();

        $this->newLine();

        if ($this->hasFailure) {
            $this->error('Diagnostics completed with errors. Please review the [FAIL] items above.');

            return self::FAILURE;
        }

        $this->info('All checks passed! Your IndexNow setup looks healthy.');

        return self::SUCCESS;
    }

    protected function checkEnvironment(): void
    {
        $enabled = (bool) config('pondol-indexnow.enabled', true);
        $this->report($enabled, 'Master Switch (enabled)', $enabled ? 'Enabled' : 'Disabled');

        $currentEnv = app()->environment();
        $allowed = (array) config('pondol-indexnow.environments', ['production']);
        $envAllowed = in_array('*', $allowed, true) || in_array($currentEnv, $allowed, true);

        $this->report(
            true,
            'Environment Check',
            $envAllowed
                ? "Current env '{$currentEnv}' is allowed."
                : "Current env '{$currentEnv}' is NOT in allowed environments (".implode(', ', $allowed).') — requests will be skipped unless forced.',
            warning: ! $envAllowed
        );

        $appUrl = trim((string) config('app.url', ''));
        $isValidUrl = filter_var($appUrl, FILTER_VALIDATE_URL) !== false;
        $isLocalhost = str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1');

        $this->report(
            $isValidUrl,
            'APP_URL',
            $isValidUrl ? $appUrl.($isLocalhost ? ' (Warning: localhost cannot be indexed by search engines)' : '') : 'Invalid or missing APP_URL',
            warning: $isValidUrl && $isLocalhost
        );
    }

    protected function checkIndexNowKey(bool $skipHttp): void
    {
        $key = trim((string) config('pondol-indexnow.key', ''));
        if ($key === '') {
            $this->report(false, 'INDEXNOW_KEY', 'Missing INDEXNOW_KEY in .env or config.');

            return;
        }

        $validFormat = (bool) preg_match('/^[a-zA-Z0-9\-]{8,128}$/', $key);
        $this->report(
            $validFormat,
            'INDEXNOW_KEY Format',
            $validFormat ? "Valid ({$key})" : 'Invalid format. Must be 8-128 alphanumeric or hyphen characters.'
        );

        $autoServe = (bool) config('pondol-indexnow.auto_serve_key', true);
        $staticExists = is_file(public_path("{$key}.txt"));
        $this->report(
            $autoServe || $staticExists,
            'Key Serving Method',
            $staticExists
                ? "Static file found at public/{$key}.txt".($autoServe ? ' (auto_serve_key also enabled)' : '')
                : ($autoServe ? 'Served dynamically via package route (auto_serve_key=true)' : "Neither static file public/{$key}.txt nor auto_serve_key is active.")
        );

        if ($skipHttp) {
            return;
        }

        $configuredLocation = trim((string) config('pondol-indexnow.key_location', ''));
        $keyUrl = $configuredLocation !== ''
            ? $configuredLocation
            : rtrim((string) config('app.url', ''), '/')."/{$key}.txt";

        if (filter_var($keyUrl, FILTER_VALIDATE_URL) === false) {
            $this->report(false, 'Live Key Verification', "Cannot verify invalid URL: {$keyUrl}");

            return;
        }

        try {
            $response = Http::timeout(5)->get($keyUrl);
            $matched = $response->successful() && trim($response->body()) === $key;
            $this->report(
                $matched,
                'Live Key Verification',
                $matched
                    ? "Verified at {$keyUrl} (HTTP {$response->status()})"
                    : "Failed at {$keyUrl} (HTTP {$response->status()}) — ensure your web server is reachable and serving the exact key."
            );
        } catch (Throwable $e) {
            $this->report(true, 'Live Key Verification', "Could not reach {$keyUrl}: {$e->getMessage()}", warning: true);
        }
    }

    protected function checkSearchEngines(IndexNowClient $client): void
    {
        $engines = $client->getEnabledEngines();

        if ($engines === []) {
            $this->report(true, 'Search Engines', 'No IndexNow search engines are enabled.', warning: true);

            return;
        }

        $list = collect($engines)
            ->map(fn (string $endpoint, string $name) => "{$name} ({$endpoint})")
            ->implode(', ');

        $this->report(true, 'Search Engines', $list);
    }

    protected function checkGoogleIndexing(GoogleIndexingClient $googleClient, bool $skipHttp): void
    {
        if (! (bool) config('pondol-indexnow.google.enabled', false)) {
            $this->line('  <fg=gray>[SKIP]</> Google Indexing API: Disabled in config.');

            return;
        }

        $keyPath = $googleClient->resolveKeyPath();
        if ($keyPath === null) {
            $configured = (string) config('pondol-indexnow.google.key_file', 'google-api-key.json');
            $this->report(false, 'Google Key File', "File not found: {$configured}");

            return;
        }

        $this->report(true, 'Google Key File', $keyPath);

        try {
            $credentials = $googleClient->loadCredentials();
            $this->report(
                true,
                'Google Service Account',
                "{$credentials['client_email']} (Ensure this email is added as Owner in Search Console)"
            );
        } catch (Throwable $e) {
            $this->report(false, 'Google Credentials JSON', $e->getMessage());

            return;
        }

        if ($skipHttp) {
            return;
        }

        try {
            $googleClient->getAccessToken(timeout: 5, forceRefresh: true);
            $this->report(true, 'Google OAuth2 Token', 'Access token issued successfully.');
        } catch (Throwable $e) {
            $this->report(false, 'Google OAuth2 Token', $e->getMessage());
        }
    }

    protected function checkQueue(): void
    {
        $queueEnabled = (bool) config('pondol-indexnow.queue.enabled', true);
        $connection = (string) (config('pondol-indexnow.queue.connection') ?: config('queue.default'));

        $this->report(
            true,
            'Queue Configuration',
            $queueEnabled
                ? "Async via queue connection '{$connection}'".($connection !== 'sync' ? ' (remember to run php artisan queue:work)' : '')
                : 'Synchronous execution (queue.enabled=false)'
        );
    }

    protected function report(bool $passed, string $label, string $detail, bool $warning = false): void
    {
        if (! $passed) {
            $this->hasFailure = true;
            $this->line("  <fg=red>[FAIL]</> <options=bold>{$label}</>: {$detail}");

            return;
        }

        if ($warning) {
            $this->line("  <fg=yellow>[WARN]</> <options=bold>{$label}</>: {$detail}");

            return;
        }

        $this->line("  <fg=green>[PASS]</> <options=bold>{$label}</>: {$detail}");
    }
}
