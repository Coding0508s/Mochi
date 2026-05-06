# 외부 플랫폼 기관 마스터 연동 (S_AccountName)

## 엔드포인트

- **메서드·경로**: `PUT /api/internal/institutions/{sk}`
- **인증**: `Authorization: Bearer <EXTERNAL_INSTITUTION_INGEST_TOKEN>`
- **SK**: URL 경로에 **외부 시스템 값 그대로** (예: `SK1234`). 필요 시 퍼센트 인코딩.

## 동작

- **Upsert**: 해당 `SKcode`가 없으면 생성, 있으면 갱신.
- **PATCH**: JSON에 **포함된 키만** DB에 반영. 없는 키는 기존 값 유지.
- **신규** 시 `institution_name` 필수 (본문).
- **계약 후 SK 치환**: 계약 완료로 생성된 임시 SK(예: `LEAD-520`)를 확정 SK로 바꿀 때는 경로에 확정 SK를 넣고, 본문에 `replaces_sk`로 기존 임시 SK를 전달.
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
| `replaces_sk` | 확정 SK로 치환할 기존 임시 SK. DB 컬럼에 직접 저장하지 않음 |

## curl 예시

```bash
curl -sS -X PUT "http://localhost:8000/api/internal/institutions/SK1234" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "X-Request-Id: $(uuidgen)" \
  -d '{"institution_name":"샘플 유치원","co":"Jane Doe","gs_no":"1.2"}'
```

익답 예: `{"ok":true,"sk":"SK1234","created":true}`

## 계약 후 임시 SK 치환 예시

잠재기관 계약 완료 후 우리 플랫폼에 `LEAD-520`으로 등록된 행을 상대 플랫폼의 확정 SK `SK1234`로 바꿀 때:

```bash
curl -sS -X PUT "http://localhost:8000/api/internal/institutions/SK1234" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "X-Request-Id: $(uuidgen)" \
  -d '{"replaces_sk":"LEAD-520","institution_name":"샘플 유치원","co":"Jane Doe","tr":"Trainer One","cs":"CS One"}'
```

- `SK1234`가 이미 기관 목록에 존재하면 `replaces_sk`는 사용할 수 없습니다. 기존 기관 갱신으로 처리해 주세요.
- `replaces_sk` 대상이 없거나 중복되어 있으면 `422`로 실패합니다. 먼저 임시 SK 데이터를 정리해야 합니다.
