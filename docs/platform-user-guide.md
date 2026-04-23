# Mocchi Platform 사용 안내서

## 1. 문서 목적

이 문서는 `GrapeSEED MOCHI` 플랫폼의 실제 화면과 권한 기준으로 정리한 사용자 안내서입니다.  
대상 범위는 다음과 같습니다.

- 사내 로그인 사용자의 업무 화면
- 관리자/운영자의 설정 화면
- `GS Brochure` 외부 신청자 및 내부 운영자 화면

## 2. 플랫폼 개요

이 플랫폼은 GrapeSEED 운영 업무를 한 곳에서 처리하기 위한 통합 업무 시스템입니다.

주요 기능은 다음과 같습니다.

- 직원 정보 조회
- 기관 정보 및 담당자 관리
- 기관 연락처 관리
- 기관 지원 보고서 작성 및 조회
- 잠재기관 등록 및 계약 전환 관리
- Store 재고 및 판매 이력 조회
- Salesforce 관련 파일 관리
- GS Brochure 신청 및 운영
- 팀/공통코드/역할 관리

## 3. 접속 및 로그인

### 3.1 기본 진입

- 로그인 전 루트(`/`)에 접속하면 로그인 화면으로 이동합니다.
- 로그인 후 루트(`/`) 또는 `대시보드`로 접속하면 기본적으로 `기관리스트` 화면으로 이동합니다.

### 3.2 상단 영역

상단 바에서는 다음 기능을 사용할 수 있습니다.

- `Profile`: 내 계정 정보 수정
- `로그아웃`
- 외부 링크
  - `Portal`
  - `eCount`
  - `Coaching`

### 3.3 로그인 가능 조건

- `users.is_active = true` 인 계정만 로그인에 성공합니다. 비활성 계정은 자격 증명이 맞아도 로그인되지 않습니다.

## 4. 권한 체계

플랫폼은 로그인 여부뿐 아니라 사용자 권한에 따라 기능이 달라집니다.

### 4.1 용어와 판별 기준

- **Country Manager(CM) 판별**: 로그인 이메일이 `employee` 테이블의 이메일과 일치하고, 해당 행의 `JOB`이 `Country Manager` 계열(대소문자·공백 무시)일 때입니다. DB 플래그만으로는 CM이 되지 않습니다.
- **Full Access**: **`is_admin = true` 일 때만** 해당합니다. CM JOB만으로는 관리자 메뉴·부서 변경·계정 관리 등 Full Access 전용 Gate가 열리지 않습니다.
- **People 직원 편집 Gate (`editEmployeeProfile`)**: **CM이거나 `is_admin`인 경우** People에서 직원 모달을 열고, 직원 본문 필드·팀 생성/삭제 등 해당 Gate로 보호되는 작업을 할 수 있습니다(부서 **이동**만 별도 Gate).
- **Gate 이름**(코드와 동일): `editEmployeeProfile`, `manageEmployeeDepartment`, `manageTeamStructure`, `manageStoreInventory`, `manageGsBrochureAdmin`, `manageUserAccounts`. 실제 허용 여부는 `App\Providers\AppServiceProvider`에서 정의됩니다.

### 4.2 역할별 기능 체크리스트

아래 표에서 **CM**은 직원 `JOB` 기준 Country Manager, **관리자(비 CM)**는 `is_admin`만 해당하고 직원 `JOB`은 CM이 아닌 경우, **GS 전용**은 `is_gs_brochure_admin`만 켜져 있고 Full Access가 아닌 경우를 뜻합니다.

| 기능 | 일반 직원 | CM (JOB) | 관리자 (`is_admin`) | GS 전용 |
| --- | :---: | :---: | :---: | :---: |
| 대부분 업무 화면 조회 | O | O | O | O (허용된 메뉴 범위) |
| GS Brochure 내부 신청 목록 조회 | O | O | O | O |
| **People** 직원 본문(이름·직책 등)·팀 생성/삭제 UI | X | O | O | X |
| **People** 소속 **부서 변경**(다른 부서로 이동) | X | X | O | X |
| **People** 모달 **계정** 필드·직원 등록 모달 등 | X | X | O¹ | X |
| **Setup** 메뉴·팀·공통코드·역할·직원 등록 | X | X | O | X |
| **Store** 품목 관리·실제수량 수정 | X | X | O | X |
| **GS Brochure** 관리자 대시보드 | X | O² | O | O |

