# Search Engines & Naver Guide

## 1. 네이버(Naver) 사용 시 주의사항

네이버는 2023년부터 IndexNow 프로토콜(`https://searchadvisor.naver.com/indexnow`)을 공식 지원하고 있습니다. 원활한 반영을 위해 아래 사항을 반드시 확인하세요.

### ① 네이버 서치어드바이저 사이트 등록 권장 (API 전송은 미등록 상태에서도 가능)

- IndexNow 프로토콜은 `/{key}.txt` 파일(또는 패키지의 `auto_serve_key` 라우트)로 소유권을 검증하므로, 서치어드바이저에 사이트를 등록하지 않아도 API 요청 자체는 정상 수신(`200 OK`)됩니다.
- 다만 **IndexNow는 사이트맵/RSS 제출을 대체하지 않으며**, 서치어드바이저에 사이트를 등록하고 소유 확인을 완료해야 실제 수집·색인 현황 리포트 확인, 사이트맵 제출, `robots.txt` 진단 등을 활용할 수 있습니다.

### ② `http://`와 `https://` 프로토콜 정확히 일치시키기

- 네이버 서치어드바이저는 `http://example.com`과 `https://example.com`을 별개 사이트로 취급합니다.
- `.env`의 `APP_URL`과 서치어드바이저에 등록된 대표 도메인 프로토콜(`https://`)이 일치해야 합니다.

### ③ `robots.txt` 수집 허용 및 `canonical` 확인

- `robots.txt`에서 네이버 검색로봇(`Yeti`)의 접근이 차단되어 있으면 IndexNow로 URL을 보내도 수집할 수 없습니다.
- 중복 URL이 있는 경우 `<link rel="canonical">`이 가리키는 대표 URL을 전송하세요.

### ④ HTTP `200 OK`가 색인 보장을 의미하지는 않습니다

- 네이버 IndexNow API에서 `200 OK`를 반환하는 것은 **"URL 수신을 정상적으로 완료했다"**는 뜻이며, 모든 URL의 검색 노출을 보장하지는 않습니다.
- 신규 도메인의 경우 검색로봇 신뢰도 및 수집 반영까지 며칠~1주일 이상 소요될 수 있으며, 기존 **사이트맵(sitemap.xml) / RSS 제출**과 병행하는 것을 권장합니다.
- 서치어드바이저 **설정 > 수집 주기**를 사이트 업데이트 빈도에 맞게(`빠르게` 등) 설정해 두면 도움이 됩니다.

---

## 2. 글로벌 IndexNow 참여 검색엔진 목록

IndexNow는 개방형 프로토콜로, **참여 검색엔진 중 한 곳에만 URL을 제출하면 나머지 참여 검색엔진 모두에게 자동으로 공유**됩니다.

| 검색엔진            | 주요 대상 지역                               | 엔드포인트 URL                               |
| :------------------ | :------------------------------------------- | :------------------------------------------- |
| **IndexNow Global** | 모든 참여 엔진 공용 전파                     | `https://api.indexnow.org/indexnow`          |
| **Microsoft Bing**  | 글로벌 (Bing, Copilot, DuckDuckGo, Yahoo 등) | `https://www.bing.com/indexnow`              |
| **Naver**           | 대한민국                                     | `https://searchadvisor.naver.com/indexnow`   |
| **Yandex**          | 러시아 및 CIS                                | `https://yandex.com/indexnow`                |
| **Seznam.cz**       | 체코                                         | `https://search.seznam.cz/indexnow`          |
| **Yep**             | 글로벌 (Ahrefs 검색엔진)                     | `https://indexnow.yep.com/indexnow`          |
| **Amazonbot**       | Amazon 크롤러                                | `https://indexnow.amazonbot.amazon/indexnow` |

### 중복 요청 줄이기 팁

`config/pondol-indexnow.php`에서 `bing`(`api.indexnow.org/indexnow`)과 `naver`를 함께 켜두어도 문제없지만, 서버 아웃바운드 요청을 최소화하고 싶다면 **`api.indexnow.org/indexnow` 단일 엔드포인트만 활성화**해도 모든 IndexNow 참여 엔진에 전파됩니다.

원하는 추가 엔진(예: `seznam`, `yep`, `amazon`)은 `config/pondol-indexnow.php`의 `search_engines` 배열에 아래와 같이 추가할 수 있습니다:

```php
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
    'seznam' => [
        'enabled' => false,
        'endpoint' => 'https://search.seznam.cz/indexnow',
    ],
    'yep' => [
        'enabled' => false,
        'endpoint' => 'https://indexnow.yep.com/indexnow',
    ],
    'amazon' => [
        'enabled' => false,
        'endpoint' => 'https://indexnow.amazonbot.amazon/indexnow',
    ],
],
```

---

## 3. 기타 검색엔진 참고사항

- **DuckDuckGo / Yahoo / Ecosia:** 별도 URL 제출 API가 없으며 Bing 검색 인덱스를 공유하므로 IndexNow 연동 시 자동으로 반영됩니다.
- **Daum (카카오):** 별도의 실시간 색인 API나 IndexNow를 지원하지 않습니다. 다음 검색등록 및 사이트맵을 활용하세요.
- **Baidu (중국):** IndexNow에 참여하지 않으며, 바이두 웹마스터 플랫폼(站长平台)에서 발급받는 토큰 기반의 별도 Push API(`data.zz.baidu.com`)를 운영합니다.
