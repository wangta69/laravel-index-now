<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Pondol\IndexNow\Data\IndexingResult;
use Pondol\IndexNow\Enums\IndexingAction;
use RuntimeException;
use Throwable;

class GoogleIndexingClient
{
    private const INDEXING_ENDPOINT = 'https://indexing.googleapis.com/v3/urlNotifications:publish';

    private const DEFAULT_TOKEN_URI = 'https://oauth2.googleapis.com/token';

    private const INDEXING_SCOPE = 'https://www.googleapis.com/auth/indexing';

    private const CONCURRENT_CHUNK_SIZE = 10;

    /**
     * Google Indexing API로 URL 알림을 전송합니다.
     *
     * @param  array<int, string>  $urls
     * @return array<int, IndexingResult>
     */
    public function send(array $urls, ?IndexingAction $action = null): array
    {
        if ($urls === [] || ! config('pondol-indexnow.google.enabled', false)) {
            return [];
        }

        $action ??= IndexingAction::resolve(config('pondol-indexnow.google.action'));
        $timeout = (int) config('pondol-indexnow.timeout', 10);

        try {
            $accessToken = $this->getAccessToken($timeout);
        } catch (Throwable $e) {
            return [
                IndexingResult::failure(
                    engine: 'google',
                    statusCode: null,
                    urls: $urls,
                    message: $e->getMessage(),
                ),
            ];
        }

        if (count($urls) === 1) {
            return [$this->sendSingle($urls[0], $action, $accessToken, $timeout)];
        }

        $results = [];

        foreach (array_chunk($urls, self::CONCURRENT_CHUNK_SIZE) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk, $action, $accessToken, $timeout) {
                foreach ($chunk as $index => $url) {
                    $pool->as((string) $index)
                        ->withToken($accessToken)
                        ->timeout($timeout)
                        ->acceptJson()
                        ->asJson()
                        ->post(self::INDEXING_ENDPOINT, [
                            'url' => $url,
                            'type' => $action->value,
                        ]);
                }
            });

            foreach ($chunk as $index => $url) {
                $response = $responses[(string) $index] ?? null;
                $results[] = $this->parseResponse($url, $response);
            }
        }

        return $results;
    }

    /**
     * 서비스 계정 JSON 파일 경로를 탐색합니다.
     * (절대 경로 -> storage/app -> storage/app/private 순으로 호환 지원)
     */
    public function resolveKeyPath(): ?string
    {
        $keyFile = trim((string) config('pondol-indexnow.google.key_file', ''));
        if ($keyFile === '') {
            return null;
        }

        $candidates = [
            $keyFile,
            storage_path('app/'.ltrim($keyFile, '/')),
            storage_path('app/private/'.ltrim($keyFile, '/')),
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * 서비스 계정 JSON 파일을 읽고 필수 필드를 검증합니다.
     *
     * @return array{client_email: string, private_key: string, token_uri: string}
     */
    public function loadCredentials(): array
    {
        $keyPath = $this->resolveKeyPath();
        if ($keyPath === null) {
            $configured = (string) config('pondol-indexnow.google.key_file', '');
            throw new RuntimeException("Google API key file not found or unreadable: {$configured}");
        }

        $raw = file_get_contents($keyPath);
        if ($raw === false) {
            throw new RuntimeException("Failed to read Google API key file: {$keyPath}");
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            throw new RuntimeException("Invalid JSON format in Google API key file: {$keyPath}");
        }

        if (empty($data['client_email']) || empty($data['private_key'])) {
            throw new RuntimeException('Google API key JSON must contain client_email and private_key.');
        }

        return [
            'client_email' => (string) $data['client_email'],
            'private_key' => (string) $data['private_key'],
            'token_uri' => (string) ($data['token_uri'] ?? self::DEFAULT_TOKEN_URI),
        ];
    }

    /**
     * OAuth2 Access Token을 발급받거나 캐시에서 가져옵니다.
     */
    public function getAccessToken(int $timeout = 10, bool $forceRefresh = false): string
    {
        $credentials = $this->loadCredentials();
        $cacheKey = 'pondol_indexnow_google_token_'.md5($credentials['client_email']);

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $jwt = $this->createSignedJwt($credentials);

        $response = Http::asForm()
            ->timeout($timeout)
            ->post($credentials['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (! $response->successful()) {
            $error = $response->json('error_description')
                ?? $response->json('error')
                ?? $response->body();
            throw new RuntimeException("Google OAuth token request failed ({$response->status()}): {$error}");
        }

        $accessToken = (string) $response->json('access_token', '');
        if ($accessToken === '') {
            throw new RuntimeException('Google OAuth response did not include an access_token.');
        }

        $expiresIn = max(60, (int) $response->json('expires_in', 3600) - 300);
        Cache::put($cacheKey, $accessToken, $expiresIn);

        return $accessToken;
    }

    protected function sendSingle(
        string $url,
        IndexingAction $action,
        string $accessToken,
        int $timeout,
    ): IndexingResult {
        try {
            $response = Http::withToken($accessToken)
                ->timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post(self::INDEXING_ENDPOINT, [
                    'url' => $url,
                    'type' => $action->value,
                ]);

            return $this->parseResponse($url, $response);
        } catch (Throwable $e) {
            return IndexingResult::failure(
                engine: 'google',
                statusCode: null,
                urls: [$url],
                message: $e->getMessage(),
            );
        }
    }

    protected function parseResponse(string $url, mixed $response): IndexingResult
    {
        if ($response instanceof Throwable) {
            return IndexingResult::failure(
                engine: 'google',
                statusCode: null,
                urls: [$url],
                message: $response->getMessage(),
            );
        }

        if (! $response instanceof Response) {
            return IndexingResult::failure(
                engine: 'google',
                statusCode: null,
                urls: [$url],
                message: 'Unexpected response from Google Indexing API.',
            );
        }

        $status = $response->status();

        if ($response->successful()) {
            return IndexingResult::success(
                engine: 'google',
                statusCode: $status,
                urls: [$url],
                message: 'Google Indexing requested successfully.',
                rawResponse: $response->body(),
            );
        }

        $errorMessage = $response->json('error.message') ?? $response->body();

        return IndexingResult::failure(
            engine: 'google',
            statusCode: $status,
            urls: [$url],
            message: "HTTP {$status}: {$errorMessage}",
            rawResponse: $response->body(),
        );
    }

    /**
     * RS256 서명된 JWT를 생성합니다.
     *
     * @param  array{client_email: string, private_key: string, token_uri: string}  $credentials
     */
    protected function createSignedJwt(array $credentials): string
    {
        $now = time();

        $header = $this->base64UrlEncode((string) json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $claims = $this->base64UrlEncode((string) json_encode([
            'iss' => $credentials['client_email'],
            'scope' => self::INDEXING_SCOPE,
            'aud' => $credentials['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $signingInput = "{$header}.{$claims}";
        $privateKey = openssl_pkey_get_private($credentials['private_key']);

        if ($privateKey === false) {
            throw new RuntimeException('Failed to parse Google service account private key.');
        }

        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('Failed to sign JWT with Google service account private key.');
        }

        return "{$signingInput}.".$this->base64UrlEncode($signature);
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
