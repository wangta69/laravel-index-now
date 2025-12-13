This library is used in the production of [gilra.kr](https://www.gilra.kr) (Online Fortune Service).

# Laravel IndexNow Package

Laravel에서 **IndexNow API**를 쉽게 사용할 수 있도록 도와주는 패키지입니다.  
콘텐츠 생성·수정·삭제 시 검색엔진(Bing, Naver 등)에 URL을 즉시 전송하여 빠른 색인을 유도합니다 ⚡

---

## Installation

```bash
composer require wangta69/laravel-index-now
php artisan pondol:install-index-now
```

---

## How to Use

```php
use Pondol\IndexNow\Jobs\IndexNow;

IndexNow::dispatch('https://yourdomain.com/path/file');
```

> ✅ Queue(Job) 기반으로 동작하므로 `queue:work` 실행이 필요합니다.

---

## IndexNow Key 개요

IndexNow는 **도메인 소유 증명용 Key 파일**을 요구합니다.

- 하나의 Key로 Bing / Naver 공통 사용 가능
- Key 문자열과 동일한 이름의 `.txt` 파일을 **웹 루트(public)** 에 배치

```text
https://yourdomain.com/{INDEXNOW_KEY}.txt
```

---

## Bing IndexNow Key 발급 방법

### 1️⃣ Bing IndexNow 페이지 접속

https://www.bing.com/indexnow/getstarted

### 2️⃣ Key 생성

`Generate API Key` 클릭 → Key 발급

### 3️⃣ Key 파일 생성

`/public/{INDEXNOW_KEY}.txt`

내용:

```
{INDEXNOW_KEY}
```

### 4️⃣ .env 설정

```env
INDEXNOW_KEY=your_indexnow_key_here
```

### 5️⃣ 브라우저 접근 확인

```
https://yourdomain.com/{INDEXNOW_KEY}.txt
```

---

## Naver IndexNow Key 발급 방법

✅ **Bing에서 발급한 Key를 그대로 사용**

1. https://searchadvisor.naver.com 접속
2. 사이트 등록 및 소유 확인
3. Bing Key 파일 유지

---

## Configuration

```php
<?php

return [
    'log' => true,
    'search_engines' => [
        'bing' => ['enabled' => true, 'endpoint' => 'api.indexnow.org'],
        'naver' => ['enabled' => true, 'endpoint' => 'searchadvisor.naver.com/indexnow'],
        'yandex' => ['enabled' => false, 'endpoint' => 'yandex.com/indexnow'],
    ],
];
```

---

## 지원 API

- ✅ Bing / Yahoo
- ✅ Naver
- ❌ Yandex

---

## 실행하기

실행하는 방법에는 2가지 방법이 있습니다. 하나는 database(job 테이블에 저장후 비동기식 실행)를 이용하는 방법(추천) 동기식 실행 방법이 있습니다.

### 동기식 실행방법

.env 파일을 아래와 같이 설정하시면 동기식으로 진행됩니다.

```
QUEUE_CONNECTION=sync
```

### 비동기식 실행방법

.env 의 default 세팅입니다.

```
QUEUE_CONNECTION=database
```

이후 콘솔에서 아래와 같이 명령을 입력하면 database에 저장된 내용이 실행됩니다.

```bash
php artisan queue:work
```

#### 비동기식 실행방법 백그라운드 실행

리눅스의 supervisor(추천) 이나 nohub을 사용하시면 됩니다. 이곳에서는 관련 사용법은 별도로 정리하지 않겠습니다.

---

## License

MIT
