# CS 반품현황 → Ecount 주문서 자동 생성 디자인

**날짜:** 2026-07-27  
**상태:** 승인됨 (스펙 작성)  
**대상 화면:** Store 반품 현황 (`store.returns.index` / `StoreReturnRegistrationForm`), CS 팀 메뉴  
**API:** `POST /OAPI/V2/SaleOrder/SaveSaleOrder`

## 1. 문제 / 목표

CS는 반품 처리 시 Ecount **주문수정(주문서)** 화면에 수동으로 반품 주문(수량 음수, 참조 `반품`)을 넣고 있다. MOCHI 반품현황과 이중 입력이 발생한다.

**목표:** CS 반품현황에서 **「Ecount 주문서 생성」** 버튼으로 `SaveSaleOrder`를 호출해 주문서를 만들고, 반환 `SlipNos`를 MOCHI에 저장한다.  
**완료** 버튼은 기존처럼 MOCHI 상태·Teams만 담당하며 Ecount를 호출하지 않는다.

## 2. 합의된 요구사항

| 항목 | 결정 |
|------|------|
| 연동 방식 | C — Ecount API로 주문서 **자동 생성** (번호만 수동 입력 아님) |
| 엔드포인트 | `SaleOrder/SaveSaleOrder` (Test: `sboapi{ZONE}`, 운영: `oapi{ZONE}`) |
| UI 트리거 | CS 전용 **「Ecount 주문서 생성」** 버튼 (완료와 **분리**) |
| 완료 버튼 | 기존 유지 — Ecount 호출 없음 |
| `CUST` | SK → `institution_external_mappings.erp_account_no` (거래처 코드) |
| `CUST_DES` | `erp_institution_name` 우선, 없으면 반품 `institution_name` |
| `EMP_CD` | 빈 문자열 — 거래처 기본 담당자에 위임 (Test Zone 검증) |
| `WH_CD` | config 고정 `GV01` |
| `REF_DES` | 고정 `"반품"` |
| `QTY` | `-1 * quantity` (음수) |
| 금액 | `PRICE` / `SUPPLY_AMT` / `VAT_AMT` = `0` |
| Class Name | MOCHI **신규 입력칸** → API 커스텀 필드로 전송 |
| 적요 `REMARKS` | MOCHI **신규 입력칸** |
| 배송지 | MOCHI **신규 입력칸** → API 커스텀 필드로 전송 |
| 원주문 전표 연결 | 1차 범위 밖 (Class Name 등으로 업무 구분) |

### Ecount 수동 입력 패턴 (참고)

주문수정 화면 기준: 수량 음수, 참조 `반품`, 창고 `GV01`, 단가 0.  
목록에서 반품 행은 `참조=반품`, Class Name은 반/용도 구분(예: `달빛처럼반`).

## 3. 접근 방식

**채택:** `EcountApiClient`에 `saveSaleOrder()` 추가 + 반품 그룹 → `SaleOrderList` 빌더 + CS 전용 생성 액션.

**이유:**

- 공식 주문 API가 있고, 세션/Zone 처리는 기존 클라이언트에 있음.
- 완료와 분리하면 API 실패가 MOCHI 「완료」 상태와 섞이지 않음.
- 품목코드는 반품 Ecount 품목 목록으로 이미 확보 가능.

**기각:**

- 완료 버튼에 Ecount 호출 묶기 — 실패 시 상태 불일치.
- 주문서 번호 수동 입력만 — 이중 입력 해소가 안 됨.
- 원주문 Slip 자동 차감 — 1차 복잡도·오매칭 위험.

## 4. 데이터 흐름

```text
CS: 「Ecount 주문서 생성」
  → 반품 그룹 로드
  → SK로 erp_account_no 조회 (없으면 중단)
  → Class Name / 적요 / 품목코드 필수 검증 (배송지는 선택)
  → SaleOrderList 구성 (라인당 BulkDatas)
  → SaveSaleOrder
  → 성공: SlipNos 저장 + flash
  → 실패: 에러 표시, DB 상태 변경 없음
```

### Payload 규칙

- 루트: `SaleOrderList` 배열.
- 품목 1줄 = `BulkDatas` 1객체. 헤더성 필드(`CUST`, `WH_CD`, `IO_DATE`, `REF_DES`, 배송지 등)는 라인마다 동일 반복.
- `IO_DATE`: `returned_at` → `Ymd`.
- `PROD_CD` / `PROD_DES`: 반품 품목 코드·표시명.
- 이미 `SlipNos`(또는 성공 상태)가 있으면 **재생성 차단** (메시지: 이미 생성된 주문서 있음). 강제 재생성은 1차 범위 밖.

### 최소 전송 예시 (개념)

```json
{
  "SaleOrderList": [{
    "BulkDatas": {
      "IO_DATE": "20260708",
      "CUST": "1069626354",
      "CUST_DES": "연세케이윙글리쉬",
      "EMP_CD": "",
      "WH_CD": "GV01",
      "REF_DES": "반품",
      "PROD_CD": "J11S-SSET-400",
      "PROD_DES": "Unit 11 Student Set",
      "QTY": "-1",
      "PRICE": "0",
      "SUPPLY_AMT": "0",
      "VAT_AMT": "0",
      "REMARKS": "(MOCHI 적요 입력값)"
    }
  }]
}
```

Class Name·배송지는 Ecount **기본입력화면 양식**에 매핑된 `U_MEMO*` / `ADD_TXT_*` / `ADD_LTXT_*` 키로 넣는다.  
키 번호는 구현 전 Test/메뉴얼·양식 설정으로 확정하고 `config/store.php`에 명시한다.

## 5. UI

