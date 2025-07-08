<?php

namespace Pondol\IndexNow\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexNow implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  protected $path;
  public function __construct($path)
  {
    // Log::info('__construct');
    // Log::info($path);
    $this->path = $path;
  }

  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle()
  {
    $this->naverIndexNow($this->path);
    $this->bingIndexNow($this->path);
    // $this->yandexIndexNow($this->path); 지원종료
  }


  private function yandexIndexNow($path) {
    $client = new \GuzzleHttp\Client();
    $endpoint = 'yandex.com/indexnow';
    
    $urlList = [env('APP_URL').'/'.$path];
    $query = [
      'host'=> env('APP_URL'),
      'key'=> env('INDEXNOW_KEY'),
      'keyLocation'=>env('APP_URL').'/'.env('INDEXNOW_KEY').'.txt',
      'urlList'=> $urlList
    ];
    
    $response = $client->request('POST', $endpoint, ['form_params' => $query]); // GET 을 사용할 경우 'query' 변수에 담아 보낸다.
    Log::info('Yandex IndexNow Status Code');
    Log::info($response->getStatusCode());
  }

  private function bingIndexNow($path) {
    $client = new \GuzzleHttp\Client();
    $endpoint = 'api.indexnow.org';
    
    $urlList = [env('APP_URL').'/'.$path];
    $query = [
      'host'=> env('APP_URL'),
      'key'=> env('INDEXNOW_KEY'),
      'keyLocation'=>env('APP_URL').'/'.env('INDEXNOW_KEY').'.txt',
      'urlList'=> $urlList
    ];

    $response = $client->request('POST', $endpoint, ['form_params' => $query]); // GET 을 사용할 경우 'query' 변수에 담아 보낸다.
    Log::info('Bing IndexNow Status Code');
    Log::info($response->getStatusCode());
  }

  private function naverIndexNow($path) {
    $client = new \GuzzleHttp\Client();
    $query = [
      'key'=> env('INDEXNOW_KEY'),
      'keyLocation'=>env('APP_URL').'/'.env('INDEXNOW_KEY').'.txt',
      'url'=> env('APP_URL').'/'.$path
    ];

    $endpoint = 'searchadvisor.naver.com/indexnow';
    $response = $client->request('GET', $endpoint, ['query' => $query]);
    Log::info('Naver IndexNow Status Code');
    Log::info($response->getStatusCode());
  }
}
