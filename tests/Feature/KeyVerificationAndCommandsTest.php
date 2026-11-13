<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Pondol\IndexNow\Tests\TestCase;

class KeyVerificationAndCommandsTest extends TestCase
{
    public function test_it_serves_indexnow_key_route_automatically(): void
    {
        $response = $this->get('/test-indexnow-key-12345678.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame('test-indexnow-key-12345678', $response->getContent());
    }

    public function test_submit_command_with_sync_option(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $this->artisan('indexnow:submit', [
            'urls' => ['/posts/1', '/posts/2'],
            '--sync' => true,
        ])->assertSuccessful();

        Http::assertSentCount(2);
    }

    public function test_submit_command_parses_sitemap(): void
    {
        $sitemapXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://example.com/a</loc></url>
  <url><loc>https://example.com/b</loc></url>
</urlset>
XML;

        Http::fake([
            'https://example.com/sitemap.xml' => Http::response($sitemapXml, 200),
            '*' => Http::response('', 200),
        ]);

        $this->artisan('indexnow:submit', [
            '--sitemap' => 'https://example.com/sitemap.xml',
            '--sync' => true,
        ])->assertSuccessful();

        Http::assertSentCount(3); // sitemap GET + bing POST + naver POST
    }

    public function test_doctor_command_runs_successfully(): void
    {
        $this->artisan('indexnow:doctor', [
            '--skip-http' => true,
        ])->assertSuccessful();
    }
}
