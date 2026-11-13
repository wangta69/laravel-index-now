<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Pondol\IndexNow\Enums\IndexingAction;
use Pondol\IndexNow\Services\IndexNowService;
use Throwable;

class SubmitCommand extends Command
{
    protected $signature = 'indexnow:submit
                            {urls?* : One or more URLs or relative paths to submit}
                            {--action=URL_UPDATED : Action type (URL_UPDATED or URL_DELETED)}
                            {--sitemap= : Local path or HTTP URL to a sitemap.xml}
                            {--sync : Execute immediately instead of dispatching to queue}
                            {--force : Ignore environment restrictions}';

    protected $description = 'Submit one or more URLs (or a sitemap.xml) to IndexNow & Google Indexing API';

    public function handle(IndexNowService $service): int
    {
        /** @var array<int, string> $urls */
        $urls = (array) $this->argument('urls');
        $sitemap = $this->option('sitemap');

        if (is_string($sitemap) && trim($sitemap) !== '') {
            $sitemapUrls = $this->parseSitemap(trim($sitemap));
            if ($sitemapUrls === []) {
                $this->error("No URLs found in sitemap: {$sitemap}");

                return self::FAILURE;
            }
            $urls = [...$urls, ...$sitemapUrls];
        }

        if ($urls === []) {
            $input = $this->ask('Enter the URL or relative path to submit');
            if (! is_string($input) || trim($input) === '') {
                $this->error('No URL provided.');

                return self::FAILURE;
            }
            $urls = [$input];
        }

        $action = IndexingAction::resolve((string) $this->option('action'));
        $isSync = (bool) $this->option('sync') || ! (bool) config('pondol-indexnow.queue.enabled', true);

        if ((bool) $this->option('force')) {
            $service = $service->force();
        }

        if ((bool) $this->option('sync')) {
            $service = $service->now();
        }

        $results = $service->submit($urls, $action);

        if (! $isSync) {
            $this->info('Queued '.count($urls)." URL(s) for indexing ({$action->value}).");

            return self::SUCCESS;
        }

        if ($results === []) {
            $this->warn('No requests were sent. Check enabled/environments config or use --force.');

            return self::SUCCESS;
        }

        $rows = [];
        $hasFailure = false;

        foreach ($results as $result) {
            if (! $result->successful) {
                $hasFailure = true;
            }

            $rows[] = [
                $result->engine,
                $result->successful ? '<fg=green>SUCCESS</>' : '<fg=red>FAILED</>',
                $result->statusCode ?? '-',
                count($result->urls),
                $result->message,
            ];
        }

        $this->table(['Engine', 'Status', 'HTTP', 'URLs', 'Message'], $rows);

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * sitemap.xml 및 sitemapindex를 파싱하여 URL 목록을 추출합니다.
     *
     * @return array<int, string>
     */
    protected function parseSitemap(string $source, int $depth = 0): array
    {
        if ($depth > 2) {
            return [];
        }

        $xmlContent = $this->fetchSitemapContent($source);
        if ($xmlContent === null) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return [];
        }

        $urls = [];
        $rootName = $xml->getName();

        if ($rootName === 'sitemapindex') {
            foreach ($xml->sitemap as $child) {
                $loc = trim((string) $child->loc);
                if ($loc !== '') {
                    $urls = [...$urls, ...$this->parseSitemap($loc, $depth + 1)];
                }
            }
        } elseif ($rootName === 'urlset') {
            foreach ($xml->url as $entry) {
                $loc = trim((string) $entry->loc);
                if ($loc !== '') {
                    $urls[] = $loc;
                }
            }
        }

        return array_values(array_unique($urls));
    }

    protected function fetchSitemapContent(string $source): ?string
    {
        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            try {
                $response = Http::timeout(15)->get($source);

                return $response->successful() ? $response->body() : null;
            } catch (Throwable) {
                return null;
            }
        }

        if (is_file($source) && is_readable($source)) {
            $content = file_get_contents($source);

            return $content !== false ? $content : null;
        }

        return null;
    }
}
