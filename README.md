# Index-now laravel package

## document



## Installation
```
composer require wangta69/laravel-index-now
```

## How to Use
```
use Pondol\IndexNow\Jobs\IndexNow;
..........
 IndexNow::dispatch('https://yourdomain/path/file');
```

## 지원하는 API
- api.indexnow.org : for bing, yahoo
- yandex.com/indexnow : for Yandex
- searchadvisor.naver.com/indexnow : for Naver
  
  
## Queue-work 설정
> 아래를 참조하여 서버를 적절하게 세팅해 주시기를 바랍니다.

- [queue:work 백그라운드 실행](https://www.onstory.fun/doc/programming/laravel/queues)

