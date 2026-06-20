# Livewire → Action 분리 가이드

MOCHI 플랫폼에서 **비대해진 Livewire 컴포넌트**를 점진적으로 정리할 때 따르는 실무 가이드입니다.  
관련 ADR: [0001 Server-Driven UI](./adr/0001-server-driven-ui-livewire.md), [0004 Action 클래스](./adr/0004-action-classes-write-operations.md).

---

## 1. 언제 분리할까?

### Action으로 빼야 하는 것 (쓰기·경계)

- `create` / `update` / `delete` / upsert
- `DB::transaction` 이 필요한 저장
- `Gate::authorize` 또는 작성자·SK 스코프 검증
- `Validator` / 비즈니스 규칙 위반 시 `AuthorizationException`
- Job/API에서 **같은 규칙**으로 재사용될 가능성이 있는 연산

### Livewire에 남겨도 되는 것 (읽기·UI)

- `wire:model` 폼 상태, 모달 open/close
- 목록 쿼리 + `WithPagination` (단, 복잡한 스코프는 `Support` 또는 scope 메서드로)
- `session()->flash`, redirect, 엑셀 **스트리밍 응답** 트리거
- Alpine/Livewire 전용 UI 이벤트

### Support(`app/Support/`)로 빼는 것

- 여러 Action/Livewire가 쓰는 **집계·정규화·타임라인 빌드**
- 예: `InstitutionUnifiedTimelineBuilder`, `ManagerNameNormalizer`

---

## 2. 계층 역할 (한 줄)

```
Blade/Livewire  →  UI 상태, 사용자 이벤트
Action          →  권한 + 검증 + 트랜잭션 + 저장
Support         →  순수 계산·집계 (DB 쓰기 없음 또는 보조)
Service/Repo    →  외부 API·별도 DB (ADR 0005)
Model           →  Eloquent, scope, relation
```

---

## 3. 좋은 예 — 이미 있는 패턴

### Coach 교사 지원 보고서

**Livewire** (`CoachTeacherSupportList`): 폼 필드, 모달, flash, 모달 닫기  
**Action** (`StoreTeacherDemoLessonSupportReport`): authorize → validate → transaction → `SupportRecord` 연동

```php
// Livewire — UI 오케스트레이션만
$action = new StoreTeacherDemoLessonSupportReport;
$action->execute($this->demoLessonTeacherId, $payload, $user);

session()->flash('success', '...');
$this->closeDemoLessonModal();
```

```php
// Action — 규칙의 단일 출처
public function execute(int $teacherId, array $data, User $user): TeacherDemoLessonSupportReport
{
    $teacher = Teacher::findOrFail($teacherId);
    $this->authorize($teacher, $user);

    $validated = $this->validate($data);
    // ...

    return DB::transaction(function () use (...) {
        // create / update
    });
}
```

### 기관 지원 보고서 수정

`UpdateSupportRecord` — SK 스코프, 해지 기관 차단, 작성자 일치를 Action에서 처리 (`__invoke`).

---

## 4. 분리 절차 (한 메서드씩)

PR을 **한 의도**로 작게 유지합니다 (git-workflow-pr-split).

| 단계 | 작업 | 검증 |
|------|------|------|
| 1 | Livewire의 `save*` / `delete*` 메서드에서 **권한·검증·DB 쓰기** 블록 표시 | 기존 Feature 테스트 green |
| 2 | `php artisan make:class Actions/VerbNoun` (또는 기존 네이밍: `StoreX`, `UpdateX`, `DeleteX`) | — |
| 3 | Action에 로직 이동; Livewire는 payload 조립 + Action 호출 + UI 후처리 | Action 단위 테스트 또는 기존 Feature |
| 4 | `Gate::authorize` / 예외는 Action 안으로; Livewire는 `catch (AuthorizationException)` 만 선택 | 403/flash 메시지 회귀 |
| 5 | Pint + `php artisan test --compact --filter=...` | CI |

**한 PR에서 Livewire 1~2개 save 메서드만** 옮기는 것을 권장합니다.

---

## 5. Action 작성 템플릿

```php
<?php

namespace App\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class UpdateExampleRecord
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(ExampleModel $record, array $payload): ExampleModel
    {
        Gate::authorize('updateExampleRecord', $record);
        // 또는 도메인 authorize() private 메서드

        $validated = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
        ])->validate();

        return DB::transaction(function () use ($record, $validated): ExampleModel {
            $record->fill($validated);
            $record->save();

            return $record->refresh();
        });
    }
}
```

**Livewire 호출:**