- **노출:** CS 팀 메뉴(`TeamMenuContext::MENU_CS`)에서만.
- **위치:** **상세 모달**에 「완료」와 구분되는 **「Ecount 주문서 생성」** 버튼. 목록에는 생성 성공 시 `ecount_slip_no`만 표시(목록에서 직접 생성은 1차 없음).
- **입력:** 상세 편집에 Class Name, 적요, 배송지.
  - Class Name·적요: **품목 라인 단위** (반마다 다를 수 있음).
  - 배송지: **그룹(기관) 단위** (`freight` / `cs_team`처럼 그룹 저장 시 전 라인에 동일 값).
- **생성 전 필수:** `erp_account_no`, 각 라인 `PROD_CD`, Class Name, 적요. 배송지는 선택(빈 문자열 허용).
- **표시:** 저장된 Ecount 주문번호(`SlipNos`)를 상세에 표시하고, 목록에서도 확인 가능하게 한다.

## 6. DB / 설정

### In scope (스키마)

`store_return_registrations` (또는 그룹 단위 테이블이 있다면 그곳)에 추가:

| 컬럼 | 용도 |
|------|------|
| `class_name` | Class Name (라인) |
| `ecount_remarks` | 적요 (라인). 기존 `notes`(특이사항)와 **별개** |
| `shipping_address` | 배송지 (그룹 — `freight`와 같이 그룹 내 전 행에 동일 값) |
| `ecount_slip_no` | `SlipNos` 문자열 |
| `ecount_order_synced_at` | 성공 시각 (nullable) |

그룹 키(`registration_group_key`) 패턴을 유지한다. 배송지·`ecount_slip_no`·`ecount_order_synced_at`는 그룹 내 모든 행에 동일하게 저장한다.

### config

`config/store.php` `return_registration` (또는 `ecount`) 하에 예:

- `sale_order_endpoint` → `/OAPI/V2/SaleOrder/SaveSaleOrder`
- `warehouse_code` 반품용 기본 `GV01` (기존 `ECOUNT_WAREHOUSE_CODE`와 분리 가능)
- `ref_des` → `반품`
- `class_name_field` / `shipping_address_field` → 확정된 API 키 이름
- feature flag (예: `STORE_RETURN_ECOUNT_SALE_ORDER_ENABLED`) — 기본 off 권장, CS만 켜기

## 7. 범위

### In scope

- `EcountApiClient::saveSaleOrder`
- 반품 → payload 빌더 + CUST 해석 (`erp_account_no`)
- CS 「Ecount 주문서 생성」 UI·액션
- Class Name / 적요 / 배송지 입력·저장
- `SlipNos` 저장·표시
- Feature 테스트 (`Http::fake`)
- Test Zone 수동 스파이크 체크리스트 (음수 QTY, 빈 EMP_CD)

### Out of scope

- 완료 버튼에 Ecount 연동
- 원주문 전표 지정·자동 차감
- 담당자·창고 동적 선택
- `SlipNos` 존재 시 강제 재생성
- Ecount 거래처 마스터 동기화 전체
- Invoice Month 등 미합의 커스텀 컬럼 (양식 필수면 스파이크 후 스펙 보완)

## 8. 실패·엣지

| 상황 | 동작 |
|------|------|
| SK 없음 / 매핑 없음 / `erp_account_no` 빈값 | 생성 중단, 안내 |
| Class Name·적요 누락 | 생성 중단 |
| 품목이 Ecount 코드가 아님 | 생성 중단 |
| API 실패 | 에러 메시지, 상태·slip 변경 없음 |
| 이미 `ecount_slip_no` 있음 | 재생성 차단 |
| `EMP_CD` 빈 값인데 Ecount가 담당자 미채움 | 스파이크 결과 보고 후 거래처 조회 보강(후속) |

## 9. 테스트 계획

1. payload 빌더: 수량 음수, `REF_DES=반품`, `WH_CD=GV01`, 라인 수 = 품목 수.
2. CUST: SK → `erp_account_no` 매핑 / 없을 때 실패.
3. Livewire: CS만 생성 버튼 노출, 비CS 숨김.
4. 성공 시 `ecount_slip_no` 저장, 완료 상태와 무관.
5. 이미 slip 있으면 두 번째 생성 거부.
6. `Http::fake`로 SaveSaleOrder URL·본문 검증.

구현 전 수동: Test Zone에서 스크린샷과 동일 패턴 1건 성공 여부.

## 10. 위험

- Class Name·배송지 API 키 오매핑 → config로 분리, 스파이크 필수.
- `erp_account_no` 커버리지 부족 → 생성 전 명확한 에러.
- 음수 `QTY` API 거부 가능 → 스파이크 실패 시 설계 재검토.
- `EcountApiClient` 비대화 → `saveSaleOrder` + 빌더는 읽기 API와 섹션 분리 유지.

## 11. 구현 시 수정·추가 파일 (예상)

- `app/Services/Store/EcountApiClient.php`
- `app/Support/` 반품 SaleOrder 빌더·CUST 해석 (신규 클래스 권장)
- `app/Livewire/StoreReturnRegistrationForm.php`
- `resources/views/livewire/store-return-registration-form.blade.php`
- `app/Models/StoreReturnRegistration.php`
- migration (`class_name`, `ecount_remarks`, `shipping_address`, `ecount_slip_no`, `ecount_order_synced_at`)
- `config/store.php` / `.env.example`
- `tests/Feature/` (반품 Ecount 주문서 생성)

## 12. 구현 전 확인 체크리스트

1. 샘플 기관 SK의 `erp_account_no` = Ecount `CUST` 인지.
2. Test Zone `SaveSaleOrder` + 음수 수량 + `REF_DES=반품` 성공.
3. `EMP_CD=""` 시 담당자 자동 반영 여부.
4. Class Name·배송지에 해당하는 API 필드 키 확정 → config 반영.
