# Handoff Note — 기관 / SK 코드 / 팀 필터 작업 인수

> 다음 Agent(또는 팀원)가 이어받기 위한 인수 문서입니다.
> 작성 시점: 2026-05-11
> 직전 작업 트랜스크립트: `/Users/boseokhur/.cursor/projects/Users-boseokhur-Desktop-Mocchi-Figma-mocchi-platform/agent-transcripts/778902ce-0763-4fb0-8d48-a9a1c3a99f67/778902ce-0763-4fb0-8d48-a9a1c3a99f67.jsonl`

---

## 1. 전체 작업 맥락

크게 세 갈래의 작업이 순차적으로 진행되었습니다.

1. **잠재기관 ↔ 기관리스트 전환 안전장치**
   - 계약완료 / 미계약 토글 실수 방지 (2단계 확인)
   - 미계약 복귀 시 기관리스트에서 숨기기 (`institution_visibility_overrides`)
2. **`sk_code_requests` ↔ `S_AccountName` / `S_Account_Information` 양방향 동기화**
   - 외부(SK 포털) 수정 ↔ 자사 InstitutionList 수정이 서로 반영
   - 규칙: **"마지막 수정이 이긴다(last write wins)"**
3. **팀 / 담당자 목록을 `employee` 마스터 기준으로 필터링**
   - 퇴사자·비직원 이름이 팀 목록 / 일정 / 담당자 드롭다운에 새는 문제 차단

---

## 2. 핵심 데이터 흐름

```
┌─────────────────────┐     reverseSyncToSkCodeRequest()
│ InstitutionList     │ ──────────────────────────────┐
│ (Livewire)          │                                │
└─────────┬───────────┘                                ▼
          │  saveDetailFields()         ┌──────────────────────┐
          │  saveManagers()             │ sk_code_requests     │
          ▼                             │  (updated_at,        │
┌─────────────────────┐                 │   applied_at)        │
│ S_AccountName       │ ◄──────────────│                      │
│ S_Account_Information│  ProcessSkCode │ status = completed   │
└─────────────────────┘  RequestsJob    └──────────────────────┘
                         (updated_at > applied_at 일 때만 재적용)
```

- **루프 방지 핵심**:
  `InstitutionList`가 역방향 동기화할 때 `updated_at = applied_at = now()` 로 동시에 찍어
  job 재실행 조건(`updated_at > applied_at`)을 만족하지 않게 함.
  Eloquent `timestamps = false`로 설정 후 수동 저장.
- **외부 → 내부 경로**:
  `UpsertInstitutionFromExternal` Action + `UpsertExternalInstitutionRequest`(키 정규화) + `ProcessSkCodeRequestsJob`.
  빈 값으로 마스터를 덮어쓰지 않음.

---

## 3. 파일별 변경 요약

### Livewire / UI

- **`app/Livewire/PotentialInstitutionList.php`**
  - 계약 변경 2단계 확인 (`requestContractChange` → `confirmContractChange`)
  - 미계약 복귀 시 `institution_visibility_overrides` 에 `updateOrInsert` 로 숨김 처리
- **`app/Livewire/InstitutionList.php`** ⭐ 최근 변경 집중 지점
  - `reverseSyncToSkCodeRequest($skCode, $values)` 추가 → `saveDetailFields` / `saveManagers` 에서 호출
  - **부서 매핑 상수**:
    - `DEPT_CO = 'A02'` (Consulting Team)
    - `DEPT_TR = 'A05'` (Training Team, Coach)
    - `DEPT_CS = 'A03'` (Customer Support Team)
  - `managerOptionsForDept(string $deptNo)` 헬퍼로 CO / Coach / CS 드롭다운 3개를 일원화
    - `STATUS = 1` 활성 직원만, `ENGLISHNAME` 우선 / 비면 `KOREANAME` 폴백
    - `Schema::hasTable('employee')` 가드로 테스트 환경 안전
- **`app/Livewire/InboundNotificationBell.php`**
  - `resolveInboundInstitutionName()` 이 다양한 `raw_body` 구조에서 기관명을 추출하도록 보강
