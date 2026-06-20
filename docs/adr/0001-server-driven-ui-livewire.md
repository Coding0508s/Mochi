# ADR 0001: Server-Driven UI — Livewire를 주 UI 계층으로

- **상태:** 수락됨
- **날짜:** 2026-06-16

## 맥락

GrapeSEED MOCHI는 CO/CS/Coach 등 **내부 운영 직원**이 쓰는 관리형 웹 앱이다. 화면 수가 많고(기관·연락처·지원·일정·Store·Setup 등), 폼·모달·필터·페이지네이션이 반복된다. 팀은 PHP(Laravel)에 익숙하고, 별도 SPA 팀을 두기 어렵다.

## 결정

- **주 UI 스택:** Laravel Blade + **Livewire 4** + Alpine.js(경량 클라이언트 상호작용)
- **라우트:** 대부분 `Route::get` → `pages/*` Blade → `<livewire:... />` 임베드
- **Controller:** 인증·파일 다운로드·외부 REST API 등 **얇은 HTTP 경계**에만 사용
- **뷰 구조:** `pages/` · `components/` · `partials/` · `livewire/` (화면 유형별 규칙 준수)

## 대안

| 대안 | 기각 이유 |
|------|-----------|
| React/Vue SPA + REST API | API·프론트 이중 유지 비용, 내부 도구에 과한 분리 |
| Inertia.js | Livewire 대비 팀 숙련도·기존 Blade 자산과의 정합성 |
| Filament/Nova 전면 | 도메인별 커스텀 모달·레거시 필드가 많아 제약 큼 |

## 결과

### 긍정

- 서버 상태·검증·권한을 PHP 한 곳에서 처리
- Blade 컴포넌트 재사용, Feature 테스트로 화면 회귀 검증 용이
- `Livewire\Concerns\*` trait로 대형 화면을 부분 조합 가능

### 부정

- Livewire 컴포넌트가 **수천 라인**으로 비대해지기 쉬움 (`InstitutionList`, `CoachTeacherSupportList` 등)
- UI와 쿼리·저장 로직이 한 클래스에 섞이면 테스트·재사용이 어려움

### 후속 조치

- 쓰기·권한·트랜잭션은 [ADR 0004](./0004-action-classes-write-operations.md) Action으로 분리
- [Livewire → Action 분리 가이드](../guides/livewire-action-separation.md) 준수
- 새 화면은 Livewire public 메서드 **300라인·단일 책임** 가이드라인 검토
