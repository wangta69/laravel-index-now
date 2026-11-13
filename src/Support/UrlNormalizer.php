<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Support;

class UrlNormalizer
{
    /**
     * 단건 또는 다건의 경로/URL을 정규화된 절대 URL 배열로 변환합니다.
     *
     * @param  string|array<int, string>  $paths
     * @return array<int, string>
     */
    public static function normalize(string|array $paths): array
    {
        $items = is_array($paths) ? $paths : [$paths];
        $baseUrl = rtrim((string) config('app.url', ''), '/');
        $normalized = [];

        foreach ($items as $item) {
            if (! is_string($item)) {
                continue;
            }

            $item = trim($item);
            if ($item === '') {
                continue;
            }

            $scheme = parse_url($item, PHP_URL_SCHEME);
            if (in_array($scheme, ['http', 'https'], true)) {
                $url = $item;
            } elseif ($baseUrl !== '') {
                $url = $baseUrl.'/'.ltrim($item, '/');
            } else {
                continue;
            }

            if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
                $normalized[] = $url;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * 호스트(scheme + host + port)별로 URL을 그룹핑합니다.
     * IndexNow API는 1회 요청의 urlList가 모두 같은 host 소속이어야 합니다.
     *
     * @param  array<int, string>  $urls
     * @return array<string, array{host: string, origin: string, urls: array<int, string>}>
     */
    public static function groupByHost(array $urls): array
    {
        $grouped = [];

        foreach ($urls as $url) {
            $parsed = parse_url($url);
            if (! is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
                continue;
            }

            $scheme = $parsed['scheme'];
            $host = $parsed['host'];
            $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
            $origin = "{$scheme}://{$host}{$port}";

            if (! isset($grouped[$origin])) {
                $grouped[$origin] = [
                    'host' => $host,
                    'origin' => $origin,
                    'urls' => [],
                ];
            }

            $grouped[$origin]['urls'][] = $url;
        }

        return $grouped;
    }
}
