# 교사 지원 현황 「고용형태」 컬럼 디자인

**날짜:** 2026-07-27  
**상태:** 구현 완료  
**대상 화면:** `/coach/teacher-support` — 「교사 지원 현황」 (`CoachTeacherSupportList`)

## 1. 문제 / 목표

교사 지원 현황 목록에서 직급만 보이고, 고용형태는 상세 모달을 열어야 확인할 수 있다.

**목표:** 직급 바로 다음에 고용형태를 목록(데스크톱 테이블·모바일 카드)에서 바로 보이게 한다.

## 2. 합의된 요구사항

| 항목 | 결정 |
|------|------|
| 컬럼 위치 | 직급 다음, GS Ess. 이전 |
| 헤더 문구 | `고용형태` |
| 표시 값 | `TeacherEmploymentType::label()` — `Full Time` / `Part Time` / `미지정` |
| 빈 값 / unspecified | **「미지정」** 표시 (연락처·상세 모달과 동일) |
| 데스크톱 | sticky 컬럼으로 삽입 |
| 모바일 | 직급 배지 옆에 고용형태 배지 |
| DB 변경 | 없음 (`Teachers.EmploymentType` 기존 컬럼 사용) |
| 필터·정렬·편집 | 이번 범위 밖 (표시만) |

## 3. 접근 방식

**채택: A — sticky 컬럼으로 삽입**

- `colgroup` / `thead` / `tbody`에 「고용형태」 컬럼 추가
- CSS: `--coach-support-employment-width` 추가, sticky `left`를 직급 다음에 두고 GS/LS Ess. 오프셋을 고용형태 폭만큼 이동
- 표시: `TeacherEmploymentType::fromMixed($teacher->EmploymentType)->label()`
- 모바일 카드: 직급 배지 옆에 동일 라벨 배지

**이유:** 기존 테이블이 SK~LS Ess.까지 sticky라, 직급과 GS Ess. 사이에 끼우면 스크롤 시에도 직급·고용형태가 함께 남는다.

**기각**

- B: sticky 아닌 일반 컬럼 — 스크롤 시 직급만 남고 고용형태가 사라짐
- C: 직급 셀에 합쳐 표시 — 헤더·정렬·가독성 저하

## 4. UI

### 데스크톱 컬럼 순서

`SK → 기관명 → 이름 → 직급 → 고용형태 → GS Ess. → LS Ess. → (계획/완료 컬럼…)`

### 모바일 카드

- 이름 옆: 직급 배지 + 고용형태 배지
- 스타일: 직급과 유사한 작은 배지 (`bg-gray-100 text-gray-700` 등 기존 톤 유지)

## 5. 수정 범위

| 구분 | 파일 (예상) |
|------|-------------|
| 수정 | `resources/views/livewire/coach-teacher-support-list.blade.php` |
| 수정 | `resources/views/partials/coach/teacher-support-mobile-card.blade.php` |
| 수정 | `resources/css/app.css` (sticky/col width) |
| 수정 | `tests/Feature/CoachTeacherSupportListTest.php` (표시 assert) |
| 건드리지 않음 | DB migration, `UpdateTeacherProfile`, 필터/검색 로직, ContactList |

## 6. 수용 기준

- [ ] 데스크톱 테이블에 「고용형태」 헤더가 직급과 GS Ess. 사이에 있다
- [ ] Full Time / Part Time / 미지정 값이 올바르게 보인다
- [ ] 가로 스크롤 시 고용형태가 sticky로 직급 옆에 남는다
- [ ] 모바일 카드에서 직급 배지 옆에 고용형태가 보인다
- [ ] Feature 테스트로 목록 표시를 검증한다
- [ ] `composer run verify` (또는 관련 테스트 + Pint) 통과

## 7. 비범위 (Out of scope)

- 고용형태 필터·정렬
- 라벨 문구 변경 (예: Full Time → 정규직)
- 다른 화면(퇴직 교사 목록 등) 동일 컬럼 추가
- DB 스키마 변경

## 8. 위험

- sticky `left` 오프셋 누락 시 GS/LS Ess. 컬럼이 겹치거나 어긋날 수 있음 → CSS 변수 한곳에서 폭을 정의하고 essentials offset에 반드시 합산
- 테이블이 더 넓어져 가로 스크롤이 늘어남 → 고용형태 폭을 짧게 유지 (예: 직급과 유사)
