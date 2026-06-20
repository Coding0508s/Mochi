# ADR 0006: 거대 Livewire 컴포넌트(Fat Livewire) 분할 및 리팩토링 전략

- **상태:** 제안됨
- **날짜:** 2026-06-16
- **대상:** `InstitutionList` 등 1,000라인 이상의 거대 Livewire 컴포넌트
- **관련 ADR:** [0001 Server-Driven UI](./0001-server-driven-ui-livewire.md), [0004 Action 클래스](./0004-action-classes-write-operations.md)

## 맥락

현재 플랫폼의 UI는 Livewire를 활용한 Server-Driven UI 패턴을 따르고 있으며, 매우 실용적으로 작동하고 있습니다. 하지만 기능이 추가됨에 따라 `InstitutionList`와 같은 핵심 컴포넌트가 1,900라인 이상으로 비대해지는 문제(Fat Livewire)가 발생했습니다.

- **문제 1 (AI 컨텍스트 이탈):** 코드가 너무 길어 AI 도구를 활용해 특정 기능을 수정하거나 추가할 때, AI가 전체 문맥을 파악하지 못해 엉뚱한 코드를 생성하거나 환각(Hallucination)을 일으킬 확률이 높아집니다.
- **문제 2 (유지보수 저하):** 데이터 조회, 필터링, 모달(상세 보기/수정), 엑셀 다운로드 등 서로 다른 목적의 로직이 한 파일에 섞여 있어 단일 책임 원칙(SRP)에 위배됩니다.

[ADR 0001](./0001-server-driven-ui-livewire.md)의 후속 조치로 이미 지적된 항목이며, 쓰기 연산 분리는 [ADR 0004](./0004-action-classes-write-operations.md)와 정합됩니다.

## 결정

비대해진 Livewire 컴포넌트를 역할에 따라 **부모(상태 관리) · 자식(UI 표현) · Action(비즈니스 로직)** 형태의 계층 구조로 분할합니다.

### 분할 아키텍처 원칙

#### 1. Parent Component (컨트롤 타워) — `InstitutionList`

- **역할:** 전체 화면의 뼈대를 잡고 하위 컴포넌트 간의 이벤트를 중계합니다.
- **로직:** 직접 쿼리를 실행하지 않으며, 전역 상태(현재 선택된 탭 등)만 관리합니다.

#### 2. Child Components (UI 조각)

| 컴포넌트 | 책임 |
|----------|------|
| `InstitutionTable` | 데이터 목록 표시, 페이지네이션 |
| `InstitutionFilter` | 검색 조건, 드롭다운 필터 상태 |
| `InstitutionFormModal` | 기관 추가·수정 모달 상태, 검증(Validation) |

#### 3. Action / Support (로직 분리)

엑셀 다운로드나 복잡한 통계 로직은 기존처럼 분리된 `Action`이나 `Support` 클래스로 위임하여 Livewire 파일 크기를 최소화합니다.

## 세부 리팩토링 가이드 (AI 개발 워크플로우용)

이 가이드는 AI에게 리팩토링을 지시할 때 참조할 수 있는 단계별 절차입니다.

### Step 1: 필터 분리

- 검색어, 지역, 상태 등 필터 관련 속성(Property)과 메서드를 `InstitutionFilter` 컴포넌트로 추출합니다.
- 검색 버튼 클릭 시 Livewire 이벤트(`#[On('filter-updated')]`)를 통해 부모나 테이블 컴포넌트로 조건을 전달합니다.

### Step 2: 모달 및 폼 분리

- 기관 정보 수정, 삭제 등을 처리하는 모달 UI와 검증 로직(`$rules`)을 `InstitutionFormModal`로 분리합니다.
- 처리 완료 시 `Action` 클래스를 호출하여 DB를 업데이트하고, 완료 이벤트를 발생시켜 테이블이 새로고침되도록 합니다.

### Step 3: 테이블 및 쿼리 최적화

- `InstitutionTable`은 전달받은 필터 조건을 바탕으로만 Eloquent 쿼리를 실행합니다.
- 기존 레거시 스키마(`S_AccountName` 등)와의 매핑은 [ADR 0002](./0002-legacy-first-eloquent-strangler.md)에 따라 유지합니다.

## 대안

| 대안 | 기각/보류 이유 |
|------|----------------|
| 단일 파일 유지 + trait만 추가 | 파일·컨텍스트 크기 문제는 해소되지 않음; AI·협업 이점 제한적 |
| 전면 SPA 분리 | [ADR 0001](./0001-server-driven-ui-livewire.md)과 상충, 비용 과다 |
| Filament Resource 전환 | 도메인별 커스텀 모달·레거시 필드가 많아 제약 큼 |

## 결과

### 긍정

- **AI 개발 생산성:** 각 컴포넌트가 300~500라인 내외로 줄어 AI가 코드를 정확히 이해·수정하기 쉬움
- **코드 재사용성:** `InstitutionFilter`, 폼 모달 등을 유사 페이지에서 재사용 가능
- **협업 용이성:** 기능별 파일 분리로 동시 작업 시 충돌 감소
- **테스트:** 필터·테이블·모달 단위 Feature 테스트 작성 용이

### 부정

- **이벤트 통신 증가:** `dispatch`, `#[On]` 등 컴포넌트 간 통신 설계·학습 비용
- **초기 리팩터 비용:** 대형 화면 1건당 여러 PR·회귀 테스트 필요

### 후속 조치

- [Livewire → Action 분리 가이드](../guides/livewire-action-separation.md)와 병행
- 1차 파일럿: `InstitutionList` 분할 후 `composer run verify` 및 기존 `InstitutionListTest` 유지·보강
- 성공 시 `CoachTeacherSupportList` 등 동일 패턴의 Fat Livewire에 순차 적용
