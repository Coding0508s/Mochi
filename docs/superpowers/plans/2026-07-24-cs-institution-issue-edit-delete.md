# CS 기관 이슈 상세 모달 수정·삭제 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 기관 이슈 상세 모달에서 작성자·관리자는 수정하고, 관리자만 삭제할 수 있게 한다.

**Architecture:** `InstitutionIssueList`에 SupportList식 모달 편집 상태/메서드를 추가한다. 권한은 기존 `updateSupportRecord` / `deleteSupportRecords` Gate를 재사용하고, 삭제는 `SupportRecordCascadeDeleter`를 호출한다. 교사·기관 필드는 읽기 전용이다.

**Tech Stack:** Laravel 13, Livewire 4, Blade, PHPUnit, 기존 Gate

**스펙:** `docs/superpowers/specs/2026-07-24-cs-institution-issue-edit-delete-design.md`

## Global Constraints

- DB 마이그레이션 없음
- 새 Gate 없음 — `updateSupportRecord`, `deleteSupportRecords`만 사용
- Coach / SupportList / SupportCreateForm 수정 경로 변경 금지 (CreateForm은 건드리지 않음)
- `record_kind = issue` 레코드만 이 컴포넌트에서 수정·삭제
- `Target` / `SK_Code` / `Account_Name` / `TR_Name` / `record_kind` 변경 금지
- 브랜치: `feature/cs-institution-issue-edit-delete`

## File Structure

| 파일 | 역할 |
|------|------|
| `app/Livewire/InstitutionIssueList.php` | 편집 상태, start/cancel/save/delete, 그룹 리프레시 |
| `resources/views/livewire/institution-issue-list.blade.php` | 펼친 이슈에 수정·삭제 UI / 편집 폼 |
| `tests/Feature/InstitutionIssueTest.php` | 권한·저장·삭제 Feature 테스트 |

참고(읽기만): `SupportList.php` (`startModalEdit`/`save`/`deleteRecord`), `AppServiceProvider` Gate, `SupportRecordCascadeDeleter`, `SupportRecord::completionAttributes`

---

### Task 1: 권한·저장·삭제 실패 테스트 작성

**Files:**
- Modify: `tests/Feature/InstitutionIssueTest.php`
- Modify: `app/Livewire/InstitutionIssueList.php` (이후 Task 2에서 구현)

**Interfaces:**
- Produces (예상 Livewire API):
  - `startIssueEdit(int $id): void`
  - `cancelIssueEdit(): void`
  - `saveIssue(): void`
  - `deleteIssue(int $id): void`

- [ ] **Step 1: 작성자 수정 성공 / 타인 수정 거부 / 비관리자 삭제 거부 / 관리자 삭제 성공 테스트 추가**

```php
public function test_author_can_update_own_issue_via_detail_modal(): void
{
    $author = User::factory()->create(['team' => 'CS', 'name' => '김작성']);
    $record = SupportRecord::factory()->create([
        // KIND_ISSUE, TR_Name = author nameForCoReports(), Issue 등
    ]);

    Livewire::actingAs($author)
        ->test(InstitutionIssueList::class)
        ->call('openGroupDetail', /* matching group_key */)
        ->call('startIssueEdit', $record->ID)
        ->set('editIssue', '수정된 이슈')
        ->call('saveIssue')
        ->assertHasNoErrors();

    $this->assertSame('수정된 이슈', $record->fresh()->Issue);
}

public function test_non_author_cannot_start_issue_edit(): void { /* expect AuthorizationException / 403 */ }
public function test_non_admin_cannot_delete_issue(): void { /* assert record remains */ }
public function test_admin_can_delete_issue(): void { /* assertDatabaseMissing */ }
```

실제 factory/컬럼·`group_key` 생성은 기존 `InstitutionIssueTest` 헬퍼/패턴을 따른다. `ManagerNameNormalizer` / `nameForCoReports()`와 `TR_Name`이 맞게 세팅됐는지 확인한다.

- [ ] **Step 2: 테스트 실행해 실패 확인**

```bash
php artisan test --compact --filter=InstitutionIssueTest
```

- [ ] **Step 3: 커밋**

```bash
git add tests/Feature/InstitutionIssueTest.php
git commit -m "$(cat <<'EOF'
test: 기관 이슈 모달 수정·삭제 권한 시나리오 추가

EOF
)"
```

---

### Task 2: InstitutionIssueList 편집·삭제 로직

**Files:**
- Modify: `app/Livewire/InstitutionIssueList.php`

**Interfaces:**
- Consumes: `Gate::authorize('updateSupportRecord'|'deleteSupportRecords')`, `SupportRecordCascadeDeleter`, `SupportRecord::completionAttributes`, `InstitutionIssueTeacherGrouper`
- Produces: Task 1의 public 메서드 + 편집 프로퍼티

