# ContactList 「내 담당만」 토글 디자인

**날짜:** 2026-07-27  
**상태:** 구현 완료  
**대상 화면:** `/contacts` — 사이드바 「교직원 연락처보기」 / 페이지 제목 「기관 연락처 관리」 (`ContactList`)

## 1. 문제 / 목표

팀(CO / CS / Coach)에서 교직원 연락처를 볼 때, 전체 목록과 본인이 담당하는 기관의 교직원만 보는 전환이 필요하다.

**목표:** 「내 담당만」 토글로 현재 사용자 팀의 담당 필드(CO/TR/CS)에 매칭되는 기관의 교사만 필터한다.

## 2. 합의된 요구사항

| 항목 | 결정 |
|------|------|
| UI 형태 | 「내 담당만」 토글 (담당자 드롭다운 아님) |
| 매칭 기준 | 사용자 **팀**에 해당하는 담당 컬럼만 |
| 기본값 | **꺼짐** (전체 연락처, 기존 동작 유지) |
| 대상 페이지 | `/contacts` (`ContactList`) — 확인 완료 |

### 팀 → 담당 컬럼

| 사용자 팀 | `S_Account_Information` 컬럼 |
|-----------|------------------------------|
| Coach / TR / Training | `TR` |
| CS | `CS` |
| CO | `CO` |

이름 매칭: 기존 기관리스트「내 담당」과 동일 (`name` / `email` / employee 한글·영문 별칭).

## 3. 접근 방식

**채택:** `InstitutionAccountListQuery::applyCurrentUserManagerScope()` 재사용.

Teacher 쿼리에서 토글 ON일 때:

```php
$query->whereHas('institution', function (Builder $institutionQuery): void {
    app(InstitutionAccountListQuery::class)
        ->applyCurrentUserManagerScope($institutionQuery);
});
```

**이유:** 기관리스트 `my_assigned`와 팀·별칭 규칙이 같아 불일치 위험이 적다.

**기각:** ContactList 전용 매칭 로직 신규 작성(중복), 담당자 드롭다운(범위 초과).

## 4. UI

- 위치: 필터 카드 안, 검색 입력과 「상태」 토글 사이(또는 「상태」 옆).
- 컨트롤: `내 담당만` 라벨 + on/off 토글(또는 활성/비활성 버튼). 기존 `mochi-toggle` / 유사 패턴 우선.
- 기본: OFF.

## 5. 동작 세부

토글 ON일 때 아래 **모두** 동일 조건 적용:

1. 목록 쿼리 (`filteredTeachersQuery`)
2. 상단 통계 (`totalCount` / `activeCount` / `inactiveCount`)
3. 엑셀 다운로드 (`exportContactsExcel`)

토글 OFF: 현재와 동일(히든 기관 제외만, 담당 스코프 없음).

### 엣지

- 팀 컬럼을 특정할 수 없고 별칭만 있는 경우: 기존 `applyCurrentUserManagerScope` 동작(CO/TR/CS OR)을 따름.
- 별칭이 없으면: 결과 0건 (`whereRaw('1 = 0')`).
- Full Access: 기본 OFF로 전체 유지. ON이면 본인 이름·팀 컬럼 기준으로 필터.
- `team_menu` 쿼리만으로 컬럼을 바꾸지 않음 — **로그인 사용자 팀** 기준.

## 6. 범위

### In scope

- `ContactList` 상태·필터·뷰·테스트
- 기존 `InstitutionAccountListQuery` 재사용

### Out of scope

- 담당자 드롭다운 / 타인 담당자 선택
- ContactList 기본 목록을 팀 담당으로 강제 스코프
- DB 마이그레이션
- 사이드바/페이지 제목 통일(이름만 다름, 동작 변경 없음)
- 진행 중인 고용 형태(`employment_type`) 변경과 무관한 리팩터

## 7. 테스트 계획

Feature 테스트 (`ContactList` 계열):

1. 기본 OFF → 타 담당 기관 교사도 보임 (회귀).
2. ON + Coach 사용자 → `TR`이 본인인 기관 교사만.
3. ON + CS 사용자 → `CS` 매칭만.
4. ON + CO 사용자 → `CO` 매칭만.
5. ON일 때 엑셀/통계가 목록과 같은 범위.

기존 `ContactListTeamScopeTest` 등과의 충돌 없이 신규 케이스 추가.

## 8. 위험

- Institution 없는 Teacher / SK 정규화 불일치 시 토글 ON에서 누락 가능 → 기존 `whereHas('institution')`·hydrate 패턴과 동일 한계. 별도 백필은 하지 않음.
- 별칭 불일치(표기 차이)는 기관리스트와 동일한 체감. 신규 normalizer 도입 금지.

## 9. 구현 시 수정 파일 (예상)

- `app/Livewire/ContactList.php`
- `resources/views/livewire/contact-list.blade.php`
- `tests/Feature/ContactListMyAssignedFilterTest.php` (신규 권장) 또는 기존 TeamScope 테스트 확장
