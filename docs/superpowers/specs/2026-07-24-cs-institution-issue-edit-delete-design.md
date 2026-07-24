# CS 기관 이슈 — 상세 모달 수정·삭제 Design

**상태:** 승인됨 (대화 합의 2026-07-24)  
**브랜치:** `feature/cs-institution-issue-edit-delete`  
**관련:** PR #45 기관 이슈 그룹핑 / `InstitutionIssueList` 상세 모달

---

## 1. Problem

기관 이슈 목록(`InstitutionIssueList`)은 그룹 상세 모달에서 **읽기만** 가능하다.  
오타·상태 변경·잘못된 등록을 고치려면 수정·삭제가 필요하다.

## 2. Goals

- 상세 모달에서 **이슈 단위**로 수정할 수 있다.
- **삭제**는 관리자만 할 수 있다.
- **수정**은 작성자 본인 + 관리자만 할 수 있다.
- 기존 Gate·삭제 파이프라인을 재사용해 새 권한 체계를 만들지 않는다.

## 3. Non-goals

- Coach / 기관지원보고서(`SupportList`) 로직·UI 변경
- `/supports/create` 이슈 모드를 수정용으로 확장
- 교사(`Target`)·기관 재지정 (그룹 키 변경 위험) — 1차 제외
- 새 Gate / DB 마이그레이션
- 목록 행 인라인 편집

## 4. Permissions

| 액션 | Gate | 규칙 |
|------|------|------|
| 수정 | `updateSupportRecord` | 관리자(`hasFullAccess`) 또는 `TR_Name` ≈ `nameForCoReports()` |
| 삭제 | `deleteSupportRecords` | `canDeletePlatformData()` → 관리자만 (deputy 제외) |

UI와 서버 액션 **모두** 동일 Gate로 검사한다. 버튼 숨김만으로 끝내지 않는다.

## 5. UX

1. 그룹 행 클릭 → 기존 상세 모달 (기관·교사 요약 + 이슈 아코디언).
2. 이슈를 펼친 영역에서:
   - `@can('updateSupportRecord', $record)` → **수정**
   - `@can('deleteSupportRecords')` → **삭제** (확인 다이얼로그)
3. **수정** 클릭 → 해당 이슈만 편집 모드:
   - 편집: 발생일, 시간, 이슈 내용, 처리 내역, 긴급, 완료
   - 읽기 전용: 기관명, SK, 관련 교사, 작성자
4. **저장** → validation → update → 모달 그룹 데이터 갱신 → 다시 읽기 모드.
5. **취소** → 폼 리셋, 읽기 모드 복귀.
6. **삭제** 성공:
   - 그룹에 남은 이슈가 있으면 모달만 갱신
   - 없으면 모달 닫기
7. 다른 이슈를 펼치거나 모달을 닫으면 편집 중이면 취소(또는 저장 유도 없이 취소)한다.

## 6. Architecture

`InstitutionIssueList`에 SupportList 모달 패턴을 얇게 이식한다.

### 상태 (추가)

- `?int $editingIssueId`
- `bool $issueModalViewOnly` (기본 `true`)
- 편집 폼 필드: `editSupportDate`, `editSupportTime`, `editIssue`, `editToAccount`, `editIsUrgent`, `editCompleted`

### 메서드

| 메서드 | 역할 |
|--------|------|
| `startIssueEdit(int $id)` | 레코드 로드 + `updateSupportRecord` authorize + 폼 채우기 + viewOnly=false |
| `cancelIssueEdit()` | 폼 리셋 + viewOnly=true |
| `saveIssue()` | authorize + validate + update (`onlyIssues`/`KIND_ISSUE` 레코드만) + 그룹 리프레시 |
| `deleteIssue(int $id)` | `deleteSupportRecords` authorize + `SupportRecordCascadeDeleter` + 그룹/모달 정리 |

삭제 시 `DeleteSupportRecord` 액션은 SK 스코프 제약이 있어 **목록 삭제에는 쓰지 않는다**.  
`SupportList::deleteRecord`와 같이 `SupportRecordCascadeDeleter`를 직접 호출한다.

### 데이터 규칙

- 대상 레코드는 `record_kind = issue` (또는 `onlyIssues()`로 조회 가능)여야 한다. 일반 지원 보고서는 이 컴포넌트에서 수정·삭제하지 않는다.
- update 시 `Target`, `SK_Code`, `Account_Name`, `TR_Name`, `record_kind`는 변경하지 않는다.
- 완료 플래그는 `SupportRecord::completionAttributes($editCompleted)` 사용.

## 7. UI copy (한국어)

- 버튼: `수정` / `저장` / `취소` / `삭제`
- 삭제 확인: `이 기관 이슈를 삭제할까요?` (browser `wire:confirm` 또는 기존 앱 패턴)
- 성공 플래시: `기관 이슈가 저장되었습니다.` / `기관 이슈가 삭제되었습니다.`

## 8. Testing

Feature 테스트 (`InstitutionIssueTest` 확장 또는 전용 메서드):

1. 작성자 Livewire: `startIssueEdit` → `saveIssue` 성공, 내용 반영
2. 타인 CS: `startIssueEdit` / `saveIssue` → 403 또는 authorize 실패
3. 비관리자: `deleteIssue` → 거부, 레코드 잔존
4. 관리자: `deleteIssue` 성공, DB에서 제거
5. Blade: 작성자 세션에서 수정 버튼 노출, 삭제 버튼 비노출; 관리자에서 둘 다 노출

## 9. Acceptance criteria

- [ ] 작성자는 본인 이슈만 상세 모달에서 수정할 수 있다.
- [ ] 관리자는 모든 이슈를 수정·삭제할 수 있다.
- [ ] 비작성자·비관리자 CS는 수정·삭제 UI가 없고 서버에서도 거부된다.
- [ ] 삭제 후 그룹이 비면 모달이 닫힌다.
- [ ] 교사/기관 필드는 편집되지 않는다.
- [ ] `composer run verify` 통과.

## 10. Open follow-ups (out of this PR)

- 교사(`Target`) 재지정 UX
- `accessCsTeamFeatures`를 `/institution-issues` 라우트에 적용할지 여부