- [ ] **Step 1: 편집 프로퍼티와 start/cancel/save/delete 구현**

요지:

```php
public ?int $editingIssueId = null;
public bool $issueModalViewOnly = true;
public string $editSupportDate = '';
public string $editSupportTime = '13:00';
public string $editIssue = '';
public string $editToAccount = '';
public bool $editIsUrgent = false;
public bool $editCompleted = false;

public function startIssueEdit(int $id): void
{
    $record = $this->findIssueOrFail($id);
    Gate::authorize('updateSupportRecord', $record);
    $this->fillEditForm($record);
    $this->editingIssueId = (int) $record->ID;
    $this->expandedIssueId = (int) $record->ID;
    $this->issueModalViewOnly = false;
}

public function saveIssue(): void
{
    $record = $this->findIssueOrFail((int) $this->editingIssueId);
    Gate::authorize('updateSupportRecord', $record);
    $this->validate([/* date, time, issue required, to_account nullable, bools */]);
    $record->update([
        'Support_Date' => $this->editSupportDate,
        'Meet_Time' => ...,
        'Issue' => $this->editIssue,
        'TO_Account' => filled($this->editToAccount) ? $this->editToAccount : null,
        'is_urgent' => $this->editIsUrgent,
        ...SupportRecord::completionAttributes($this->editCompleted),
    ]);
    $this->issueModalViewOnly = true;
    $this->editingIssueId = null;
    $this->refreshSelectedGroup(); // group_key 유지하며 issues 재구성
    session()->flash('success', '기관 이슈가 저장되었습니다.');
}

public function deleteIssue(int $id): void
{
    Gate::authorize('deleteSupportRecords');
    $record = $this->findIssueOrFail($id);
    app(SupportRecordCascadeDeleter::class)->delete($record);
    // refresh or close modal if empty
    session()->flash('success', '기관 이슈가 삭제되었습니다.');
}
```

`findIssueOrFail`: `SupportRecord::query()->onlyIssues()->findOrFail($id)`.

`closeDetailModal` / `openGroupDetail`에서 편집 상태를 리셋한다.

- [ ] **Step 2: 테스트 통과 확인**

```bash
php artisan test --compact --filter=InstitutionIssueTest
```

- [ ] **Step 3: Pint + 커밋**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/InstitutionIssueList.php
git commit -m "$(cat <<'EOF'
feat: 기관 이슈 상세 모달에서 작성자·관리자 수정과 관리자 삭제

EOF
)"
```

---

### Task 3: 상세 모달 Blade UI

**Files:**
- Modify: `resources/views/livewire/institution-issue-list.blade.php`

- [ ] **Step 1: 펼친 이슈에 수정/삭제 버튼과 편집 폼 추가**

패턴:

- 읽기 모드: 기존 본문 + `@can` 버튼
- 편집 모드 (`$editingIssueId === $issue['id'] && ! $issueModalViewOnly`): date/time/textarea/checkbox + 저장/취소
- 삭제: `wire:click="deleteIssue(...)"` + `wire:confirm="이 기관 이슈를 삭제할까요?"`
- Gate에 모델 인스턴스가 필요하면 뷰에서 `SupportRecord::find`를 반복하지 말고, snapshot에 권한용 플래그를 PHP에서 넣거나 Livewire computed/`canUpdateIssue($id)` 헬퍼를 쓴다. **권장:** 컴포넌트 메서드 `canUpdateIssue(int $id): bool` / 뷰는 `@if($this->canUpdateIssue($issue['id']))` + `@can('deleteSupportRecords')`.

- [ ] **Step 2: 관련 Feature 테스트에 assertSee 보강(선택) 후 실행**

```bash
php artisan test --compact --filter=InstitutionIssueTest
```

- [ ] **Step 3: 커밋**

```bash
git add resources/views/livewire/institution-issue-list.blade.php tests/Feature/InstitutionIssueTest.php
git commit -m "$(cat <<'EOF'
feat: 기관 이슈 상세 모달에 수정·삭제 UI 추가

EOF
)"
```

---

### Task 4: 검증 마무리

- [ ] **Step 1: `composer run verify`**
- [ ] **Step 2: 자체 코드 리뷰** (Gate 양쪽 적용, issue-only 가드, Target 미변경, N+1 없음)
- [ ] **Step 3: PR 생성** (`main` 대상, 본문 5단락 템플릿)

---

## Risk notes

- `TR_Name`과 `nameForCoReports()` 불일치 시 작성자도 수정 불가 → 테스트 픽스처를 SupportList 테스트와 동일하게 맞출 것
- 그룹 리프레시 시 `group_key`가 바뀌지 않도록 Target/기관을 건드리지 말 것
- Soft UI만 숨기고 서버 authorize를 빼먹으면 실패 — 반드시 `Gate::authorize`
