# 상대 DB 기관 직접 연동

이 문서는 **HTTP API(`PUT /api/internal/...`)가 아니라**, 상대 쪽 **DB 테이블을 우리가 읽는 방식**만 설명한다. (REST 연동은 [`external-institution-ingest.md`](external-institution-ingest.md) 참고.)

## 목적

우리 시스템이 상대 원격 DB의 기관 연동 테이블을 주기적으로 읽어 `S_AccountName` 기관 마스터에 반영한다. 반대 방향은 상대 시스템이 우리 DB의 기관 마스터를 읽기 전용으로 조회하는 방식으로 운영한다.

## 상대 플랫폼에서 입력 → 우리 플랫폼으로 반영

계약 완료 등으로 **Portal Campus ID**, **사업자/기관번호** 같은 값이 상대 쪽에서 정해지면, 상대 담당자는 **상대 플랫폼(화면/업무 시스템)에 그 정보를 입력**한다. 입력이 저장될 때 반드시 **우리가 읽는 기관 연동 테이블**의 해당 기관 행에도 같은 값이 기록되어야 한다. (상대 시스템에서 “화면 저장 → 연동 테이블 upsert”가 한 묶기로 가야 우리로 넘어온다.)

### SK 코드와 함께 한 행으로 받기

**확정 SK 코드(`PARTNER_INSTITUTION_SK_COLUMN` → 우리 `S_AccountName.SKcode`)는 포털 ID·사업자/기관번호와 분리해서 보내면 안 된다.** 같은 연동 테이블 **같은 행**에 SK와 나머지 필드가 **같이** 있어야 우리 `PullInstitutionFromPartnerJob`이 한 번의 읽기로 해당 기관을 upsert할 수 있다. SK가 비어 있으면 우리 쪽에서 처리하지 못하고 실패 로그로 남는다.

- 상대는 “SK만 먼저”, “ID·사업자번호만 나중에 다른 행”처럼 쪼개 넣지 말고, **계약 확정 시점에 SK + 필요한 마스터 필드를 한 번에 같은 행에 반영**하는 것을 합의하는 것이 안전하다.
- 임시 SK → 확정 SK 치환이 필요하면 같은 행 또는 연속 처리에서 `replaces_sk`(`PARTNER_INSTITUTION_REPLACES_SK_COLUMN`) 규칙을 쓰고, 역시 **확정 SK가 있는 상태에서** 나머지 필드와 묶이도록 맞춘다.
- 임시 SK 확정은 `sk_code_requests` 브릿지 테이블을 사용할 수 있다. 상대는 `final_sk_code`, `status=completed`를 채울 때 `portal_campus_id`, `account_no`도 같은 행에 같이 입력한다. 우리 `ProcessSkCodeRequestsJob`은 SK 치환 후 이 두 값을 `S_AccountName.PortalCampusID`, `S_AccountName.AccountNo`에 반영한다.

기술적으로는 HTTP로 우리가 직접 받는 것이 아니라, **상대 DB 행이 갱신된 뒤** 우리 `PullInstitutionFromPartnerJob`이 그 행을 읽어 `S_AccountName`을 갱신하는 방식이다. 즉 **“상대가 연동 테이블에 보내 준 것”을 우리가 주기적으로 가져오는 것**이 곧 우리 플랫폼으로 정보가 오는 경로다.

- 반영 대상: `S_AccountName.PortalCampusID`, `S_AccountName.AccountNo` 등은 아래 매핑 테이블의 `portal_campus_id`, `account_no`에 대응하는 상대 컬럼에서 채워진다.
- 상대 DB 컬럼명이 기본값과 다르면 `.env`의 `PARTNER_INSTITUTION_PORTAL_CAMPUS_ID_COLUMN`, `PARTNER_INSTITUTION_ACCOUNT_NO_COLUMN`으로 맞춘다.
- 변경이 우리 쪽에서 잡히려면 `updated_at`(또는 합의한 `changed_at` 컬럼) 갱신, 또는 `status = pending` 같은 **대기 행만 읽기** 규칙과 맞춰야 한다.

