<?php

namespace Pondol\IndexNow\Jobs;

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

        $search_engines = config('pondol-indexnow.search_engines');

        $scheme = parse_url($this->path, PHP_URL_SCHEME);
        if ($scheme === 'http' || $scheme === 'https') {
            $this->url = $this->path;
        } else {
            $this->url = [env('APP_URL').'/'.$this->path];
        }

        Log::info(' IndexNow Start');

        foreach ($search_engines as $key => $value) {
            if (is_array($value) && isset($value['enabled']) && $value['enabled'] === true) {
                $response = $this->query($value['endpoint']);
                if (config('pondol-indexnow.log')) {
                    Log::info($key.' IndexNow Response');
                    Log::info($response->getStatusCode());
                    // Log::info($response->getBody()->getContents());
                }
            }
        }
    }

    private function query($endpoint)
    {
        $client = new \GuzzleHttp\Client;

        $query = [
            // 'host'=> env('APP_URL'),
            'host' => parse_url($this->url, PHP_URL_HOST),
            'key' => env('INDEXNOW_KEY'),
            'keyLocation' => env('APP_URL').'/'.env('INDEXNOW_KEY').'.txt',
            'urlList' => [$this->url],
        ];

        if (config('pondol-indexnow.log')) {
            Log::info($query);
        }

        $response = $client->request('POST', $endpoint, ['form_params' => $query]); // GET 을 사용할 경우 'query' 변수에 담아 보낸다.

        return $response;
    }
}
