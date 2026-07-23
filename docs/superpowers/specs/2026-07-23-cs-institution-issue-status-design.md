# CS 기관 이슈 — 교사 선택 + 현황형 조회

**상태:** 승인됨 (대화 합의 2026-07-23)  
**접근:** 1안 — 기존 이슈 화면에 얇게 붙이기 (Coach 목록 통째 복제 없음)

## 1. 문제 / 목표

### 문제
- CS **기관 이슈**는 기관만 선택해 경량 기록한다. 관련 교사를 남길 수 없다.
- 조회는 이슈 **건 단위 평평한 표**라서, 기관·교사별로 이슈를 모아보기 어렵다.

### 목표
1. 이슈 작성 시 기관 선택 후 **교사를 선택 가능**하게 한다 (필수는 아님).
2. Coach 교사 지원 현황과 비슷한 **기관 → 교사(또는 기관 공통)** 현황 화면을 추가한다.
3. 기존 평평한 **기관 이슈 목록은 유지**한다.

### 비범위 (1차)
- KPI / 기간 매트릭스 집계
- Coach 교사 지원 목록·컴포넌트 통째 복제
- 현황 화면에서의 상세 모달·수정·삭제 (후속)
- `teacher_id` FK 컬럼 추가 (후속 후보)
- Coach/CO 팀의 기관 이슈 현황 접근

## 2. 사용자 · 권한

| 대상 | 작성 | 평평한 목록 | 현황 |
|------|------|-------------|------|
| CS 팀 (`accessCsTeamFeatures`) | O | O | O |
| 관리자 등 기존 Gate와 동일 범위 | O | O | O |
| Coach / CO | 이슈 모드·현황 X | (기존 정책 유지) | X |

권한은 기존 Gate `accessCsTeamFeatures`와 동일하게 맞춘다.

## 3. IA / 메뉴

CS Team 사이드바에 다음 두 메뉴를 둔다. (현재 **기관 이슈**가 사이드바에 없으면 함께 노출)

| 메뉴 | 경로 (안) | 역할 |
|------|-----------|------|
| 기관 이슈 | `/institution-issues` (`institution-issues.index`) | 기존 평평한 목록 **유지** |
| 기관 이슈 현황 | `/institution-issues/status` (`institution-issues.status`) | **신규** 기관→교사 아코디언 |

작성 진입: 기존 `supports.create?report_mode=issue` (목록·현황 모두에서 “기관 이슈 작성” 링크).

## 4. 데이터 · 저장

- 테이블: 기존 `S_SupportInfo_Account` (`SupportRecord`)
- `record_kind = 'issue'` 유지
- **마이그레이션 없음**

| 상황 | `Target` | 현황 표시 |
|------|----------|-----------|
| 교사 미선택 | null / 빈값 | **기관 공통** |
| 교사 선택 | 교사 이름 문자열 | 해당 교사명 그룹 |

기타 필드(발생일, 시간, `Issue`, `is_urgent`, `TR_Name`, `SK_Code` 등)는 현행 이슈 저장과 동일.

과거 데이터: `Target`이 비어 있으면 모두 **기관 공통**.

동명이인 등은 1차에서 이름 그룹핑으로 수용. 필요 시 후속으로 `teacher_id` 검토.

## 5. 작성 흐름

화면: 기존 `SupportCreateForm` + `reportMode === 'issue'`.

1. 기관 선택 (현행)
2. **교사 선택 (선택 사항, 신규)**
   - 해당 기관 교사 드롭다운 (Coach 작성의 `formTeacherId` / `institutionTeachers` 패턴 재사용)
   - 기본: 「선택 안 함 (기관 공통)」
   - 필수 아님 → 기관만으로 저장 가능
3. 발생일 / 시간 / 이슈 내용 / 긴급 (현행)

동작:
- 기관 변경 시 교사 선택 초기화
- 교사 목록이 비어도 기관 공통으로 저장 가능 (안내 문구)
- 저장 시 `Target`에 교사명 또는 빈값 반영
- 저장 후 리다이렉트는 현행 유지 (현황으로 강제 이동하지 않음)

### 평평한 목록 보완
- `InstitutionIssueList`에 **관련 교사**(또는 기관 공통) 컬럼 추가
- 필터·검색·긴급·페이지네이션 동작은 유지

