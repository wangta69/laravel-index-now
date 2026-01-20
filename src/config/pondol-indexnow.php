<?php

return [
    'log' => true,
    // Google Indexing API 설정 추가
    'google' => [
        'enabled' => false, // true로 변경하면 작동
        'key_file' => 'google-api-key.json', // storage/app 폴더 내의 파일명
        'action' => 'URL_UPDATED', // or 'URL_DELETED'
    ],
    'search_engines' => [
        'bing' => ['enabled' => true, 'endpoint' => 'api.indexnow.org'],
        'naver' => ['enabled' => true, 'endpoint' => 'searchadvisor.naver.com/indexnow'],
        'yandex' => ['enabled' => false, 'endpoint' => 'yandex.com/indexnow'],
    ],
];
