# 교사 지원 현황 — 전체 기관 보기 정렬 (교사 최신 지원순)

**날짜:** 2026-07-27  
**상태:** 승인됨  
**대상 화면:** Teams > Coach Team > 교사 지원 현황 (`CoachTeacherSupportList`)

## 1. 문제 / 목표

담당 코치 선택 후 **전체 기관 보기**를 켜면 지원 이력이 없는 교사·기관까지 포함되는데, 현재는 **기관명 가나다순**이라 미지원 행이 중간에 섞여 탐색이 어렵다.

**목표:** 전체 기관 보기에서도 **교사 단위 최신 지원일 내림차순**으로 정렬하고, 지원이 없는 교사는 목록 뒤쪽에 오게 한다.

## 2. 합의된 요구사항

| 항목 | 결정 |
|------|------|
| 접근 방식 | **B** — 교사 단위 정렬 (기관 묶음 미보장) |
| 정렬 기준 | 최신 지원 보기와 동일: 완료·MOCHI·레거시 최신 지원일 내림차순 |
| 미지원 교사 | 목록에 **유지**(제외하지 않음)하되, 정렬상 **뒤** |
| 포함 범위 | 변경 없음 — 전체 보기는 계속 미지원 교사·기관 표시 |
| UI | 토글 라벨·레이아웃 변경 없음 |
| 담당 코치 필터 | 기존과 동일 (정렬만 변경) |

### 의도적으로 하지 않는 것

- 기관 단위로 묶은 뒤 “기관 최신 지원일” 정렬 (방식 A)
- 미지원 기관·교사 목록 제외
- KPI·검색·퇴직 토글 동작 변경

## 3. 현재 동작 vs 변경 후

| 모드 | 현재 정렬 | 변경 후 |
|------|-----------|---------|
| 최신 지원 보기 | `applyLatestSupportOrdering` | 동일 (변경 없음) |
| 전체 기관 보기 | 기관명 ASC → SK → 이름 | **`applyLatestSupportOrdering`** (최신 보기와 동일) |

미지원 교사의 최신일 표현은 기존과 같이 폴백(`1970-01-01` 등)으로 처리되어 `orderByDesc` 시 자연스럽게 후순위가 된다.

## 4. 구현 전략

### 변경 지점

- `app/Livewire/CoachTeacherSupportList.php` — `applyTeacherListOrdering()`
  - `showAllInstitutionsView === true` 분기에서 기관명 `orderByRaw` 제거
  - 분기 없이(또는 전체 보기에서도) `TeacherSupportListActivity::applyLatestSupportOrdering($query, $this->resolvedFilterYear())` 호출

### 재사용

- `TeacherSupportListActivity::applyLatestSupportOrdering` — 신규 정렬 로직을 만들지 않음

### 테스트

- `tests/Feature/CoachTeacherSupportListTest.php`
  - `test_teachers_ordered_by_institution_name_in_all_institutions_view` 를 **전체 보기 + 최신 지원일 순** 검증으로 교체·개명
  - 전체 보기에서 지원 있는 교사 → 없는 교사 순서가 되는 케이스 포함
- 최신 지원 보기 기존 테스트는 유지

## 5. 수용 기준

- [ ] 전체 기관 보기 ON일 때, 교사 행이 최신 지원일 내림차순이다.
- [ ] 같은 조건에서 지원 이력이 없는 교사는 지원 이력이 있는 교사보다 뒤에 온다.
- [ ] 전체 보기에서도 미지원 교사가 **여전히 목록에 나타난다**(숨기지 않음).
- [ ] 최신 지원 보기 동작·필터·KPI는 회귀 없이 기존과 같다.
- [ ] 관련 Feature 테스트가 통과한다.

## 6. 위험 / 부작용

- **같은 기관 교사가 목록에서 떨어져 보일 수 있음** — 방식 B의 의도된 트레이드오프.
- 정렬 SQL은 이미 최신 보기에서 쓰이므로 성능 리스크는 기존과 동일 수준.

## 7. 범위

### In scope

- `CoachTeacherSupportList::applyTeacherListOrdering`
- 관련 Feature 테스트 수정·추가

### Out of scope

- Blade/UI 카피 변경
- 기관 단위 그룹 정렬
- 다른 목록 화면(기관리스트, 연락처 등)
