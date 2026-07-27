# CS 반품 → Ecount 주문서 생성 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** CS 반품 상세에서 「Ecount 주문서 생성」으로 `SaveSaleOrder`를 호출하고, 반환 `SlipNos`를 반품 그룹에 저장한다. 「완료」와 Ecount 호출은 분리한다.

**Architecture:** 마이그레이션으로 반품 행에 Class Name·적요·배송지·slip 컬럼을 추가한다. `StoreReturnEcountCustResolver` / `StoreReturnSaleOrderPayloadBuilder`로 CUST·payload를 만들고, `EcountApiClient::saveSaleOrder()`가 **캐시 없이** POST한다. `StoreReturnRegistrationForm` 상세 모달에 입력·생성 버튼을 붙인다.

**Tech Stack:** Laravel 13, Livewire 4, PHPUnit, `Http::fake`, 기존 `EcountApiClient` 세션/Zone

**스펙:** `docs/superpowers/specs/2026-07-27-cs-return-ecount-sale-order-design.md`

## Global Constraints

- 「완료」(`completeReturnGroup`)에 Ecount 호출을 **넣지 않는다**
- CS 메뉴(`team_menu=cs` / `isCsTeamMenu`)에서만 생성 버튼 노출
- `CUST` = `institution_external_mappings.erp_account_no` (SK로 조회). `AccountNo`/기관명 매칭 금지
- `WH_CD` = config 고정 `GV01`, `REF_DES` = `반품`, `QTY` = 음수, 금액 0, `EMP_CD` = `""`
- `notes`(특이사항)와 `ecount_remarks`(적요)는 **별개**
- 이미 `ecount_slip_no`가 있으면 재생성 차단
- `saveSaleOrder`는 **응답 캐시를 쓰지 않는다** (`postEcountJson` 읽기 캐시 경로 재사용 금지)
- feature flag `store.return_registration.sale_order_enabled` 기본 `false` — 테스트에서는 `true`로 켠다
- Class Name / 배송지 API 키는 config (`class_name_field`, `shipping_address_field`). 스파이크 전 기본값: `U_MEMO1`, `ADD_LTXT_01_T` (확정 후 `.env`로 교체)
- 브랜치 권장: `feature/cs-return-ecount-sale-order`
- GSBrochure/ 수정 금지

## File Structure

| 파일 | 역할 |
|------|------|
| `database/migrations/2026_07_27_xxxxxx_add_ecount_sale_order_fields_to_store_return_registrations_table.php` | 컬럼 추가 |
| `config/store.php` / `.env.example` | sale order endpoint·창고·필드키·flag |
| `app/Models/StoreReturnRegistration.php` | fillable / casts |
| `database/factories/StoreReturnRegistrationFactory.php` | 새 필드 |
| `app/Support/StoreReturnEcountCustResolver.php` | SK → CUST / CUST_DES |
| `app/Support/StoreReturnSaleOrderPayloadBuilder.php` | 그룹 → `SaleOrderList` |
| `app/Services/Store/EcountApiClient.php` | `saveSaleOrder()` (무캐시 POST) |
| `app/Livewire/StoreReturnRegistrationForm.php` | 상세 필드·`createEcountSaleOrder` |
| `resources/views/livewire/store-return-registration-form.blade.php` | 입력·버튼·slip 표시 |
| `tests/Unit/StoreReturnEcountCustResolverTest.php` | CUST 해석 |
| `tests/Unit/StoreReturnSaleOrderPayloadBuilderTest.php` | payload |
| `tests/Feature/StoreReturnEcountSaleOrderTest.php` | Livewire + Http::fake |

참고(읽기만): `StoreReturnRegistrationForm::saveDetail` / `completeReturnGroup`, `EcountApiClient::postEcountJsonInternal`, `tests/Feature/StoreReturnRegistrationFormTest.php` (CS `team_menu` 패턴)

---

### Task 0: 구현 전 스파이크 (수동, 코드 없음)

**Files:** 없음 (결과만 메모 → config 기본값 확정)

- [ ] **Step 1:** Test Zone에서 `SaveSaleOrder`로 수량 `-1`, `REF_DES=반품`, `WH_CD=GV01`, `EMP_CD=""` 1건 전송해 성공·`SlipNos` 확인

- [ ] **Step 2:** Class Name·배송지가 실제로 들어가는 API 키 확인 → `class_name_field` / `shipping_address_field`에 반영할 값 기록

