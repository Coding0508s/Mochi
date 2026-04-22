# GS Brochure 권한 정책

## 목적
- 공개 사용자, 직원 사용자, 관리자 사용자의 접근 범위를 명확히 분리합니다.
- URL 호환성을 유지하면서 서버(API)에서 동일한 권한 경계를 강제합니다.

## 역할 정의
- 공개 사용자(비로그인): 신청/본인 조회만 가능
- 직원 사용자(로그인): 전체 신청 내역 조회 및 신청 정보 수정 가능
- 관리자 사용자(`manageGsBrochureAdmin`): 운영/재고/설정 관리 가능

## 웹 라우트 정책
- 공개
  - `GET /co/gs-brochure/request` (신청 폼)
  - `GET /requestbrochure-list-v2` (본인 조회)
- 직원(로그인 필요)
  - `GET /co/gs-brochure/request?view=list` (전체 신청 내역)
  - `GET /co/gs-brochure/requests` (내부 진입점, 위 URL로 리다이렉트)
- 관리자
  - `GET /co/gs-brochure/admin/dashboard`
  - `GET /co/gs-brochure/admin/login` (관리자 권한이 없으면 신청 화면으로 리다이렉트)

## API 정책
- 공개 API
  - `POST /api/gs-brochure/verification/send`
  - `POST /api/gs-brochure/verification/verify`
  - `GET /api/gs-brochure/brochures`
  - `GET /api/gs-brochure/institutions`
  - `POST /api/gs-brochure/requests`
  - `GET /api/gs-brochure/requests/search`
- 직원 API (`web + auth`)
  - `GET /api/gs-brochure/requests`
  - `PUT /api/gs-brochure/requests/{id}`
  - `GET /api/gs-brochure/contacts`
- 관리자 API (`web + auth + can:manageGsBrochureAdmin`)
  - 요청 운영: 운송장 등록/삭제, 요청 삭제
  - 재고/브로셔 운영: 브로셔 생성/수정/삭제, 입출고, 물류→본사 이동, 이미지 업로드/삭제
  - 기타 운영: 재고 이력 생성, 관리자 계정 관리, 기관 관리, 데이터 리셋

## UI 정책
- 공용 사이드바(`shell-public`)는 로그인 시 `전체 신청 내역` 노출
- 관리자 권한인 경우에만 `대시보드로 가기` 노출
- 사용자명 표시는 `preferredDisplayName()` 기준(영어 이름 우선)

## Teams 알림 정책
- 신청/운송장 알림 카드 모두 `신청자` 필드를 표시
- `신청자` 값은 `contact_name` 우선, 비어 있으면 `외부 신청자`

## E2E 체크리스트 (직원/관리자)
- 직원 계정으로 `/co/gs-brochure/request?view=list` 접근 시 전체 신청 내역이 로드된다.
- 직원 계정으로 운송장 미등록 건을 수정하면 저장이 성공하고 목록에 반영된다.
- 직원 계정으로 운송장 등록/삭제, 신청 삭제 API 호출 시 권한 오류(403)가 반환된다.
- 관리자 계정으로 `/co/gs-brochure/admin/dashboard` 접근 시 대시보드가 정상 노출된다.
- 관리자 계정 사이드바에서 `대시보드로 가기`가 표시되고 클릭 시 대시보드로 이동한다.
- 신청 생성 시 Teams 카드에 `신청자`가 표시되고, 직원 신청은 로그인 사용자(영문명 우선)로 표시된다.
