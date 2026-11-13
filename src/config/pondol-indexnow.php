<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Master Switch & Allowed Environments
    |--------------------------------------------------------------------------
    | 로컬/테스트 환경에서 실수로 검색엔진에 URL이 전송되는 것을 방지합니다.
    | 모든 환경에서 허용하려면 ['*'] 로 설정하세요.
    */
    'enabled' => env('INDEXNOW_ENABLED', true),

    'environments' => array_filter(
        explode(',', env('INDEXNOW_ENVIRONMENTS', 'production'))
    ),

    /*
    |--------------------------------------------------------------------------
    | IndexNow API Key & Key Verification
    |--------------------------------------------------------------------------
    | auto_serve_key 가 true 이면 public/{key}.txt 파일이 없어도
    | 패키지 라우트가 /{key}.txt 요청에 자동으로 응답합니다.
    */
    'key' => env('INDEXNOW_KEY'),

    'key_location' => env('INDEXNOW_KEY_LOCATION'),

    'auto_serve_key' => env('INDEXNOW_AUTO_SERVE_KEY', true),

    /*
    |--------------------------------------------------------------------------
    | Logging & HTTP Options
    |--------------------------------------------------------------------------
    */
    'log' => env('INDEXNOW_LOG', true),

    'log_channel' => env('INDEXNOW_LOG_CHANNEL'),

    'timeout' => (int) env('INDEXNOW_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'enabled' => env('INDEXNOW_QUEUE_ENABLED', true),
        'connection' => env('INDEXNOW_QUEUE_CONNECTION'),
        'name' => env('INDEXNOW_QUEUE_NAME', 'default'),
        'tries' => (int) env('INDEXNOW_QUEUE_TRIES', 3),
        'backoff' => [60, 300, 900],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Indexing API Configuration
    |--------------------------------------------------------------------------
    | key_file 은 storage/app/ 기준 상대 경로 또는 절대 경로를 지원합니다.
    */
    'google' => [
        'enabled' => env('INDEXNOW_GOOGLE_ENABLED', false),
        'key_file' => env('INDEXNOW_GOOGLE_KEY_FILE', 'google-api-key.json'),
        'action' => env('INDEXNOW_GOOGLE_ACTION', 'URL_UPDATED'),
    ],

    /*
    |--------------------------------------------------------------------------
    | IndexNow Search Engines
    |--------------------------------------------------------------------------
    | api.indexnow.org/indexnow 는 참여 중인 모든 검색엔진에 전파됩니다.
    */
    'search_engines' => [
        'bing' => [
            'enabled' => true,
            'endpoint' => 'https://api.indexnow.org/indexnow',
        ],
        'naver' => [
            'enabled' => true,
            'endpoint' => 'https://searchadvisor.naver.com/indexnow',
        ],
        'yandex' => [
            'enabled' => false,
            'endpoint' => 'https://yandex.com/indexnow',
        ],
    ],
];
