# ADR 0002: Legacy-First Eloquent + Strangler Fig

- **상태:** 수락됨
- **날짜:** 2026-06-16

## 맥락

운영 데이터의 상당수가 **Forguncy/레거시 MySQL**(`S_AccountName`, `S_SupportInfo_Account`, `employee` 등)에 있다. 테이블·컬럼명을 일괄 리네이밍하면 동기화·외부 ingest·기존 보고서와 충돌한다. 동시에 MOCHI 전용 기능(팀 일정, 공용품, Store SKU 로그 등)은 **새 마이그레이션 테이블**이 필요하다.

## 결정

- **도메인 모델:** Laravel Eloquent **Active Record**를 기본으로 사용
- **레거시 테이블:** `Institution` → `S_AccountName`처럼 **실제 테이블명·PK·timestamps**를 모델에 명시
- **신규 기능:** Laravel migration으로 **MOCHI 전용 테이블** 추가 (Strangler Fig)
- **환경 차이:** `Schema::hasTable` / `hasColumn`으로 스키마 방어 (테스트·로컬 SQLite)
- **스키마 덤프/SQL in repo:** 참고용만 — SSOT는 migration + 운영 DB

## 대안

| 대안 | 기각 이유 |
|------|-----------|
| 전 테이블 Laravel 네이밍으로 마이그레이션 | 데이터 이관·외부 연동·운영 리스크 과대 |
| Repository로 모든 Eloquent 감싸기 | 40+ 모델 전면 래핑은 비용 대비 이득 적음 |
| 읽기 전용 CQRS 분리 | 현 단계 복잡도 대비 과설계 |

## 결과

### 긍정

- 업무 용어(SKcode, TR_Name)가 코드·DB·문서에서 일치
- 레거시와 MOCHI 기능을 **한 앱**에서 점진 확장
- Eloquent 관계·스코프로 목록 화면 구현 속도 빠름

### 부정

- 컬럼명이 PSR 스타일과 다름 → 온보딩 비용
- `Schema::has*` 분기가 누적되면 가독성 저하
- Livewire에서 Eloquent 쿼리 직접 호출 시 N+1·스코프 누락 위험

### 후속 조치

- 목록 화면: non-admin은 **쿼리 스코프·필터 옵션·카운트**를 함께 제한 (AGENTS.md)
- 외부 DB(그누보드 등)만 [ADR 0005](./0005-external-integration-adapters.md) Repository 사용
- 대규모 테이블 리네이밍은 별도 ADR·이관 계획 없이 진행하지 않음
