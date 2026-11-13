# Advanced Usage Guide

## 1. Eloquent `Indexable` Trait 활용 패턴

### 조건부 색인 및 변경 컬럼 감지

트래픽이 많은 서비스에서는 조회수(`views`)가 오를 때마다 색인 API가 호출되는 것을 막아야 합니다.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Pondol\IndexNow\Enums\IndexingAction;
use Pondol\IndexNow\Traits\Indexable;

class Post extends Model
{
    use Indexable, SoftDeletes;

    /**
     * 단건 또는 다건 URL 반환 가능
     */
    public function getIndexNowUrl(): string|array
    {
        return [
            route('posts.show', $this),
            route('posts.index'),
        ];
    }

    /**
     * 공개된 글만 색인 (단, 삭제 시에는 비공개 여부와 무관하게 삭제 알림 전송 가능)
     */
    public function shouldIndexNow(IndexingAction $action): bool
    {
        if ($action === IndexingAction::Deleted) {
            return true;
        }

        return $this->status === 'published' && $this->published_at?->isPast();
    }

    /**
     * 아래 컬럼이 변경되었을 때만 updated 이벤트에서 색인 요청
     */
    public function indexNowWatchColumns(): array
    {
        return ['title', 'slug', 'content', 'status', 'published_at'];
    }
}
```

> `SoftDeletes`를 사용하는 모델에서 `restore()`가 호출되면 자동으로 `IndexingAction::Updated` 요청이 전송됩니다.

---

## 2. Events를 활용한 실패 알림 / DB 로깅

전송 결과에 따라 두 가지 이벤트가 발행됩니다:

- `Pondol\IndexNow\Events\IndexingSucceeded`
- `Pondol\IndexNow\Events\IndexingFailed`

`EventServiceProvider` 또는 `AppServiceProvider::boot()`에서 리스너를 등록할 수 있습니다.

```php
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Pondol\IndexNow\Events\IndexingFailed;

Event::listen(function (IndexingFailed $event): void {
    // $event->result->engine     ('bing', 'naver', 'google' 등)
    // $event->result->statusCode (403, 422, 429 등)
    // $event->result->message    (오류 상세 메시지)
    // $event->result->urls       (전송 시도한 URL 배열)

    Log::channel('slack')->error('Search Engine Indexing Failed', $event->result->toArray());
});
```

---

## 3. 멀티 도메인(Multi-Domain) 환경에서 사용하기

IndexNow 프로토콜은 **`urlList`의 호스트와 `keyLocation`의 호스트가 동일해야 한다**는 엄격한 규칙이 있습니다(불일치 시 `422 Unprocessable Entity` 발생).

이 패키지는 URL 배열에 여러 도메인이 섞여 있어도 자동으로 호스트별 그룹핑을 수행합니다:

- `https://a.example.com/page` → `keyLocation: https://a.example.com/{key}.txt`
- `https://b.example.com/page` → `keyLocation: https://b.example.com/{key}.txt`

`auto_serve_key`가 `true`이고 동일한 라라벨 앱이 두 도메인을 모두 서빙한다면 별도 파일 작업 없이 양쪽 도메인 모두 자동 인증됩니다.

---

## 4. Scheduler로 Sitemap 주기적 동기화

매일 새벽 `sitemap.xml`을 읽어 전체 URL을 IndexNow에 제출하고 싶다면 `routes/console.php` (Laravel 11+)에 스케줄을 등록할 수 있습니다.

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('indexnow:submit --sitemap=https://yourdomain.com/sitemap.xml')
    ->dailyAt('03:00')
    ->environments(['production']);
```

---

## 5. 테스트 코드 작성하기 (`Http::fake()`)

외부 의존성을 제거하고 Laravel `Http` 파사드 기반으로 설계되어 있으므로, 애플리케이션 테스트에서도 간단히 모킹할 수 있습니다.

```php
use Illuminate\Support\Facades\Http;
use Pondol\IndexNow\Facades\IndexNow;

Http::fake(['*' => Http::response('', 200)]);

$results = IndexNow::force()->now()->update('/posts/1');

$this->assertTrue($results[0]->successful);
Http::assertSentCount(2);
```