¹ `manageUserAccounts` + `PEOPLE_MODAL_ACCOUNT_EDIT_ENABLED`(기본 `true`)일 때 계정 UI가 보입니다.  
² CM이면서 `is_gs_brochure_admin`이면 대시보드 가능(Full Access와 무관).

**주의**: CO 팀 소속이면서 Full Access가 아닌 사용자(일반 직원·CM 포함)는 **기관리스트** 등에서 담당 CO 이름과 매칭되는 행만 보도록 스코프될 수 있습니다. CM이 예전처럼 전 기관이 보이지 않을 수 있습니다.

### 4.3 일반 로그인 사용자

- 대부분의 조회 화면 접근 가능
- GS Brochure 내부 신청 내역 조회 가능
- 기관/연락처/지원 내역 등 일반 업무 화면 사용 가능

### 4.4 Full Access 사용자 (`is_admin`만 해당)

- `is_admin = true` 인 사용자만 Full Access입니다.

Full Access 사용자는 다음을 포함합니다.

- Setup 메뉴(사이드바) 노출 및 해당 화면 사용
- Store 재고 품목 관리 및 실제수량 수정
- 팀 구조 / 공통코드 / 역할 관리
- 직원 등록(Setup)
- GS Brochure 관리자 대시보드 접근(아래 4.5와 동일 Gate)

### 4.5 GS Brochure 관리자 대시보드

다음 중 하나이면 `manageGsBrochureAdmin`으로 관리자 대시보드에 접근할 수 있습니다.

- Full Access 사용자
- `is_gs_brochure_admin = true` 인 사용자(GS 전용 역할로 Full Access 없이도 대시보드만 가능)

## 5. 메뉴 구조

사이드바 기준 주요 메뉴는 다음과 같습니다.

### 5.1 People

- `전체 Employees`
- 부서별 Employees 목록

### 5.2 Teams > CO Team

- `기관리스트`
- `기관연락처보기`
- `기관지원보고서`
- `잠재기관리스트`
- `잠재기관보기`
- `GS Brochure`
- `Store 재고`
- `Store판매내역`
- `Salesforce파일`

### 5.3 Setup

**Full Access** 사용자에게만 사이드바에 노출됩니다.

- `팀 관리`
- `공통코드`
- `역할·권한`
- `직원 등록`

SetUp 랜딩의 일부 카드(예: 사용자 계정 관리)는 UI상 `준비중`일 수 있습니다. 계정 관련 편집은 현재 **People** 직원 모달에서 Full Access + 기능 플래그 조건으로 제공됩니다(4.2 표 참고).

## 6. 주요 기능별 사용 방법

## 6.1 People

직원 정보를 조회하는 화면입니다.

주요 기능:

- 이름 / 이메일 / 부서 기준 검색
- 상태 필터
- 부서 필터
- 정렬

권한이 있는 경우 추가 기능:

- 직원 본문 저장·팀 생성/삭제(동일 Gate 구간): **CM 또는 `is_admin`**(`editEmployeeProfile`)
- 소속 **부서를 다른 부서 코드로 변경**: **Full Access**만(`manageEmployeeDepartment`)
- 직원 등록 모달·모달의 **계정** 필드: **Full Access** + `PEOPLE_MODAL_ACCOUNT_EDIT_ENABLED`(`manageUserAccounts` 등)
- GS Brochure 관리자 플래그 토글 등: Gate에 맞는 권한이 있을 때

주의:

- **CM만**인 계정은 People에서 직원 정보를 다룰 수 있지만, **다른 부서로 옮기기·Setup·Store 관리·계정 플래그 편집**은 할 수 없습니다.
- **`is_admin`**이면 People 모달과 Full Access 기능을 함께 사용할 수 있습니다(직원 테이블에 CM JOB이 없어도 됨).

## 6.2 기관리스트

운영 중인 기관 정보를 조회하고 관리하는 화면입니다.

주요 기능:

- 기관명, 코드 기반 검색
- 기관 구분 필터
- 담당 배정 상태 필터
- 컬럼 정렬
- 기관 상세 모달 확인

상세 화면에서 확인 가능한 항목:

- 기관 기본 정보
- 담당 CO / TR / CS
- 고객 유형
- GS 번호
- 교사 수
- 지원 이력 수
- 최근 지원일
- 최근 10년 지원 이력

수정 가능한 항목:

- 고객 유형
- GS 번호
- CO / TR / CS

## 6.3 기관연락처보기

기관 담당자(Teacher) 연락처를 조회/등록/수정하는 화면입니다.

주요 기능:

