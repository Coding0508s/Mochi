# Agent Handoff: Team Schedule / Potential Institution Scope Fix

## 현재 작업 요약

사용자가 요청한 두 버그를 확인하고 수정했다.

1. `TeamScheduleCalendar`에서 현재 사용자의 `employee` 또는 `WORKDEPT`가 없을 때 팀 일정 조회가 `WORKDEPT = NULL` 성격의 조건으로 흐르던 문제를 수정했다.
2. `PotentialInstitutionList::render()`에서 비관리자가 전체 잠재기관 목록, 필터 옵션, 카운트/메타데이터를 볼 수 있던 정보 노출 문제를 수정했다.

## 핵심 수정 파일

- `app/Livewire/TeamScheduleCalendar.php`
  - `currentUserWorkdept()` 헬퍼를 추가했다.
  - `WORKDEPT`가 빈 값이면 팀 공개 일정 확장 조회를 하지 않고 본인 일정만 보이도록 했다.
  - `teamUsers()`는 `WORKDEPT`가 없으면 빈 컬렉션을 반환한다.

- `app/Livewire/PotentialInstitutionList.php`
  - `applyManageableTargetScope()`와 `manageableTargetQuery()` 계열 scope 처리를 추가했다.
  - 비관리자 scope 기준:
    - `created_by = 현재 사용자 id`
    - 또는 `created_by IS NULL`인 레거시 데이터 중 `AccountManager`가 현재 사용자 표시명과 정규화 매칭되는 항목
  - 관리자(`hasFullAccess()`)는 기존처럼 전체 조회한다.
  - 목록뿐 아니라 `yearList`, `managerList`, `typeList`, `introductionPathList`, `allCount`, `newCount`, `terminatedCount`에도 같은 scope를 적용해야 한다.

- `tests/Feature/TeamScheduleCalendarTest.php`
  - `WORKDEPT`가 없는 사용자의 팀 보기에서 본인 일정은 보이고 타인 팀 일정은 보이지 않는 회귀 테스트를 추가했다.

- `tests/Feature/PotentialInstitutionListTest.php`
  - 비관리자가 본인 소유/레거시 담당자 매칭 잠재기관만 보고, 타인 목록/옵션을 볼 수 없는 테스트를 추가했다.
  - 기존 필터 테스트는 전체 조회 전제였으므로 기본 테스트 로그인 사용자를 관리자로 바꿨다.

## 검증 결과

아래 명령은 통과했다.

```bash
php artisan test --filter=TeamScheduleCalendarTest --stop-on-failure
php artisan test --filter=PotentialInstitutionListTest --stop-on-failure
```

결과:

- `TeamScheduleCalendarTest`: 11 passed
- `PotentialInstitutionListTest`: 34 passed
- 관련 파일 linter 오류 없음

## 다음 에이전트 주의사항

- `.cursor/plans/비밀번호_재설정_메일_9d73e30e.plan.md`는 사용자가 “수정하지 말라”고 했던 계획 파일이므로 건드리지 말 것.
- 이 브랜치에는 이미 많은 기능 변경이 섞여 있다. 되돌리거나 정리할 때는 사용자가 만든 변경과 이번 scope 수정이 섞이지 않게 `git diff`를 먼저 확인할 것.
- `PotentialInstitutionList`의 보안 핵심은 “작업 버튼만 막는 것”이 아니라 “목록/옵션/카운트까지 같은 scope로 막는 것”이다.
- `created_by`가 없는 기존 레거시 잠재기관은 완전히 숨기면 기존 업무가 끊길 수 있으므로, 담당자명 정규화 매칭 fallback을 유지했다.
- `TeamScheduleCalendar`에서 `WORKDEPT`가 없을 때 같은 부서 판단을 시도하면 다시 null 비교 문제가 생긴다. 부서가 없으면 팀 범위를 확장하지 않는 현재 정책을 유지할 것.
