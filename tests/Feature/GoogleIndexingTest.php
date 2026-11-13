<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Pondol\IndexNow\Facades\IndexNow;
use Pondol\IndexNow\Tests\TestCase;

class GoogleIndexingTest extends TestCase
{
    private string $tempKeyPath;

    protected function setUp(): void
    {
        parent::setUp();

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            $this->markTestSkipped('OpenSSL RSA key generation is not available in this environment.');
        }

        openssl_pkey_export($resource, $privateKey);

        $this->tempKeyPath = sys_get_temp_dir().'/pondol-test-google-key-'.uniqid().'.json';
        file_put_contents($this->tempKeyPath, json_encode([
            'client_email' => 'indexing-bot@test-project.iam.gserviceaccount.com',
            'private_key' => $privateKey,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ], JSON_THROW_ON_ERROR));

        $this->app['config']->set('pondol-indexnow.google.enabled', true);
        $this->app['config']->set('pondol-indexnow.google.key_file', $this->tempKeyPath);
        $this->app['config']->set('pondol-indexnow.search_engines.bing.enabled', false);
        $this->app['config']->set('pondol-indexnow.search_engines.naver.enabled', false);
    }

    protected function tearDown(): void
    {
        if (isset($this->tempKeyPath) && is_file($this->tempKeyPath)) {
            @unlink($this->tempKeyPath);
        }

        parent::tearDown();
    }

    public function test_it_authenticates_with_jwt_and_sends_delete_action(): void
    {
        Cache::flush();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'fake-google-access-token',
                'expires_in' => 3600,
            ], 200),
            'https://indexing.googleapis.com/v3/urlNotifications:publish' => Http::response([
                'urlNotificationMetadata' => ['url' => 'https://example.com/posts/99'],
            ], 200),
        ]);

        $results = IndexNow::now()->delete('/posts/99');

        $this->assertCount(1, $results);
        $this->assertSame('google', $results[0]->engine);
        $this->assertTrue($results[0]->successful);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://indexing.googleapis.com/v3/urlNotifications:publish'
                && $request->hasHeader('Authorization', 'Bearer fake-google-access-token')
                && $request['url'] === 'https://example.com/posts/99'
                && $request['type'] === 'URL_DELETED';
        });

        // 두 번째 호출에서는 캐싱된 토큰 재사용 확인 (OAuth 호출 1회 유지)
        IndexNow::now()->update('/posts/100');
        Http::assertSentCount(3); // oauth(1) + publish(2)
    }
}