- [ ] **Step 3:** 샘플 SK의 `erp_account_no`가 Ecount `CUST`와 같은지 확인

실패 시: 스펙 §12 기준 재검토 후 Task 1 진행 여부를 사용자와 합의.  
성공 시(또는 키 미확정이어도 기본값으로 개발 가능 시): Task 1로 진행.

---

### Task 1: 마이그레이션·config·모델·팩토리

**Files:**
- Create: `database/migrations/2026_07_27_170000_add_ecount_sale_order_fields_to_store_return_registrations_table.php`
- Modify: `config/store.php`
- Modify: `.env.example`
- Modify: `app/Models/StoreReturnRegistration.php`
- Modify: `database/factories/StoreReturnRegistrationFactory.php`

**Interfaces:**
- Produces: DB 컬럼 `class_name`, `ecount_remarks`, `shipping_address`, `ecount_slip_no`, `ecount_order_synced_at`
- Produces config keys under `store.return_registration`:
  - `sale_order_enabled` (bool)
  - `sale_order_endpoint` (string)
  - `sale_order_warehouse_code` (string, default `GV01`)
  - `sale_order_ref_des` (string, default `반품`)
  - `sale_order_class_name_field` (string)
  - `sale_order_shipping_address_field` (string)

- [ ] **Step 1: 마이그레이션 작성**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_return_registrations', function (Blueprint $table): void {
            if (! Schema::hasColumn('store_return_registrations', 'class_name')) {
                $table->string('class_name', 100)->nullable()->after('notes');
            }
            if (! Schema::hasColumn('store_return_registrations', 'ecount_remarks')) {
                $table->string('ecount_remarks', 255)->nullable()->after('class_name');
            }
            if (! Schema::hasColumn('store_return_registrations', 'shipping_address')) {
                $table->string('shipping_address', 500)->nullable()->after('ecount_remarks');
            }
            if (! Schema::hasColumn('store_return_registrations', 'ecount_slip_no')) {
                $table->string('ecount_slip_no', 100)->nullable()->after('shipping_address');
            }
            if (! Schema::hasColumn('store_return_registrations', 'ecount_order_synced_at')) {
                $table->timestamp('ecount_order_synced_at')->nullable()->after('ecount_slip_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('store_return_registrations', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('store_return_registrations', 'class_name') ? 'class_name' : null,
                Schema::hasColumn('store_return_registrations', 'ecount_remarks') ? 'ecount_remarks' : null,
                Schema::hasColumn('store_return_registrations', 'shipping_address') ? 'shipping_address' : null,
                Schema::hasColumn('store_return_registrations', 'ecount_slip_no') ? 'ecount_slip_no' : null,
                Schema::hasColumn('store_return_registrations', 'ecount_order_synced_at') ? 'ecount_order_synced_at' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
```

- [ ] **Step 2: `config/store.php`의 `return_registration` 배열에 추가**

```php
'sale_order_enabled' => filter_var(env('STORE_RETURN_ECOUNT_SALE_ORDER_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
'sale_order_endpoint' => env('STORE_RETURN_ECOUNT_SALE_ORDER_ENDPOINT', '/OAPI/V2/SaleOrder/SaveSaleOrder'),
'sale_order_warehouse_code' => env('STORE_RETURN_ECOUNT_SALE_ORDER_WAREHOUSE', 'GV01'),
'sale_order_ref_des' => env('STORE_RETURN_ECOUNT_SALE_ORDER_REF_DES', '반품'),
'sale_order_class_name_field' => env('STORE_RETURN_ECOUNT_CLASS_NAME_FIELD', 'U_MEMO1'),
'sale_order_shipping_address_field' => env('STORE_RETURN_ECOUNT_SHIPPING_ADDRESS_FIELD', 'ADD_LTXT_01_T'),
```

- [ ] **Step 3: `.env.example`에 동일 키 주석/빈 값 추가**

- [ ] **Step 4: 모델 fillable에 5컬럼 추가, `ecount_order_synced_at` => `datetime` cast**

- [ ] **Step 5: 팩토리에 nullable 기본값 추가** (`class_name` => null 등)

- [ ] **Step 6: 마이그레이션 실행**

Run: `php artisan migrate --no-interaction --path=database/migrations/2026_07_27_170000_add_ecount_sale_order_fields_to_store_return_registrations_table.php`  
Expected: DONE (로컬/테스트 DB)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_27_170000_add_ecount_sale_order_fields_to_store_return_registrations_table.php \
  config/store.php .env.example \
  app/Models/StoreReturnRegistration.php \
  database/factories/StoreReturnRegistrationFactory.php
git commit -m "$(cat <<'EOF'
feat: 반품 등록에 Ecount 주문서 필드 추가

Class Name·적요·배송지·전표번호 저장과 sale order config를 준비한다.
EOF
)"
```

---

### Task 2: CUST 해석기 (Unit TDD)

**Files:**
- Create: `app/Support/StoreReturnEcountCustResolver.php`
- Create: `tests/Unit/StoreReturnEcountCustResolverTest.php`

**Interfaces:**
- Produces:
```php
final class StoreReturnEcountCustResolver
{
    /**
     * @return array{cust: string, cust_des: string}
     * @throws InvalidArgumentException 매핑/`erp_account_no` 없을 때
     */
    public function resolve(?string $institutionSkCode, string $fallbackInstitutionName): array
}
```

- [ ] **Step 1: 실패 테스트 작성**

```php
<?php

namespace Tests\Unit;

use App\Models\InstitutionExternalMapping;
use App\Support\StoreReturnEcountCustResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StoreReturnEcountCustResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_cust_from_erp_account_no(): void
    {
        InstitutionExternalMapping::query()->create([
            'institution_name' => '울산 북구 연세케이잉글리쉬',
            'account_no' => 'X',
            'sk_code' => 'SK-TEST-1',
            'erp_institution_name' => '연세케이윙글리쉬',
            'erp_account_no' => '1069626354',
            'portal_campus_id' => null,
        ]);

        $result = app(StoreReturnEcountCustResolver::class)
            ->resolve('SK-TEST-1', 'fallback name');

        $this->assertSame('1069626354', $result['cust']);
        $this->assertSame('연세케이윙글리쉬', $result['cust_des']);
    }

    public function test_throws_when_sk_missing_or_erp_account_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(StoreReturnEcountCustResolver::class)->resolve(null, '이름만');
    }
}
```

(`InstitutionExternalMapping` 생성에 필요한 컬럼이 테스트 DB에 없으면 기존 `ImportInstitutionExternalMappingsTest`처럼 테이블 시드/마이그레이션 확인.)

- [ ] **Step 2: 테스트 실행 (실패 확인)**

Run: `php artisan test --compact tests/Unit/StoreReturnEcountCustResolverTest.php`  
Expected: FAIL (class not found)

- [ ] **Step 3: 구현**

```php
<?php

namespace App\Support;

use App\Models\InstitutionExternalMapping;
use InvalidArgumentException;

final class StoreReturnEcountCustResolver
{
    /**
     * @return array{cust: string, cust_des: string}
     */
    public function resolve(?string $institutionSkCode, string $fallbackInstitutionName): array
    {
        $sk = trim((string) $institutionSkCode);
        if ($sk === '') {
            throw new InvalidArgumentException('기관 SK 코드가 없어 Ecount 거래처를 찾을 수 없습니다.');
        }

        $mapping = InstitutionExternalMapping::query()
            ->whereRaw('LOWER(sk_code) = ?', [mb_strtolower($sk)])
            ->first();

        $cust = trim((string) ($mapping?->erp_account_no ?? ''));
        if ($mapping === null || $cust === '') {
            throw new InvalidArgumentException('ERP 거래처코드(erp_account_no) 매핑이 없습니다.');
        }

        $custDes = trim((string) ($mapping->erp_institution_name ?? ''));
        if ($custDes === '') {
            $custDes = trim($fallbackInstitutionName);
        }

        return [
            'cust' => $cust,
            'cust_des' => $custDes !== '' ? $custDes : $cust,
        ];
    }
}
```

- [ ] **Step 4: 테스트 통과 확인**

Run: `php artisan test --compact tests/Unit/StoreReturnEcountCustResolverTest.php`  
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Support/StoreReturnEcountCustResolver.php tests/Unit/StoreReturnEcountCustResolverTest.php
git commit -m "$(cat <<'EOF'
feat: 반품용 Ecount CUST(erp_account_no) 해석기 추가

SK 코드로 외부 매핑의 ERP 거래처코드를 찾는다.
EOF
)"
```

---

### Task 3: SaleOrder payload 빌더 (Unit TDD)

**Files:**
- Create: `app/Support/StoreReturnSaleOrderPayloadBuilder.php`
- Create: `tests/Unit/StoreReturnSaleOrderPayloadBuilderTest.php`

**Interfaces:**
- Consumes: `StoreReturnEcountCustResolver`, `StoreReturnEcountProductOptions::selectionValueForStoredItemName` / `displayNameForStoredItemName`
- Produces:
```php
final class StoreReturnSaleOrderPayloadBuilder
{
    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\StoreReturnRegistration>  $items
     * @return array{SaleOrderList: list<array{BulkDatas: array<string, string>}>}
     */
    public function build(\Illuminate\Support\Collection $items): array
}
```
- 각 `BulkDatas`에 최소: `IO_DATE`, `CUST`, `CUST_DES`, `EMP_CD`(`""`), `WH_CD`, `REF_DES`, `PROD_CD`, `PROD_DES`, `QTY`(음수 문자열), `PRICE`/`SUPPLY_AMT`/`VAT_AMT`=`0`, `REMARKS`, config 키로 Class Name·배송지

- [ ] **Step 1: 실패 테스트**

```php
public function test_builds_negative_qty_and_ref_des_per_line(): void
{
    Config::set('store.return_registration.sale_order_warehouse_code', 'GV01');
    Config::set('store.return_registration.sale_order_ref_des', '반품');
    Config::set('store.return_registration.sale_order_class_name_field', 'U_MEMO1');
    Config::set('store.return_registration.sale_order_shipping_address_field', 'ADD_LTXT_01_T');

    // mapping + 2 StoreReturnRegistration same group with class_name, ecount_remarks, shipping_address
    // mock or real CustResolver

    $payload = app(StoreReturnSaleOrderPayloadBuilder::class)->build($items);

    $this->assertCount(2, $payload['SaleOrderList']);
    $first = $payload['SaleOrderList'][0]['BulkDatas'];
    $this->assertSame('GV01', $first['WH_CD']);
    $this->assertSame('반품', $first['REF_DES']);
    $this->assertSame('', $first['EMP_CD']);
    $this->assertSame('-2', $first['QTY']); // quantity 2 → -2
    $this->assertSame('0', $first['PRICE']);
    $this->assertArrayHasKey('U_MEMO1', $first);
    $this->assertArrayHasKey('ADD_LTXT_01_T', $first);
}
```

(품목코드: `item_name`이 표시명이면 `selectionValueForStoredItemName`으로 `PROD_CD` 해석. 테스트에서는 options를 stub하거나 item_name에 코드를 넣는다.)

- [ ] **Step 2: 실행 → FAIL**

Run: `php artisan test --compact tests/Unit/StoreReturnSaleOrderPayloadBuilderTest.php`

- [ ] **Step 3: 빌더 구현** — 빈 그룹/`class_name`·`ecount_remarks` 누락/`PROD_CD` 불가 시 `InvalidArgumentException` 메시지 한국어

- [ ] **Step 4: PASS 확인 후 Commit**

```bash
git add app/Support/StoreReturnSaleOrderPayloadBuilder.php tests/Unit/StoreReturnSaleOrderPayloadBuilderTest.php
git commit -m "$(cat <<'EOF'
feat: 반품 그룹을 Ecount SaleOrderList payload로 변환

음수 수량·참조 반품·고정 창고 규칙을 반영한다.
EOF
)"
```

---

### Task 4: `EcountApiClient::saveSaleOrder` (Feature/Unit + Http::fake)

**Files:**
- Modify: `app/Services/Store/EcountApiClient.php`
- Create: `tests/Feature/EcountApiClientSaveSaleOrderTest.php` (또는 Unit)

**Interfaces:**
- Produces:
```php
/**
 * @param  array{SaleOrderList: list<array{BulkDatas: array<string, string>}>}  $body
 * @return array{slip_nos: list<string>, raw: array<string, mixed>}
 */
public function saveSaleOrder(array $body): array
```
- 세션: 기존 `resolveSessionId()` / `obtainOapiSession`
- POST: `config('store.return_registration.sale_order_endpoint')` + `?SESSION_ID=`
- **캐시 저장/조회 없음**
- `Status`/`SuccessCnt`/`FailCnt`/`SlipNos` 파싱. 실패 시 `RuntimeException` (한국어 메시지)
- `SlipNos`가 비면 실패로 간주

- [ ] **Step 1: Http::fake 성공/실패 테스트 작성**

```php
Http::fake([
    'https://oapi.ecount.com/OAPI/V2/SaleOrder/SaveSaleOrder*' => Http::response([
        'Status' => '200',
        'Data' => [
            'SuccessCnt' => 1,
            'FailCnt' => 0,
            'SlipNos' => ['20260708-21'],
            'ResultDetails' => [['IsSuccess' => true]],
        ],
        'Error' => null,
    ], 200),
]);
Config::set('store.ecount.base_url', 'https://oapi.ecount.com');
Config::set('store.ecount.session_id', 'test-session');
Config::set('store.return_registration.sale_order_endpoint', '/OAPI/V2/SaleOrder/SaveSaleOrder');
// auto login off so session_id 사용

$result = app(EcountApiClient::class)->saveSaleOrder([
    'SaleOrderList' => [['BulkDatas' => [/* minimal */]]],
]);
$this->assertSame(['20260708-21'], $result['slip_nos']);
```

- [ ] **Step 2: FAIL → 구현 (전용 private `postEcountJsonNoCache` 또는 인라인 Http::post)**

기존 `parseEcountApiStatus` 재사용 가능하면 사용. Write 응답의 `SlipNos`는 `Data.SlipNos` 배열에서 string list로 정규화.

- [ ] **Step 3: PASS + Commit**

```bash
git add app/Services/Store/EcountApiClient.php tests/Feature/EcountApiClientSaveSaleOrderTest.php
git commit -m "$(cat <<'EOF'
feat: Ecount SaveSaleOrder 클라이언트 메서드 추가

주문서 생성은 응답 캐시 없이 호출한다.
EOF
)"
```

---

### Task 5: Livewire 상세 필드 저장·표시

**Files:**
- Modify: `app/Livewire/StoreReturnRegistrationForm.php`
- Modify: `resources/views/livewire/store-return-registration-form.blade.php`
- Modify: `tests/Feature/StoreReturnRegistrationFormTest.php` (또는 새 Feature 파일에 저장 케이스)

**Interfaces:**
- `detailShippingAddress` (string, 그룹)
- `detailItemRows[]`에 `className`, `ecountRemarks` 키 추가
- `saveDetail` validation:
  - `detailShippingAddress` => nullable|string|max:500
  - `detailItemRows.*.className` => nullable|string|max:100 (생성 시에만 필수 — 저장은 nullable 허용)
  - `detailItemRows.*.ecountRemarks` => nullable|string|max:255
- `loadDetailFieldsFromAnchor` / `emptyDetailItemRow` / mapGroup 에 필드 반영
- 목록 그룹 배열에 `ecount_slip_no` 표시용 키 추가
- 신규 모달(`institutionBlocks`)에는 1차 불필요(상세에서 입력) — YAGNI. 신규 등록 직후 상세에서 채움.

- [ ] **Step 1: 테스트 — 상세 저장 시 class_name / ecount_remarks / shipping_address DB 반영**

- [ ] **Step 2: FAIL → Livewire·Blade 수정**

상세 편집 UI:
- 운임/CS 팀 행 근처에 **배송지** input
- 품목 행에 **Class Name**, **Ecount 적요** input (`notes` 옆, 라벨 구분)

목록: `ecount_slip_no` 있으면 작은 텍스트로 표시 (컬럼 추가 또는 기관명 아래)

- [ ] **Step 3: PASS + Commit**

```bash
git add app/Livewire/StoreReturnRegistrationForm.php \
  resources/views/livewire/store-return-registration-form.blade.php \
  tests/Feature/StoreReturnRegistrationFormTest.php
git commit -m "$(cat <<'EOF'
feat: 반품 상세에 Class Name·적요·배송지 입력 추가

Ecount 주문서 생성에 필요한 값을 MOCHI에 저장한다.
EOF
)"
```

---

### Task 6: 「Ecount 주문서 생성」 액션 + UI

**Files:**
- Modify: `app/Livewire/StoreReturnRegistrationForm.php`
- Modify: `resources/views/livewire/store-return-registration-form.blade.php`
- Create: `tests/Feature/StoreReturnEcountSaleOrderTest.php`

**Interfaces:**
- Produces: `createEcountSaleOrder(int $anchorRegistrationId): void`
- 가드 순서:
  1. `isCsTeamMenu` 아니면 return/403
  2. `sale_order_enabled` false면 flash/error
  3. 그룹 로드, 이미 `ecount_slip_no` filled → addError/flash 「이미 생성된 주문서가 있습니다」
  4. Builder `build` (필수값 예외 → 사용자 메시지)
  5. `saveSaleOrder`
  6. 그룹 전 행 `ecount_slip_no` = implode 또는 첫 SlipNo, `ecount_order_synced_at` = now()
  7. 상세 open 중이면 reload, flash 성공
- **완료 상태/`completeReturnGroup` 변경 없음**

- [ ] **Step 1: Feature 테스트 작성**

```php
public function test_cs_can_create_ecount_sale_order_and_stores_slip_no(): void
{
    Config::set('store.return_registration.sale_order_enabled', true);
    // mapping, return group with class_name, ecount_remarks, product code item_name
    Http::fake([/* SaveSaleOrder success SlipNos */]);

    Livewire::actingAs($user)
        ->withQueryParams(['team_menu' => 'cs'])
        ->test(StoreReturnRegistrationForm::class)
        ->call('openDetailModal', $anchor->id)
        ->call('createEcountSaleOrder', $anchor->id)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('store_return_registrations', [
        'id' => $anchor->id,
        'ecount_slip_no' => '20260708-21',
    ]);
}

