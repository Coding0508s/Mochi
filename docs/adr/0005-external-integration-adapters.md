# ADR 0005: 외부 연동은 Service/Repository 어댑터

- **상태:** 수락됨
- **날짜:** 2026-06-16

## 맥락

MOCHI는 단일 DB만 쓰지 않는다.

- **eCount** — 창고 재고 API
- **그누보드** — `mysql_grapeseed_goods` 별도 연결, 주문·장바구니 테이블
- **외부 CRM** — `POST /api/internal/institutions/{sk}` ingest
- **GS Brochure** — `App\GsBrochure` 모듈 + REST prefix

Livewire·Eloquent가 이 경계를 직접 알면 소스 전환·테스트·장애 격리가 어렵다.

## 결정

- **앱 내부 레거시 테이블:** Eloquent 직접 (ADR 0002)
- **외부 시스템·별도 DB:**
  - `app/Services/{Domain}/` — **파사드·소스 전환** (`match` + `config()`)
  - `app/Repositories/{Boundary}/` — **쿼리·행 매핑** (읽기/쓰기 SQL 캡슐화)
- **설정:** `config/store.php` 등에서 `data_source`, `sales_history_source` 로 전환
- **HTTP ingest:** Controller 얇게 → `Actions/UpsertInstitutionFromExternal` 등 Action
- **비동기:** `Jobs/` — outbound sync, 배정 변경 처리

## 대안

| 대안 | 기각 이유 |
|------|-----------|
| 모든 데이터 Repository | 내부 Eloquent까지 래핑 시 보일러플레이트 |
| Interface + DI 전면 | 소스 2~3개 수준에서 `match`가 더 읽기 쉬움 |
| 마이크로서비스 분리 | 운영·배포 비용, 내부 도구 규모에 부적합 |

## 결과

### 긍정

- `StoreInventoryApiClient` 한 곳에서 eCount ↔ gnuboard 전환
- Repository 테스트 시 DB connection·테이블명 mock/fake 용이
- 외부 API 장애를 Service 경계에서 처리·메시지 통일

### 부정

- Repository가 3개뿐이라 **일관성 규칙이 문서로만** 존재
- `app()` 서비스 로케이터 호출이 Service 내부에 남아 있음

### 후속 조치

- 새 외부 소스: Service에 `match` 분기 + Repository(또는 ApiClient) 추가, Livewire는 Service만 호출
- ingest·webhook: `routes/api.php` + middleware + Action + Feature 테스트
- 인터페이스 도입은 **소스가 3개 이상**이거나 단위 테스트 mock이 꼭 필요할 때만 검토