```php
public function save(): void
{
    $this->validate([ /* wire:model 필드 — UI 수준 */ ]);

    try {
        app(UpdateExampleRecord::class)->execute(
            $this->record,
            $this->only(['name', ...]),
        );
    } catch (AuthorizationException) {
        $this->addError('form', '권한이 없습니다.');

        return;
    }

    session()->flash('success', '저장되었습니다.');
    $this->closeModal();
}
```

### 네이밍

| 동작 | 접두어 | 예 |
|------|--------|-----|
| 생성 | `Store` | `StoreTeacherDemoLessonSupportReport` |
| 수정 | `Update` | `UpdateSupportRecord` |
| 삭제 | `Delete` | `DeletePotentialMeetingDetail` |
| 외부 upsert | `Upsert` | `UpsertInstitutionFromExternal` |

---

## 6. 리팩터 우선순위 (파일 크기·위험도)

2026-06 기준 Livewire 라인 수 상위와 권장 순서입니다.

| 우선순위 | 컴포넌트 | 라인(약) | 제안 |
|----------|----------|----------|------|
| P1 | `CoachTeacherSupportList` | 2850 | 저장 메서드별 Action 이미 부분 적용 → **수정·삭제·공통 payload** 통합 |
| P1 | `InstitutionList` | 1920 | 상세 모달 저장·배정 변경 → Action; 목록 쿼리는 scope/Support |
| P2 | `SharedSupplyManager` | 1800 | 예약 CRUD → Action (Policy는 유지) |
| P2 | `SupportCreateForm` | 1435 | `StoreSupportRecord`류 Action |
| P3 | `PeopleEmployeesList` | 1387 | 부서 변경·프로필 저장 분리 |
| P3 | `PotentialInstitutionList` | 1187 | 계약 전환·미팅 저장 Action화 |

**P1 완료 기준:** 해당 화면의 모든 `DB::transaction` / `->save()` / `->delete()` 가 Action 또는 Observer 한곳에만 존재.

---

## 7. 안티패턴 (피할 것)

| 안티패턴 | 문제 | 대안 |
|----------|------|------|
| Action이 `Livewire` 참조 | 순환·테스트 불가 | 배열만 전달 |
| Livewire에 `DB::transaction` 잔존 | 규칙 이중화 | Action으로 이동 |
| UI만 Gate 체크 | API/Job 우회 시 취약 | Action 진입 시 재검증 |
| God Action (`ManageInstitution`) | 다시 비대화 | 동사+명사 단일 연산 |
| 읽기 쿼리 200줄을 Action으로 | 역할 혼동 | `Support` 또는 Model `scope` |

---

## 8. 테스트 전략

1. **기존 Feature 테스트 유지** — Livewire `->call('save')` 경로로 회귀
2. **Action 추가 시** — happy path + `AuthorizationException` + validation failure
3. **권한** — admin / 일반 / 타팀 `actingAs` 조합
4. **외부 DB** — Repository fake 또는 SQLite in-memory (기존 `StoreSalesHistoryGnuboardPageTest` 패턴)

```bash
php artisan test --compact tests/Feature/YourPageTest.php
vendor/bin/pint --dirty
```

---

## 9. Concern(trait) vs Action

| | Concern | Action |
|---|---------|--------|
| 목적 | 여러 Livewire **UI·상태 공유** | **쓰기 연산** 단일 출처 |
| 예 | `ManagesInstitutionSupportDetailEdit` | `UpdateSupportRecord` |
| 테스트 | Livewire 통합 테스트 | Action 단위 가능 |

Concern에 `->save()`가 들어가기 시작하면 Action 추출 신호입니다.

---

## 10. 체크리스트 (PR 전)

- [ ] Livewire save/delete에 `DB::write` 없음
- [ ] Gate/Policy 검증이 Action **진입부**에 있음
- [ ] flash·모달·redirect만 Livewire에 있음
- [ ] Feature 테스트 추가/갱신
- [ ] ADR 0003 목록 스코프( non-admin ) 위반 없음
- [ ] 한 PR = 한 화면 또는 한 연산군

---

## 11. 다음 단계 제안

1. **P1** `InstitutionList` — 배정·상세 저장 1개 Action부터 PR
2. **공통** `CoachTeacherSupportPayload` — 이미 Support에 있음; Update 경로도 `UpdateTeacherSupportReport`로 통일 검토
3. 팀 합의 후 ADR 0004에 `execute` vs `__invoke` **팀 표준** 한 줄 추가

문의·예외 케이스는 PR 본문에 “ADR/가이드 대비 예외 사유”로 남깁니다.