public function test_create_blocked_when_slip_already_exists(): void { /* ... */ }
public function test_create_button_not_for_logistics_menu(): void { /* assertDontSee or method no-op */ }
public function test_complete_does_not_call_ecount(): void {
    Http::fake();
    // call completeReturnGroup
    Http::assertNothingSent(); // or assertNotSent SaveSaleOrder
}
```

- [ ] **Step 2: FAIL → `createEcountSaleOrder` 구현**

- [ ] **Step 3: Blade — 상세 모달 푸터/액션 영역**

```blade
@if($this->isCsTeamMenu && config('store.return_registration.sale_order_enabled'))
    <button type="button"
            wire:click="createEcountSaleOrder({{ $detailAnchorId }})"
            wire:confirm="Ecount 주문서를 생성할까요?"
            class="...">
        Ecount 주문서 생성
    </button>
@endif
```

이미 slip 있으면 버튼 disabled + slip 번호 텍스트.

- [ ] **Step 4: PASS**

Run: `php artisan test --compact tests/Feature/StoreReturnEcountSaleOrderTest.php`

- [ ] **Step 5: 관련 기존 테스트 회귀**

Run: `php artisan test --compact tests/Feature/StoreReturnRegistrationFormTest.php`

- [ ] **Step 6: Pint + Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/StoreReturnRegistrationForm.php \
  resources/views/livewire/store-return-registration-form.blade.php \
  tests/Feature/StoreReturnEcountSaleOrderTest.php
git commit -m "$(cat <<'EOF'
feat: CS 반품 상세에서 Ecount 주문서 생성

SaveSaleOrder 호출 후 SlipNos를 그룹에 저장한다. 완료 버튼과 분리한다.
EOF
)"
```