- 이름 / 이메일 / 기관명 / 전화번호 기준 검색
- 재직 상태 필터
- 연락처 신규 등록
- 연락처 수정
- 연락처 상세 조회
- 삭제

등록/수정 항목:

- 이름
- 전화번호
- 이메일
- 직급
- 기관(SK 코드)
- 재직 상태
- 수업 참여 여부
- 비고

## 6.4 기관지원보고서

기관 지원 이력을 조회하고 관리하는 화면입니다.

주요 기능:

- 연도 필터
- 담당자(TR) 필터
- 기관 필터
- 키워드 검색
- 지원 보고서 목록 조회
- 계약서 파일 업로드 / 수정 / 다운로드 / 미리보기

계약서 업로드 규칙:

- 최대 20MB
- 허용 형식: `pdf`, `jpg`, `jpeg`, `png`, `gif`, `webp`, `doc`, `docx`, `xls`, `xlsx`

## 6.5 지원보고서 작성

`/supports/create` 화면에서 새 지원 보고서를 등록합니다.

입력 항목:

- 기관 선택 또는 잠재기관 선택
- 지원 날짜 / 시간
- 지원 방식
- 참석자
- 기관 공유 내용
- 본사/타 부서 공유 내용
- 완료 여부
- 첨부 파일

동작 방식:

- 일반 기관은 SK 코드 기준으로 저장됩니다.
- 잠재기관도 지원 보고서 작성이 가능합니다.
- 파일 업로드는 SK 코드가 있는 기관에만 가능합니다.

## 6.6 잠재기관리스트

잠재 고객 기관을 관리하는 화면입니다.

주요 기능:

- 검색
- 연도 / 담당자 / 유형 / 지역 필터
- 소개경로 필터
- 계약가능성 필터
- 신규/종료 요약 필터
- 잠재기관 신규 등록
- 상세 조회
- 계약완료 처리

계약완료 처리 시:

- 잠재기관이 기관리스트에 동기화됩니다.
- 기관 코드가 없으면 `LEAD-{ID}` 형식으로 생성될 수 있습니다.

미계약 전환 시:

- 기관리스트에서는 숨김 처리됩니다.

## 6.7 Store 재고

스토어 재고를 조회하는 화면입니다.

주요 기능:

- 상품코드 / 상품명 검색
- 재고 목록 조회
- 최근 차감 상세 확인
- 실제수량 수정
- 품목 관리 모달 열기

Full Access 사용자 기능:

- `품목 관리`
- `실제수량 수정`

실제수량 수정 시:

- 음수 입력 불가
- 변경 이력이 기록됩니다.

품목 운영 상세는 별도 문서를 참고하세요.

- [Store 재고 품목 관리 가이드](/Users/boseokhur/Desktop/Mocchi 화면 Figma/mocchi-platform/docs/store-inventory-sku-manager-guide.md)

## 6.8 Store판매내역

Store 판매 이력을 조회하는 화면입니다.

사용 목적:

- 판매 데이터 조회
- 기간/목록 기반 확인
- 재고 차감 근거 확인

## 6.9 Salesforce파일

기관별 Salesforce 파일 및 연결되지 않은 파일을 관리하는 화면입니다.

주요 기능:

- 계정 기준 목록 조회
- 미연결 파일 조회
- 파일 상세 검색
- 파일 미리보기
- 파일 정보 수정
- 파일 교체 업로드
- 파일 삭제

수정 가능한 항목:

- SK 코드
- 기관명
- 변경 기관명
- 사업자번호
- 문서 날짜 / 시간
- 컨설턴트
- 파일명

## 6.10 GS Brochure

GS Brochure는 외부 공개 신청과 내부 운영 기능을 함께 제공합니다.

### 외부 신청자

접속 경로:

- `/co/gs-brochure/request`
- 레거시 주소 `/requestbrochure-v2`, `/requestbrochure`

사용 방법:

1. 신청일 입력
2. 기관명 입력
3. 주소 검색
4. 전화번호 입력
5. 인증번호 발송 및 인증
6. 브로셔 선택
7. 수량 입력
8. `신청하기`

주요 규칙:

- 브로셔 수량은 `10권 단위`
- 신청 후 최대 `3일 이내 발송` 안내가 표시됨

### 외부 신청 조회

접속 경로:

- `/requestbrochure-list-v2`

조회 방법:

- 기관명 또는 전화번호 입력 후 조회
- 본인이 신청한 내역만 확인 가능

확인 가능 항목:

- 총 신청 건수
- 운송장 입력 대기
- 운송장 등록 완료