- **`app/Livewire/TeamScheduleCalendar.php`**
  - `teamUsers()`: `whereNotNull('employee_empno')->whereHas('employee')` 추가
  - `visibleSchedules()` 팀 뷰: 동일 조건을 `$ownerQuery` 에 추가 (단, 본인 일정은 그대로 보임)

### Jobs / Actions / Requests

- **`app/Jobs/ProcessSkCodeRequestsJob.php`**
  - `syncInstitutionFields` 에 `institution_name` 동기화 포함
  - `S_Account_Information` 은 `updateOrCreate()` 사용 (레코드 없을 때도 생성)
  - 최종 상태는 `updated_at > applied_at` 만 트리거 (역동기화 루프 방지).
    **중간에 도입했던 `isDataInSync()` 우회는 제거됨.**
- **`app/Jobs/PullInstitutionFromPartnerJob.php`**
  - `services.partner_institutions.sync_institution_name` 플래그로 institution_name 동기화 토글
- **`app/Actions/UpsertInstitutionFromExternal.php`**
  - 빈 / 널 `institution_name` 이 들어와도 기존 `AccountName` 을 덮지 않음
- **`app/Http/Requests/UpsertExternalInstitutionRequest.php`**
  - `prepareForValidation()` 에서 `institutionName`, `account_name` 등 외부 키 이름들을 `institution_name` 으로 정규화

### 설정 / 환경

- **`config/services.php`**, **`.env.example`**
  - `services.partner_institutions.sync_institution_name` 추가

---

## 4. 의사결정 / 트레이드오프

- **last write wins + `updated_at = applied_at` 트릭**:
  양방향에서 가장 단순 / 안전한 동기화 규칙. 별도 버전 컬럼 도입은 과한 엔지니어링이라 채택 안 함.
- **담당자 드롭다운 = 부서 매핑(A02 / A05 / A03)**:
  사용자가 명시적으로 선택. "현재 저장값이 매핑에 없으면 옵션에 포함" 옵션은 채택하지 않았음
  (아래 "주의점 #1" 참고).
- **부서 코드는 클래스 상수로 하드코딩**:
  매핑이 3건이고 변경 빈도 낮음. 잦아지면 `config/services.php` 나 별도 매핑 테이블로 분리 권장.
- **`Employee` 모델은 `STATUS = 1` 이 활성**:
  People - Employees 화면과 동일한 기준.

---

## 5. 알려진 주의점 / 향후 개선 후보

1. **드롭다운에 없는 현재 저장값**:
   어떤 기관의 CO / TR / CS 에 매핑 부서가 아닌 직원이나 employee 에 없는 이름이 저장돼 있으면,
   모달 `<select>` 옵션에는 안 보입니다. 사용자가 그대로 두기로 선택했지만, 추후 "현재 저장값은 옵션으로 union" 처리가 필요해질 수 있음.
   (`managerOptionsForDept` 결과에 `selectedInstitution['accountInfo']['CO/TR/CS']` 머지)
2. **외부 동기화 잡 캐시 이슈**:
   과거 `php artisan queue:work` 가 옛 코드를 잡고 있어 동기화가 안 되는 버그가 있었음.
   코드 배포 후에는 큐 워커를 반드시 재시작해야 함.
   (배포 스크립트에 `queue:restart` 신호가 들어가 있는지 점검 권장)
3. **DB 직접 수정(예: DBeaver)으로 인한 ON UPDATE CURRENT_TIMESTAMP 미동작**:
   `sk_code_requests` 에 `2026_05_08_210000_set_sk_code_requests_updated_at_auto_update.php` 마이그레이션이 있지만,
   일부 GUI 도구는 트리거하지 않음. 운영 데이터를 손으로 만질 때 주의.
4. **부서 / 직책 한글 컬럼**:
   `employee` 테이블에 `직급`, `직책`, `근무형태` 등 한글 컬럼이 살아있음.
   새 기능 추가 시 한글 컬럼 참조하지 말고 영문 컬럼(`JOB`, `WORKDEPT`, …)을 우선 사용.