## 데이터 흐름 (시스템)

1. 상대 업무에서 기관 정보가 입력·저장되며, **상대 DB의 기관 연동 테이블** 행이 함께 갱신된다.
2. 우리 Laravel Scheduler가 `PullInstitutionFromPartnerJob`을 실행한다.
3. Job이 상대 테이블을 읽고, `UpsertInstitutionFromExternal` 규칙으로 우리 기관 마스터를 upsert한다.
4. (선택) 상대 시스템은 우리 DB의 기관 마스터를 읽기 전용 계정으로 조회해 교차 검증한다.

## 우리 시스템이 읽는 상대 테이블

상대 테이블명과 컬럼명은 `.env`에서 조정한다.

| 설정 | 기본값 | 설명 |
| --- | --- | --- |
| `PARTNER_INSTITUTION_TABLE` | `institutions` | 상대 기관 연동 테이블 |
| `PARTNER_INSTITUTION_PRIMARY_KEY` | `id` | 상대 테이블 기본키 |
| `PARTNER_INSTITUTION_CHANGED_AT_COLUMN` | `updated_at` | 마지막 변경 시각 컬럼 |
| `PARTNER_INSTITUTION_STATUS_COLUMN` | 비움 | 처리 상태 컬럼. 있으면 pending 행만 읽음 |
| `PARTNER_INSTITUTION_MARK_REMOTE_ROWS` | `false` | true면 처리 결과를 상대 테이블에 마킹 |

## 상대 컬럼 → 우리 필드 매핑

| 상대 컬럼 설정 | 기본 컬럼명 | 우리 반영 대상 |
| --- | --- | --- |
| `PARTNER_INSTITUTION_SK_COLUMN` | `sk_code` | `S_AccountName.SKcode` |
| `PARTNER_INSTITUTION_REPLACES_SK_COLUMN` | `replaces_sk` | 임시 SK 치환용. DB 컬럼에 저장하지 않음 |
| `PARTNER_INSTITUTION_NAME_COLUMN` | `institution_name` | `S_AccountName.AccountName`, `S_Account_Information.Account_Name` |
| `PARTNER_INSTITUTION_GS_NO_COLUMN` | `gs_no` | `S_AccountName.GSno`, `S_GSNumber.GSnumber` |
| `PARTNER_INSTITUTION_CO_COLUMN` | `co` | `S_Account_Information.CO` |
| `PARTNER_INSTITUTION_TR_COLUMN` | `tr` | `S_Account_Information.TR` |
| `PARTNER_INSTITUTION_CS_COLUMN` | `cs` | `S_Account_Information.CS` |
| `PARTNER_INSTITUTION_PORTAL_CAMPUS_ID_COLUMN` | `portal_campus_id` | `S_AccountName.PortalCampusID` |
| `PARTNER_INSTITUTION_ACCOUNT_NO_COLUMN` | `account_no` | `S_AccountName.AccountNo` |

추가로 `english_name`, `portal_account_name`, `director`, `phone`, `account_tel`, `address`, `gubun`, `possibility`, `ls`, `gs_k`, `gs_e`, `customer_type`도 설정으로 매핑할 수 있다.

## 상대가 읽는 우리 테이블

상대 시스템에는 읽기 전용 계정만 제공하는 것을 권장한다.

| 테이블 | 주요 컬럼 | 설명 |
| --- | --- | --- |
| `S_AccountName` | `SKcode`, `AccountName`, `PortalCampusID`, `AccountNo`, `GSno`, `Phone`, `Address`, `LS`, `GS_K`, `GS_E` | 기관 마스터 |
| `S_Account_Information` | `SK_Code`, `Account_Name`, `CO`, `TR`, `CS`, `Customer_Type` | 기관 담당자/분류 정보 |
| `S_GSNumber` | `SKCode`, `AccountName`, `GSnumber` | GS 번호 보조 테이블 |

## `sk_code_requests`에서 상대가 채우는 컬럼

