<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Pondol\IndexNow\Events\IndexingFailed;
use Pondol\IndexNow\Events\IndexingSucceeded;
use Pondol\IndexNow\Facades\IndexNow;
use Pondol\IndexNow\Jobs\IndexNow as IndexNowJob;
use Pondol\IndexNow\Services\IndexNowClient;
use Pondol\IndexNow\Support\UrlNormalizer;
use Pondol\IndexNow\Tests\TestCase;

class IndexNowTest extends TestCase
{
    public function test_it_normalizes_and_groups_urls_by_host(): void
    {
        $urls = UrlNormalizer::normalize([
            '/posts/1',
            'posts/1', // 중복 제거 확인
            'https://example.com/posts/2',
            'https://sub.example.com/landing',
            'not-a-valid-url://??',
        ]);

        $this->assertSame([
            'https://example.com/posts/1',
            'https://example.com/posts/2',
            'https://sub.example.com/landing',
        ], $urls);

        $grouped = UrlNormalizer::groupByHost($urls);
        $this->assertCount(2, $grouped);
        $this->assertSame(['https://example.com/posts/1', 'https://example.com/posts/2'], $grouped['https://example.com']['urls']);
        $this->assertSame(['https://sub.example.com/landing'], $grouped['https://sub.example.com']['urls']);
    }

    public function test_it_normalizes_legacy_endpoints(): void
    {
        $client = app(IndexNowClient::class);

        $this->assertSame('https://api.indexnow.org/indexnow', $client->normalizeEndpoint('api.indexnow.org'));
        $this->assertSame('https://searchadvisor.naver.com/indexnow', $client->normalizeEndpoint('searchadvisor.naver.com/indexnow'));
        $this->assertSame('https://yandex.com/indexnow', $client->normalizeEndpoint('https://yandex.com/indexnow'));
    }

    public function test_it_submits_urls_to_enabled_engines_and_fires_events(): void
    {
        Http::fake([
            '*' => Http::response('', 200),
        ]);
        Event::fake([IndexingSucceeded::class, IndexingFailed::class]);

        $results = IndexNow::now()->update(['/posts/1', '/posts/2']);

        $this->assertCount(2, $results); // bing + naver
        $this->assertTrue($results[0]->successful);
        $this->assertTrue($results[1]->successful);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.indexnow.org/indexnow'
                && $request['host'] === 'example.com'
                && $request['key'] === 'test-indexnow-key-12345678'
                && $request['keyLocation'] === 'https://example.com/test-indexnow-key-12345678.txt'
                && $request['urlList'] === [
                    'https://example.com/posts/1',
                    'https://example.com/posts/2',
                ];
        });

        Event::assertDispatched(IndexingSucceeded::class, 2);
        Event::assertNotDispatched(IndexingFailed::class);
    }

    public function test_it_fires_failed_event_on_http_error(): void
    {
        Http::fake([
            '*' => Http::response('Invalid key', 403),
        ]);
        Event::fake([IndexingSucceeded::class, IndexingFailed::class]);

        $results = IndexNow::now()->update('/posts/1');

        $this->assertFalse($results[0]->successful);
        $this->assertSame(403, $results[0]->statusCode);
        Event::assertDispatched(IndexingFailed::class, 2);
    }

    public function test_it_skips_non_allowed_environments_unless_forced(): void
    {
        Http::fake();
        $this->app['config']->set('app.env', 'local');

        $skipped = IndexNow::now()->update('/posts/1');
        $this->assertSame([], $skipped);
        Http::assertNothingSent();

        $forced = IndexNow::force()->now()->update('/posts/1');
        $this->assertCount(2, $forced);
        Http::assertSentCount(2);
    }

    public function test_it_dispatches_to_queue_and_preserves_legacy_job_usage(): void
    {
        Queue::fake();
        $this->app['config']->set('pondol-indexnow.queue.enabled', true);

        IndexNow::update('/posts/10');
        IndexNowJob::dispatch('https://example.com/legacy');

        Queue::assertPushed(IndexNowJob::class, 2);
    }
}
