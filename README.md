# Laravel IndexNow & Google Indexing Package

![Production Used](https://img.shields.io/badge/Production-gilra.kr-blue)

This library is successfully used in production at **[gilra.kr](https://www.gilra.kr)** (Online Fortune Service).  
이 라이브러리는 **[길라(gilra.kr)](https://www.gilra.kr)** 실서버에서 안정적으로 운영되고 있습니다.

---

Laravel에서 **IndexNow API** (Bing, Naver, Yandex, Seznam, Yep, Amazon 등)와 **Google Indexing API**를 통합 지원하는 초경량 패키지입니다.  
외부 SDK(`google/apiclient`) 없이 **순수 Laravel `Http` 파사드와 OpenSSL**만으로 동작하며, Facade·Eloquent Trait·Artisan 진단 도구까지 모두 제공합니다.

---

## Documentation

상세한 설정 방법과 검색엔진별 주의사항은 아래 문서를 참고하세요:

- 📘 **[Google Indexing API Setup Guide](docs/google-indexing.md)** — Google Cloud 프로젝트, JSON 키 발급, Search Console 권한 설정(403 해결법)
- 🌏 **[Search Engines & Naver Guide](docs/search-engines.md)** — 네이버 서치어드바이저 주의사항, 글로벌 IndexNow 참여 엔진 목록
- 🛠️ **[Advanced Usage Guide](docs/advanced-usage.md)** — `Indexable` Trait 고급 패턴, Events 알림 연동, 멀티 도메인, Scheduler

---

## Requirements

- PHP `^8.2` (with `ext-openssl`, `ext-json`)
- Laravel `^11.0 | ^12.0 | ^13.0`

---

## Installation

```bash
composer require wangta69/laravel-index-now
php artisan pondol:install-index-now
```

설치 마법사가 실행되면:

1. `config/pondol-indexnow.php` 설정 파일을 생성합니다.
2. `INDEXNOW_KEY`가 없으면 32자 랜덤 키를 자동 생성해 `.env`에 등록합니다.
3. 기본값(`auto_serve_key = true`)으로 동작하므로 `public/{key}.txt` 파일을 직접 만들지 않아도 `https://yourdomain.com/{key}.txt` 요청에 자동 응답합니다.
4. 실서버(`APP_URL`이 실제 도메인인 경우)에서는 홈페이지 URL로 **최초 활성화 핑(Initial Ping)**을 바로 전송해 Bing/Naver에 IndexNow를 즉시 등록할 수 있습니다. (`--init` 옵션으로 질문 없이 즉시 전송 가능)

### Bing Webmaster Tools에 "IndexNow 미설정"으로 표시될 때

IndexNow는 최초 1건 이상의 URL이 제출되어야 검색엔진이 키 파일을 검증하고 활성화됩니다. 실서버 배포 후 아래 명령어로 홈페이지 URL을 1회 전송하면 활성화가 완료됩니다:

```bash
php artisan indexnow:submit / --sync
```

설치 후 아래 명령어로 설정 상태를 한 번에 점검할 수 있습니다:

```bash
php artisan indexnow:doctor
```

---

## Configuration

`.env` 파일 예시:

```env
INDEXNOW_ENABLED=true
INDEXNOW_ENVIRONMENTS=production
INDEXNOW_KEY=your_32_char_key_here
INDEXNOW_AUTO_SERVE_KEY=true

# Queue 설정
INDEXNOW_QUEUE_ENABLED=true
INDEXNOW_QUEUE_NAME=default

# Google Indexing API (사용 시 true)
INDEXNOW_GOOGLE_ENABLED=false
INDEXNOW_GOOGLE_KEY_FILE=google-api-key.json
```

> **로컬 오발송 방지:** `environments` 기본값이 `['production']`이므로 로컬/테스트 환경에서는 실수로 검색엔진에 URL이 전송되지 않습니다.

---

## How to Use

### 1. Facade 사용 (권장)

```php
use Pondol\IndexNow\Facades\IndexNow;

// 단건 / 다건 / 상대경로 모두 지원
IndexNow::update('https://yourdomain.com/posts/1');
IndexNow::update(['/posts/1', '/posts/2']);

// 삭제 알림 (Google Indexing API에 URL_DELETED 전송)
IndexNow::delete('/posts/1');

// 큐를 타지 않고 즉시 실행 + 결과 확인
$results = IndexNow::now()->update('/posts/1');

// 로컬 환경에서 강제 테스트
$results = IndexNow::force()->now()->update('https://yourdomain.com/posts/1');
```

### 2. Eloquent 모델 자동 색인 (`Indexable` Trait)

모델에 `Indexable` Trait을 붙이면 `created`, `updated`, `deleted`, `restored` 이벤트에 맞춰 자동으로 색인을 요청합니다.  
👉 자세한 활용법은 **[Advanced Usage Guide](docs/advanced-usage.md)**를 참고하세요.

```php
use Illuminate\Database\Eloquent\Model;
use Pondol\IndexNow\Enums\IndexingAction;
use Pondol\IndexNow\Traits\Indexable;

class Post extends Model
{
    use Indexable;

    public function getIndexNowUrl(): string
    {
        return route('posts.show', $this);
    }

    public function shouldIndexNow(IndexingAction $action): bool
    {
        return $this->status === 'published';
    }

    public function indexNowWatchColumns(): array
    {
        return ['title', 'content', 'status'];
    }
}
```

### 3. 기존 Queue Job 방식 (하위 호환)

```php
use Pondol\IndexNow\Jobs\IndexNow;

IndexNow::dispatch('https://yourdomain.com/path/file');
```

### 4. Artisan CLI 도구

```bash
# 설정, 키 서빙 URL, Google OAuth 토큰 자가 진단
php artisan indexnow:doctor

# 특정 URL 수동 전송
php artisan indexnow:submit https://yourdomain.com/posts/1 --sync

# sitemap.xml 전체 일괄 전송 (sitemapindex 지원)
php artisan indexnow:submit --sitemap=https://yourdomain.com/sitemap.xml
```

---

## Quick Setup for Search Engines

- **IndexNow (Bing, Naver, Yandex 등):** `php artisan pondol:install-index-now` 실행만으로 키 생성 및 라우트 서빙이 자동 설정됩니다. 별도 사이트 등록 없이도 API 전송이 가능하지만, 네이버 색인 현황 확인과 사이트맵 제출을 위해 서치어드바이저 등록을 권장합니다. 👉 **[Naver & Search Engines Guide](docs/search-engines.md)**
- **Google Indexing API:** 서비스 계정 JSON 키를 `storage/app/google-api-key.json`에 넣고 Search Console 소유자 권한을 부여한 뒤 `INDEXNOW_GOOGLE_ENABLED=true`로 설정하세요. 👉 **[Google Setup Guide](docs/google-indexing.md)**

---

## Events

전송 결과에 따라 이벤트가 발행되므로 Slack 알림이나 실패 로깅을 손쉽게 연결할 수 있습니다.

- `Pondol\IndexNow\Events\IndexingSucceeded` (`$event->result`, `$event->action`)
- `Pondol\IndexNow\Events\IndexingFailed` (`$event->result`, `$event->action`)

---

## Upgrading from v1.x

- `composer.json` 요구사항이 `PHP ^8.2`, `Laravel ^11.0|^12.0|^13.0`으로 상향되었으며 `google/apiclient` 의존성이 제거되었습니다.
- 기존 `Pondol\IndexNow\Jobs\IndexNow::dispatch($url)` 코드는 수정 없이 그대로 호환됩니다.
- 신규 옵션(`enabled`, `environments`, `key`, `auto_serve_key`, `queue`)을 사용하려면 설정 파일을 다시 퍼블리시하세요:
  ```bash
  php artisan vendor:publish --tag=pondol-indexnow-config --force
  ```

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

---

## License

MIT
