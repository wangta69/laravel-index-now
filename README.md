# Index-now laravel package

## document


## Installation
```
composer require wangta69/laravel-index-now
php artisan pondol:install-index-now
```

## How to Use
```
use Pondol\IndexNow\Jobs\IndexNow;
..........
IndexNow::dispatch('https://yourdomain/path/file');
```

## INDEXNOW KEY 생성
> 아래 링크에서 키를 생성
- [키생성 url : Bing Webmaster Tools](https://www.bing.com/indexnow/getstarted)

- .env 
```
INDEXNOW_KEY=4c1b4369adb97db0a6cef150fb06f7cb
```

## config
- pondol-indexnow.php
```
<?php
return [
  'log'=>true,
  'search_engines' => [
    'bing'=>['enabled'=>true, 'endpoint'=>'api.indexnow.org'],
    'naver'=>['enabled'=>true, 'endpoint'=>'searchadvisor.naver.com/indexnow'],
    'yandex'=>['enabled'=>false, 'endpoint'=>'yandex.com/indexnow'],
  ],
];

```
- key file
> yourdomain.com/[indexnwo_key].txt 를 위치 시킵니다.

## 지원하는 API
- api.indexnow.org : for bing, yahoo
- ~~yandex.com/indexnow : for Yandex~~
- searchadvisor.naver.com/indexnow : for Naver

  
## Queue-work 설정
> 아래를 참조하여 서버를 적절하게 세팅해 주시기를 바랍니다.
- [queue:work 백그라운드 실행](https://www.onstory.fun/doc/programming/laravel/queues)

