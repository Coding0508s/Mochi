# 외부 플랫폼 기관 마스터 연동 (S_AccountName)

## 엔드포인트

- **메서드·경로**: `PUT /api/internal/institutions/{sk}`
- **인증**: `Authorization: Bearer <EXTERNAL_INSTITUTION_INGEST_TOKEN>`
- **SK**: URL 경로에 **외부 시스템 값 그대로** (예: `SK1234`). 필요 시 퍼센트 인코딩.

## 동작

- **Upsert**: 해당 `SKcode`가 없으면 생성, 있으면 갱신.
- **PATCH**: JSON에 **포함된 키만** DB에 반영. 없는 키는 기존 값 유지.
- **신규** 시 `institution_name` 필수 (본문).
- **테이블**: `S_AccountName`(마스터), `S_Account_Information`, (존재 시) `S_GSNumber`.

## 환경 변수

| 변수 | 설명 |
|------|------|
| `EXTERNAL_INSTITUTION_INGEST_TOKEN` | Bearer 토큰. 비우면 `503` |
| `EXTERNAL_INSTITUTION_INGEST_CLEARS_HIDDEN` | `true`면 upsert 후 `institution_visibility_overrides`에서 해당 `sk_code` 행 삭제 |

## JSON 필드 → DB 컬럼

| JSON (스네이크) | DB |
|------------------|-----|
| `institution_name` | `S_AccountName.AccountName` |
| `english_name` | `EnglishName` |
| `portal_account_name` | `PortalAccountName` |
| `account_no` | `AccountNo` |
| `gs_no` | `GSno` + `S_GSNumber.GSnumber` |
| `director` | `Director` |
| `phone` | `Phone` |
| `account_tel` | `AccountTel` |
| `address` | `Address` (마스터 및 담당 테이블 동기 시 담당 쪽도 동일 키로 반영) |
| `gubun` | `Gubun` |
| `possibility` | `Possibility` |
| `ls` | `LS` |
| `gs_k` | `GS_K` |
| `gs_e` | `GS_E` |
| `co` | `S_Account_Information.CO` |
| `tr` | `TR` |
| `cs` | `CS` |
| `customer_type` | `Customer_Type` |

## curl 예시

```bash
curl -sS -X PUT "http://localhost:8000/api/internal/institutions/SK1234" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "X-Request-Id: $(uuidgen)" \
  -d '{"institution_name":"샘플 유치원","co":"Jane Doe","gs_no":"1.2"}'
```

익답 예: `{"ok":true,"sk":"SK1234","created":true}`
