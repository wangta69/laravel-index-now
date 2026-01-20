# Laravel IndexNow & Google Indexing Package

![Production Used](https://img.shields.io/badge/Production-gilra.kr-blue)

This library is successfully used in production at **[gilra.kr](https://www.gilra.kr)** (Online Fortune Service).  
이 라이브러리는 **[길라(gilra.kr)](https://www.gilra.kr)** 실서버에서 안정적으로 운영되고 있습니다.

---

Laravel에서 **IndexNow API**와 **Google Indexing API**를 쉽고 통합적으로 사용할 수 있도록 도와주는 패키지입니다.  
콘텐츠 생성·수정·삭제 시 검색엔진(Google, Bing, Naver 등)에 URL을 즉시 전송하여 **빠른 색인(Fast Indexing)**을 유도합니다 ⚡

---

## Installation

```bash
composer require wangta69/laravel-index-now
php artisan pondol:install-index-now
```

---

## Configuration

`config/pondol-indexnow.php` 파일을 통해 각 검색엔진 사용 여부를 설정할 수 있습니다.

```php
<?php

return [
    'log' => true,

    // ✅ Google Indexing API 설정 (New)
    'google' => [
        'enabled' => true, // true로 설정 시 구글 전송 활성화
        'key_file' => 'google-api-key.json', // storage/app 폴더 내의 파일명
        'action' => 'URL_UPDATED' // or 'URL_DELETED'
    ],

    // ✅ IndexNow 설정 (Bing, Naver 등)
    'search_engines' => [
        'bing' => ['enabled' => true, 'endpoint' => 'api.indexnow.org'],
        'naver' => ['enabled' => true, 'endpoint' => 'searchadvisor.naver.com/indexnow'],
        'yandex' => ['enabled' => false, 'endpoint' => 'yandex.com/indexnow'],
    ],
];
```

---

## How to Use

URL을 전송하는 방법은 매우 간단합니다.

```php
use Pondol\IndexNow\Jobs\IndexNow;

// 구글과 IndexNow(Bing, Naver) 모두에게 전송됩니다 (Config 설정에 따라)
IndexNow::dispatch('https://yourdomain.com/path/file');
```

> ✅ Queue(Job) 기반으로 동작하므로 `queue:work` 실행이 필요합니다.

---

## 🚀 Google Indexing API 설정 가이드 (중요)

구글은 별도의 인증 절차가 필요합니다. 아래 4단계를 반드시 진행해야 합니다.

### 1️⃣ Google Cloud 프로젝트 및 API 활성화

1. [Google Cloud Console](https://console.cloud.google.com/)에 접속하여 새 프로젝트를 생성합니다.
2. 좌측 메뉴에서 **API 및 서비스 > 라이브러리**로 이동합니다.
3. 검색창에 **`Web Search Indexing API`**를 검색합니다.
4. **사용(Enable)** 버튼을 눌러 API를 활성화합니다. **(필수)**

### 2️⃣ 서비스 계정 생성 및 키 다운로드

1. **IAM 및 관리자 > 서비스 계정**으로 이동합니다.
2. **서비스 계정 만들기**를 클릭합니다.
   - 이름: 원하는 대로 입력 (예: `indexing-bot`)
   - 역할(Role): **소유자(Owner)** 선택 (권장)
3. 생성된 서비스 계정 목록에서 **이메일 주소**(`~@~.iam.gserviceaccount.com`)를 복사해 둡니다. (3단계에서 사용)
4. 해당 계정의 **키(Keys)** 탭으로 이동합니다.
5. **키 추가 > 새 키 만들기 > JSON**을 선택하여 다운로드합니다.

### 3️⃣ 구글 서치 콘솔(Search Console) 권한 부여

⚠️ **이 단계를 건너뛰면 `403 Forbidden` 에러가 발생합니다.**

1. [구글 서치 콘솔](https://search.google.com/search-console)에 접속하여 연동할 사이트를 선택합니다.
2. **설정(Settings) > 사용자 및 권한(Users and permissions)**으로 이동합니다.
3. **사용자 추가(Add User)**를 클릭합니다.
4. 아까 복사한 **서비스 계정 이메일**을 입력합니다.
5. 권한을 반드시 **소유자(Owner)**로 설정하고 추가합니다.

### 4️⃣ 키 파일 업로드 및 설정

1. 다운로드한 JSON 파일의 이름을 `google-api-key.json` (또는 원하는 이름)으로 변경합니다.
2. 라라벨 프로젝트의 **`storage/app/`** 폴더 안에 파일을 업로드합니다.
3. `config/pondol-indexnow.php` 파일에서 `google.enabled`를 `true`로 변경하고 파일명을 확인합니다.

---

## 🌏 IndexNow Key 설정 가이드 (Bing & Naver)

IndexNow는 **도메인 소유 증명용 Key 파일**을 요구합니다. 하나의 Key로 Bing과 Naver에서 공통으로 사용할 수 있습니다.

### 1️⃣ Key 발급 (Bing)

1. [Bing IndexNow 페이지](https://www.bing.com/indexnow/getstarted) 접속
2. `Generate API Key` 클릭하여 Key 문자열 복사

### 2️⃣ Key 파일 생성 및 업로드

웹 루트(public) 폴더에 Key 값과 동일한 이름의 텍스트 파일을 생성합니다.

- 파일명: `your_key_string.txt`
- 파일 내용: `your_key_string`
- 위치: `/public/` 폴더

### 3️⃣ .env 설정

```env
INDEXNOW_KEY=your_key_string_here
```

### 4️⃣ 확인

브라우저에서 `https://yourdomain.com/{INDEXNOW_KEY}.txt` 접속 시 내용이 보여야 합니다.

> **Naver 설정:** Bing에서 발급받은 Key 파일을 그대로 두시고, [네이버 서치어드바이저](https://searchadvisor.naver.com)에 사이트 소유 확인만 되어 있으면 자동으로 연동됩니다.

---

## 지원 API

- ✅ **Google** (Indexing API)
- ✅ **Bing / Yahoo** (IndexNow)
- ✅ **Naver** (IndexNow)
- ❌ Yandex (설정 가능하나 기본값 비활성)

---

## 실행하기 (Queue Worker)

전송 속도 저하를 막기 위해 비동기 처리를 권장합니다.

### 추천: 비동기식 (Database Queue)

`.env` 파일 설정:

```env
QUEUE_CONNECTION=database
```

Queue 실행 (Supervisor 등을 이용해 백그라운드 실행 권장):

```bash
php artisan queue:work
```

### 참고: 동기식 (개발 테스트용)

즉시 실행되어 결과를 바로 확인할 수 있지만, 사용자 응답이 느려질 수 있습니다.

```env
QUEUE_CONNECTION=sync
```

---

## License

MIT