---

### Task 7: 검증 마무리

- [ ] **Step 1:** `vendor/bin/pint --dirty --format agent`

- [ ] **Step 2:**

Run: `php artisan test --compact tests/Unit/StoreReturnEcountCustResolverTest.php tests/Unit/StoreReturnSaleOrderPayloadBuilderTest.php tests/Feature/EcountApiClientSaveSaleOrderTest.php tests/Feature/StoreReturnEcountSaleOrderTest.php tests/Feature/StoreReturnRegistrationFormTest.php`

Expected: all PASS

- [ ] **Step 3:** (선택) `composer run verify` — 사용자 요청 시

- [ ] **Step 4:** 스펙 §12 수동 체크리스트가 남았으면 PR 본문에 기록

---

## Spec coverage (self-review)

| 스펙 요구 | Task |
|-----------|------|
| SaveSaleOrder API | 4 |
| CUST = erp_account_no | 2 |
| EMP 빈값 / WH GV01 / REF 반품 / QTY 음수 | 3 |
| Class Name·적요·배송지 입력 | 5 |
| 생성 버튼 분리 (완료 비연동) | 6 |
| SlipNos 저장·재생성 차단 | 6 |
| feature flag / config 필드키 | 1 |
| 테스트 | 2–6 |
| 스파이크 | 0 |

Placeholder 없음. `saveSaleOrder` 무캐시·완료 미연동을 Global Constraints에 명시함.
