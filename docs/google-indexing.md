# Google Indexing API Setup Guide

Google은 IndexNow 프로토콜에 참여하지 않으므로, **Google Cloud 서비스 계정(JSON Key)**을 통한 별도 OAuth2 인증이 필요합니다.  
이 패키지는 무거운 `google/apiclient` 없이 순수 PHP OpenSSL과 Laravel `Http` 클라이언트로 인증을 처리합니다.

---

## 1. Google Cloud 프로젝트 및 API 활성화

1. [Google Cloud Console](https://console.cloud.google.com/)에 접속하여 프로젝트를 선택하거나 새 프로젝트를 생성합니다.
2. 좌측 메뉴에서 **API 및 서비스 > 라이브러리(Library)**로 이동합니다.
3. **`Web Search Indexing API`**를 검색합니다.
4. **사용(Enable)** 버튼을 클릭하여 API를 활성화합니다.

---

## 2. 서비스 계정(Service Account) 생성 및 JSON 키 다운로드

1. **IAM 및 관리자 > 서비스 계정(Service Accounts)**으로 이동합니다.
2. **서비스 계정 만들기**를 클릭합니다.
   - 이름: 원하는 이름 입력 (예: `laravel-indexing-bot`)
   - 역할(Role): **소유자(Owner)** 또는 기본 설정 유지
3. 생성된 서비스 계정의 **이메일 주소**(`xxx@xxx.iam.gserviceaccount.com`)를 복사해 둡니다. _(3단계에서 필수 사용)_
4. 해당 서비스 계정을 클릭하고 **키(Keys)** 탭으로 이동합니다.
5. **키 추가 > 새 키 만들기 > JSON**을 선택하여 키 파일을 다운로드합니다.

---

## 3. Google Search Console 권한 부여 (⚠️ 403 에러 방지 필수)

> 이 단계를 누락하면 Google API 호출 시 `403 Forbidden: Permission denied. Failed to verify the URL ownership.` 오류가 발생합니다.

1. [Google Search Console](https://search.google.com/search-console)에 접속하여 색인할 사이트(속성)를 선택합니다.
2. 좌측 하단 **설정(Settings) > 사용자 및 권한(Users and permissions)**으로 이동합니다.
3. **사용자 추가(Add user)**를 클릭합니다.
4. 2단계에서 복사한 **서비스 계정 이메일**을 입력합니다.
5. 권한을 반드시 **소유자(Owner)**로 지정하고 추가합니다.

---

## 4. 라라벨 프로젝트에 키 적용 및 검증

1. 다운로드한 JSON 파일의 이름을 `google-api-key.json`으로 변경합니다.
2. 아래 경로 중 한 곳에 파일을 업로드합니다:
   - `storage/app/google-api-key.json` (권장)
   - `storage/app/private/google-api-key.json` (Laravel 11+ `Storage::disk('local')` 기본 경로)
3. `.env` 파일에 설정을 추가합니다:

```env
INDEXNOW_GOOGLE_ENABLED=true
INDEXNOW_GOOGLE_KEY_FILE=google-api-key.json
```

> ⚠️ **보안 주의:** `google-api-key.json` 파일에는 서비스 계정의 비공개 키(`private_key`)가 들어 있으므로 절대 Git 저장소에 커밋하지 마세요. (`.gitignore` 확인 필수)

4. 아래 명령어로 JSON 유효성 및 실제 OAuth2 토큰 발급 여부를 즉시 검증할 수 있습니다:

```bash
php artisan indexnow:doctor
```

---

## 5. 운영 시 주의사항 및 할당량(Quota)

- **일일 할당량:** Google Indexing API는 프로젝트당 기본 **일일 200건(PublishRequestsPerDayPerProject)**의 요청 제한이 있습니다.
  - Eloquent `Indexable` Trait 사용 시 `indexNowWatchColumns()`를 지정하여 조회수 증가 같은 사소한 DB 업데이트로 할당량이 낭비되지 않도록 하세요.
- **정책 안내:** Google 공식 문서상 Indexing API는 원래 `JobPosting` 또는 `BroadcastEvent` 구조화 데이터가 포함된 페이지용으로 권장되며, 일반 웹페이지의 경우에도 크롤링 신호로 활용되지만 색인 자체를 100% 보장하지는 않습니다.
- **삭제 요청 (`URL_DELETED`):** 페이지가 실제로 `404 Not Found` 또는 `410 Gone`을 반환하거나 삭제된 상태에서 `IndexNow::delete($url)`을 호출해야 검색 결과에서 신속히 제거됩니다.