### 내부 직원 신청 내역 조회

접속 경로:

- `/co/gs-brochure/request?view=list`
- `/co/gs-brochure/requests` 접속 시 위 주소로 이동

권한:

- 로그인 사용자면 조회 가능

가능 기능:

- 전체 신청 내역 조회
- 신청 건 검색 및 상태 확인
- 권한 범위 내 수정

### GS Brochure 관리자 대시보드

접속 경로:

- `/co/gs-brochure/admin/dashboard`

권한:

- `manageGsBrochureAdmin`

주요 기능:

- 대시보드 통계 확인
- 물류센터 브로셔 재고관리
- 운송장 입력
- 본사 브로셔 재고관리
- 입출고 내역 조회
- 기관관리
- 설정

## 6.11 Setup

Setup은 운영 기준값을 관리하는 Full Access 전용 메뉴입니다.

### 팀 관리

기능:

- 팀 검색
- 팀 생성
- 팀 수정
- 팀 삭제

삭제 조건:

- 소속 직원이 있는 팀은 삭제할 수 없습니다.

### 공통코드

관리 카테고리:

- `직책`
- `고객유형`
- `상태값`

기능:

- 코드 생성
- 코드 수정
- 활성/비활성 관리
- 정렬순서 관리
- 코드 삭제

### 역할·권한

기능:

- 역할 생성
- 역할 수정
- 역할 삭제
- 메뉴별 권한 설정

현재 권한 설정 대상 메뉴:

- `People`
- `기관 리스트`
- `기관 연락처`
- `기관 지원 내역`
- `잠재기관 관리`
- `SetUp`

권한 종류:

- `view`
- `create`
- `update`
- `delete`

### 직원 등록

Full Access 사용자가 신규 직원을 등록하는 화면입니다.

등록 항목 예시:

- 사번
- 한글명 / 영문명
- 직책
- 이메일
- 전화번호
- 부서
- 상태

운영 참고:

- 로그인 계정이 함께 생성되는 경우 비밀번호 재설정 안내가 발송될 수 있습니다.

## 7. 자주 쓰는 업무 흐름

### 7.1 새 기관이 계약된 경우

1. `잠재기관리스트`에서 대상 확인
2. `계약완료` 처리
3. `기관리스트`에서 자동 반영 여부 확인
4. 필요 시 담당 CO / TR / CS 보정
5. `기관연락처보기`에서 연락처 등록

### 7.2 기관 지원 후 보고서 저장

1. `기관지원보고서` 또는 `지원보고서 작성` 화면 진입
2. 기관 선택
3. 지원 일시 / 지원 방식 / 공유 내용 입력
4. 필요 시 파일 첨부
5. 저장 후 목록에서 이력 확인

### 7.3 Store 재고 운영

1. `Store 재고`에서 품목 검색
2. 최근 차감 내역 확인
3. 필요 시 Full Access 권한으로 실제수량 수정
4. 품목 추가/비활성은 `품목 관리`에서 처리

### 7.4 GS Brochure 운영

1. 외부 사용자가 신청서 제출
2. 내부 직원이 신청 내역 조회
3. GS Brochure 관리 대시보드 권한이 있는 사용자가 재고·운송장·입출고 관리

## 8. 운영 시 주의사항

- 권한이 없으면 일부 버튼이나 메뉴가 보이지 않거나 접근이 차단됩니다.
- `직원 등록`, `팀 관리`, `공통코드`, `역할·권한`, `Store 재고 품목 관리`는 **Full Access**가 필요합니다.
- `GS Brochure` 관리자 대시보드는 **Full Access** 또는 **`is_gs_brochure_admin`** 이면 됩니다.
- `People`에서 **직원 본문·팀 생성/삭제**는 **CM JOB 또는 `is_admin`**(`editEmployeeProfile`)입니다. **부서 이동**은 **`is_admin`**만 가능합니다.
- 일부 메뉴는 아직 `준비중` 상태이므로 실제 동작하지 않을 수 있습니다.

## 9. 관련 문서

- [GS Brochure 권한 정책](/Users/boseokhur/Desktop/Mocchi 화면 Figma/mocchi-platform/docs/gs-brochure-permission-policy.md)
- [Profile Email Policy](/Users/boseokhur/Desktop/Mocchi 화면 Figma/mocchi-platform/docs/profile-email-policy.md)
- [Store 재고 품목 관리 가이드](/Users/boseokhur/Desktop/Mocchi 화면 Figma/mocchi-platform/docs/store-inventory-sku-manager-guide.md)
