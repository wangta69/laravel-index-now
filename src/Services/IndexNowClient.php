<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Pondol\IndexNow\Data\IndexingResult;
use Pondol\IndexNow\Support\UrlNormalizer;
use Throwable;

class IndexNowClient
{
    private const MAX_URLS_PER_REQUEST = 10000;

    /**
     * 활성화된 모든 IndexNow 검색엔진에 URL 목록을 전송합니다.
     *
     * @param  array<int, string>  $urls
     * @return array<int, IndexingResult>
     */
    public function send(array $urls): array
    {
        if ($urls === []) {
            return [];
        }

        $key = trim((string) config('pondol-indexnow.key', ''));
        if ($key === '') {
            return [
                IndexingResult::failure(
                    engine: 'indexnow',
                    statusCode: null,
                    urls: $urls,
                    message: 'INDEXNOW_KEY is not configured.',
                ),
            ];
        }

        $engines = $this->getEnabledEngines();
        if ($engines === []) {
            return [];
        }

        $groupedByHost = UrlNormalizer::groupByHost($urls);
        $timeout = (int) config('pondol-indexnow.timeout', 10);
        $configuredKeyLocation = config('pondol-indexnow.key_location');
        $results = [];

        foreach ($groupedByHost as $group) {
            $host = $group['host'];
            $keyLocation = is_string($configuredKeyLocation) && trim($configuredKeyLocation) !== ''
                ? trim($configuredKeyLocation)
                : "{$group['origin']}/{$key}.txt";

            foreach (array_chunk($group['urls'], self::MAX_URLS_PER_REQUEST) as $chunk) {
                $payload = [
                    'host' => $host,
                    'key' => $key,
                    'keyLocation' => $keyLocation,
                    'urlList' => $chunk,
                ];

                foreach ($engines as $engineName => $endpoint) {
                    $results[] = $this->dispatchToEngine(
                        engine: $engineName,
                        endpoint: $endpoint,
                        payload: $payload,
                        timeout: $timeout,
                    );
                }
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function dispatchToEngine(
        string $engine,
        string $endpoint,
        array $payload,
        int $timeout,
    ): IndexingResult {
        /** @var array<int, string> $urls */
        $urls = $payload['urlList'];

        try {
            /** @var Response $response */
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            $status = $response->status();

            if (in_array($status, [200, 202], true)) {
                return IndexingResult::success(
                    engine: $engine,
                    statusCode: $status,
                    urls: $urls,
                    message: $this->describeStatus($status),
                    rawResponse: $response->body(),
                );
            }

            return IndexingResult::failure(
                engine: $engine,
                statusCode: $status,
                urls: $urls,
                message: $this->describeStatus($status, $response->body()),
                rawResponse: $response->body(),
            );
        } catch (Throwable $e) {
            return IndexingResult::failure(
                engine: $engine,
                statusCode: null,
                urls: $urls,
                message: $e->getMessage(),
            );
        }
    }

    /**
     * 활성화된 검색엔진 목록과 정규화된 엔드포인트를 반환합니다.
     *
     * @return array<string, string>
     */
    public function getEnabledEngines(): array
    {
        $engines = config('pondol-indexnow.search_engines', []);
        if (! is_array($engines)) {
            return [];
        }

        $enabled = [];

        foreach ($engines as $name => $settings) {
            if (! is_array($settings) || empty($settings['enabled']) || empty($settings['endpoint'])) {
                continue;
            }

            $enabled[(string) $name] = $this->normalizeEndpoint((string) $settings['endpoint']);
        }

        return $enabled;
    }

    /**
     * 구버전 config('api.indexnow.org') 호환을 위해 스킴 및 경로를 자동 보정합니다.
     */
    public function normalizeEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);

        if (! str_starts_with($endpoint, 'http://') && ! str_starts_with($endpoint, 'https://')) {
            $endpoint = 'https://'.ltrim($endpoint, '/');
        }

        $path = parse_url($endpoint, PHP_URL_PATH);
        if ($path === null || $path === '' || $path === '/') {
            $endpoint = rtrim($endpoint, '/').'/indexnow';
        }

        return $endpoint;
    }

    protected function describeStatus(int $status, string $body = ''): string
    {
        $base = match ($status) {
            200 => 'OK - URL submitted successfully.',
            202 => 'Accepted - Key validation pending.',
            400 => 'Bad Request - Invalid payload format.',
            403 => 'Forbidden - Key is invalid or key file verification failed.',
            422 => 'Unprocessable Entity - URL host does not match keyLocation host.',
            429 => 'Too Many Requests - Rate limit exceeded.',
            default => "HTTP {$status}",
        };

        $trimmed = trim($body);

        return $trimmed !== '' && $status >= 400 ? "{$base} ({$trimmed})" : $base;
    }
}