5. **ADR 미작성**:
   양방향 동기화 규칙 · 부서 매핑 상수는 아키텍처 결정이므로 `docs/adr/` 에 ADR 로 남기면 좋습니다(아직 안 함).

---

## 6. 테스트 현황

| 파일 | 핵심 검증 |
|---|---|
| `tests/Feature/InstitutionListTest.php` | reverse sync(saveDetail / saveManagers), 담당자 드롭다운 부서 매핑 / 활성 / 폴백 |
| `tests/Feature/ProcessSkCodeRequestsTest.php` | `updated_at > applied_at` 동작, reverse-synced 행이 다시 적용되지 않음, institution_name 재적용 등 |
| `tests/Feature/TeamScheduleCalendarTest.php` (신규) | 팀 목록 / 팀 일정이 employee 연결 사용자만 노출 |
| `tests/Feature/ExternalInstitutionIngestTest.php` | 외부 inbound 정규화 / 빈값 보호 |
| `tests/Feature/PartnerInstitutionSyncTest.php` (신규) | 파트너 DB 풀 동기화 동작 |

마지막 실행 시점 기준 `InstitutionListTest + ProcessSkCodeRequestsTest` = **26 passed / 103 assertions**, 린트 0.

---

## 7. 미완료 / 다음 후보

- 현재 완료된 모든 TODO 는 닫힘.
- 사용자 요구에서 자연스럽게 이어질 수 있는 다음 후보:
  - 위 "주의점 #1" 의 보완 (저장값 union 옵션)
  - 양방향 동기화 / 부서 매핑에 대한 ADR 작성 (`docs/adr/NNNN-*.md`)
  - 부서 매핑이 잦아지면 매핑을 config 로 추출
  - 담당자 권한(`Policy`) 검토 — 현재는 표시만 필터, 저장 권한 검증은 별도 확인 필요

---

## 8. 작업 시 반드시 지킬 규칙(워크스페이스 룰 요약)

- **코드 작성 전에 한국어로 짧게라도 계획 합의** (PRD-first). 버그 한 줄 수정은 예외.
- **Laravel first**: 가능한 한 Eloquent / FormRequest / Policy / Queue / Schema 우선.
- **DB 스키마 / SQL 은 참고용**: 임의 SQL 실행이나 스키마 직접 변경 금지. 변경은 마이그레이션으로.
- **응답은 한국어, 초보자 관점**으로 설명. "왜 이 방식인지" + "흔한 실수"를 함께.
- **Surgical changes**: 요구된 라인 외 주변 코드 / 포맷 변경 금지.
- **Blade 구조**: `pages/`, `components/`, `partials/` + `x-admin.*` / `x-landing.*` / `x-dashboard.*` 네이밍.

---

## 9. 빠른 진입 포인트 (Code Pointers)

- 양방향 동기화 로직을 더 손볼 때:
  `app/Livewire/InstitutionList.php::reverseSyncToSkCodeRequest()` ↔ `app/Jobs/ProcessSkCodeRequestsJob.php`
- 담당자 드롭다운:
  `app/Livewire/InstitutionList.php::managerOptionsForDept()` + 상수 `DEPT_CO / DEPT_TR / DEPT_CS`
- 팀 / 일정 필터:
  `app/Livewire/TeamScheduleCalendar.php::teamUsers()`, `::visibleSchedules()`
- 외부 inbound 진입점:
  `app/Http/Requests/UpsertExternalInstitutionRequest.php` → `app/Actions/UpsertInstitutionFromExternal.php`

---

## 10. 새 Agent 가 이 문서를 활용하는 법

새 채팅 첫 메시지에 다음처럼 첨부하세요.

```text
@.cursor/plans/handoff-institution-sk-team.md 를 먼저 읽고 시작해줘.
오늘 작업은 [원하는 작업 내용] 이야.
```

필요하면 원본 트랜스크립트도 추가로 참고할 수 있습니다 (문서 상단 경로 참고).