잠재기관 계약 완료 시 우리 쪽에서 임시 SK(`LEAD-*`)가 발급되면 `sk_code_requests`에 `status=pending` 행이 생성된다. 상대 플랫폼은 확정 SK를 입력할 때 아래 값을 같은 행에 함께 채운다.

| 컬럼 | 설명 |
| --- | --- |
| `final_sk_code` | 상대 플랫폼에서 확정한 최종 SK 코드 |
| `portal_campus_id` | Portal Campus ID. 값이 있으면 `S_AccountName.PortalCampusID`에 반영 |
| `account_no` | 사업자/기관번호. 값이 있으면 `S_AccountName.AccountNo`에 반영 |
| `co` | 담당 CO. 값이 있으면 `S_Account_Information.CO`에 반영 |
| `tr` | 담당 TR. 값이 있으면 `S_Account_Information.TR`에 반영 |
| `cs` | 담당 CS. 값이 있으면 `S_Account_Information.CS`에 반영 |
| `status` | 입력 완료 시 `completed`로 변경 |
| `completed_at` | 입력 완료 시각. 운영 추적용 |

`ProcessSkCodeRequestsJob`은 `status=completed`, `final_sk_code` 있음, 그리고 `applied_at`이 비어 있거나 `updated_at`이 `applied_at`보다 최신인 행을 처리한다. 최초 처리 때는 `temp_sk_code`를 `final_sk_code`로 치환하고, 이후 상대가 완료 행을 다시 수정하면 SK 치환은 건너뛰고 `portal_campus_id`, `account_no`, `co`, `tr`, `cs` 값만 다시 반영한다. MySQL/MariaDB 환경에서는 `sk_code_requests.updated_at`이 행 수정 시 자동 갱신되도록 설정하므로, 상대 시스템은 값만 수정하면 된다. `applied_at`은 우리 시스템이 처리 성공 후 채우는 값이므로 상대 시스템에서 수정하지 않는다. `portal_campus_id`, `account_no`가 비어 있으면 기존 마스터 값을 유지한다. `co`, `tr`, `cs`도 각각 비어 있으면 해당 담당 컬럼은 기존 값을 유지한다.

## 운영 합의 필요 사항

- **SK + 부가정보 동시 반영**: 연동 테이블 또는 `sk_code_requests` 한 행에 **확정 SK 코드**와 Portal Campus ID·사업자/기관번호 등이 **같이** 들어오는지(분리 저장 시 우리 upsert 실패·누락).
- **상대 쪽 입력 경로**: 어떤 화면/권한에서 위 값들을 넣고, 저장 시 연동 테이블의 **어느 컬럼**에 쓰는지.
- 상대 DB 접속 정보와 방화벽/IP 허용 범위
- 상대 테이블명, 기본키, 변경 시각 컬럼명
- 변경 감지 방식: `updated_at` 기준인지, `status = pending` 기준인지
- 상대 DB가 읽기 전용인지, 처리 상태 마킹을 위해 제한적 update 권한을 줄지
- 임시 SK를 확정 SK로 바꾸는 `replaces_sk`를 실제로 사용할지

## 안전장치

- `PullInstitutionFromPartnerJob`은 기본적으로 **확정 SK가 있는 행**에 대해 같은 패치에 `portal_campus_id`·`account_no`가 비어 있지 않은지 검사한다. 비어 있으면 upsert하지 않고 해당 행은 실패 처리되며 `external_assignment_inbound_logs`에 남는다. 단계적으로만 채우는 상대 DB라면 `.env`에서 `PARTNER_INSTITUTION_REQUIRE_SK_WITH_PORTAL_AND_ACCOUNT=false` 로 끌 수 있다(기본 `true`).
- 기존 인바운드 HTTP API는 `EXTERNAL_INSTITUTION_INGEST_ENABLED=false`가 기본값이라 비활성화된다.
- 기존 아웃바운드 HTTP Job은 `INSTITUTION_OUTBOUND_ENABLED=false`가 기본값이라 실행되지 않는다.
- 상대 DB 장애 시 Job은 3회 재시도하고, 실패 행은 `external_assignment_inbound_logs`에 `failed`로 남긴다.