## 6. 현황 화면

### UX
Coach 교사 지원 **목록형 펼침**에 가깝게. KPI 매트릭스 없음.

1. **필터:** 년도, 검색(기관명/SK/교사명/이슈 내용), 긴급만 보기, 작성 버튼
2. **기관 행:** 기관명 · SK · 이슈 N건 · (긴급 M건)
3. **펼침:**
   - **기관 공통** 그룹 (`Target` 없음)
   - **교사명** 그룹들
4. **이슈 행:** 발생일 · 시간 · 긴급 · 담당 CS · 이슈 요약 · 상태(완료/진행중)
5. **빈 상태:** 이슈 없음 / 검색 결과 없음

### 구현 방향 (권장)
- 새 Livewire 컴포넌트 (예: `InstitutionIssueStatus`)
- 이슈만 `onlyIssues()`로 조회 후 기관·`Target` 기준 그룹핑 (Aggregator/Support 클래스 분리 가능)
- Coach `CoachTeacherSupportList`를 복제하지 않음

### 1차에서 하지 않음
- 행 클릭 상세 모달 / 인라인 수정·삭제

## 7. 수용 기준

- [ ] CS가 이슈 작성 시 기관만으로 저장할 수 있다.
- [ ] CS가 이슈 작성 시 기관 + 교사를 선택해 저장하면 `Target`에 교사명이 들어간다.
- [ ] 기관 변경 시 교사 선택이 초기화된다.
- [ ] 기존 `/institution-issues` 평평한 목록이 유지되고, 관련 교사(또는 기관 공통)가 보인다.
- [ ] CS 사이드바에 기관 이슈 / 기관 이슈 현황이 보인다.
- [ ] 현황에서 기관을 펼치면 기관 공통·교사별 이슈가 보인다.
- [ ] Coach/CO는 현황·이슈 작성 모드를 쓰지 못한다 (기존 정책과 동일).
- [ ] DB 마이그레이션이 없다.
- [ ] 관련 Feature 테스트가 작성·통과한다.

## 8. 테스트 체크리스트

- 이슈 저장: 교사 없음 → `Target` 빈값
- 이슈 저장: 교사 선택 → `Target` = 교사명
- 기관 변경 시 `formTeacherId` 초기화
- `InstitutionIssueList`에 교사/기관 공통 표시
- 현황: 기관·교사·기관 공통 그룹핑
- 현황: 년도/검색/긴급 필터
- 권한: CS OK, 비CS 차단(또는 기존 Gate와 동일)
- 사이드바 메뉴 노출 (CS)

## 9. 위험 · 주의

- `Target`은 이름 문자열이라 교사 개명·동명이인 시 그룹이 갈라질 수 있음 (1차 수용).
- 사이드바에 기관 이슈가 없던 상태면 메뉴 추가가 “새 기능처럼” 보일 수 있음 — 의도된 IA.
- 현황 집계 시 N+1 방지: 이슈를 한 번에 가져와 메모리 그룹핑 권장.

## 10. 구현 시 주요 파일 (예상)

| 구분 | 파일 |
|------|------|
| 작성 | `app/Livewire/SupportCreateForm.php`, `resources/views/livewire/support-create-form.blade.php` |
| 목록 | `app/Livewire/InstitutionIssueList.php`, `resources/views/livewire/institution-issue-list.blade.php` |
| 현황 (신규) | Livewire + blade + `pages/institution-issues/status.blade.php` |
| 라우트 | `routes/web.php` |
| 메뉴 | `resources/views/partials/sidebar-cs-team-block.blade.php` |
| 테스트 | `tests/Feature/InstitutionIssueTest.php` (확장) 및/또는 현황·작성 테스트 |

## 11. 변경 기록 요약 (스펙 확정 시점)

| 항목 | 내용 |
|------|------|
| 변경 기능 | 이슈 작성 시 선택적 교사, 현황형 조회 화면 추가, 목록에 교사 컬럼 |
| 변경 이유 | 기관·교사 맥락으로 이슈를 남기고 모아보기 |
| DB 변경 | 없음 |
| IA 변경 | CS 메뉴에 기관 이슈(+현황) 노출 |
| 권한 변경 | 새 Gate 없이 기존 CS Gate 재사용 |
