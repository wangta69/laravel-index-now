<?php

namespace Pondol\IndexNow\Jobs;

use Google\Client as GoogleClient;
use Google\Service\Indexing as GoogleIndexing;
use Google\Service\Indexing\UrlNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexNow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $path;

    protected $url;

    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->resolveUrl(); // URL 정규화 로직 분리

        Log::info('[Pondol/IndexNow] Job Started for: '.$this->url);

        // 1. 기존 IndexNow 실행 (Bing, Naver 등)
        $this->processIndexNow();

        // 2. Google Indexing API 실행 (설정 확인)
        if (config('pondol-indexnow.google.enabled')) {
            $this->processGoogleIndexing();
        }
    }

    /**
     * URL이 전체 경로인지 상대 경로인지 확인하여 완전한 URL로 변환
     */
    private function resolveUrl()
    {
        $scheme = parse_url($this->path, PHP_URL_SCHEME);
        if ($scheme === 'http' || $scheme === 'https') {
            $this->url = $this->path;
        } else {
            // env('APP_URL') 끝에 슬래시 처리를 안전하게
            $baseUrl = rtrim(env('APP_URL'), '/');
            $path = ltrim($this->path, '/');
            $this->url = $baseUrl.'/'.$path;
        }
    }

    /**
     * Google Indexing API 처리
     */
    private function processGoogleIndexing()
    {
        try {
            $keyFile = config('pondol-indexnow.google.key_file');
            $keyPath = storage_path('app/'.$keyFile);

            if (! file_exists($keyPath)) {
                Log::error('[Pondol/IndexNow] Google API Key file not found at: '.$keyPath);

                return;
            }

            $client = new GoogleClient;
            $client->setAuthConfig($keyPath);
            $client->addScope(GoogleIndexing::INDEXING);

            $service = new GoogleIndexing($client);
            $urlNotification = new UrlNotification;
            $urlNotification->setUrl($this->url);
            $urlNotification->setType(config('pondol-indexnow.google.action', 'URL_UPDATED'));

            $service->urlNotifications->publish($urlNotification);

            if (config('pondol-indexnow.log')) {
                Log::info('[Pondol/IndexNow] Google Indexing Requested Successfully');
            }

        } catch (\Exception $e) {
            Log::error('[Pondol/IndexNow] Google Indexing Error: '.$e->getMessage());
        }
    }

    /**
     * 기존 IndexNow 로직
     */
    private function processIndexNow()
    {
        $search_engines = config('pondol-indexnow.search_engines');

        foreach ($search_engines as $key => $value) {
            if (is_array($value) && isset($value['enabled']) && $value['enabled'] === true) {
                try {
                    $response = $this->query($value['endpoint']);
                    if (config('pondol-indexnow.log')) {
                        Log::info('[Pondol/IndexNow] '.$key.' Response: '.$response->getStatusCode());
                    }
                } catch (\Exception $e) {
                    Log::error('[Pondol/IndexNow] '.$key.' Error: '.$e->getMessage());
                }
            }
        }
    }

    private function query($endpoint)
    {
        $client = new \GuzzleHttp\Client;

        // APP_URL이 http/https가 아닐 경우 parse_url이 실패할 수 있어 방어 코드 필요하지만
        // 위 resolveUrl()에서 처리했으므로 $this->url은 정상적인 URL임.

        $host = parse_url($this->url, PHP_URL_HOST);

        // 키 파일 위치도 설정으로 뺄 수 있으면 좋으나 일단 기존 로직 유지
        $keyLocation = rtrim(env('APP_URL'), '/').'/'.env('INDEXNOW_KEY').'.txt';

        $query = [
            'host' => $host,
            'key' => env('INDEXNOW_KEY'),
            'keyLocation' => $keyLocation,
            'urlList' => [$this->url],
        ];

        if (config('pondol-indexnow.log')) {
            // Log::info($query); // 너무 길어서 생략 가능
        }

        // IndexNow API는 보통 JSON body나 Query String보다는 JSON Body 전송을 권장하지만
        // 기존 코드(form_params)가 작동한다면 유지, 혹시 안되면 'json' => $query 로 변경 고려
        $response = $client->post($endpoint, [
            'json' => $query, // IndexNow는 JSON 형식을 권장합니다. (기존 form_params -> json 변경 추천)
            'http_errors' => false, // 400/500 에러 시 예외 발생 대신 응답 객체 반환
        ]);

        return $response;
    }
}
