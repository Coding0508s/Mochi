---
name: SF File Master-Detail Plan
overview: 좌측 기관(SF_Account) 리스트와 우측 파일(SF_Files & 내부 계약서) 리스트를 분할하여 보여주는 Master-Detail 구조의 UI를 구현합니다. 이전의 메모리 문제를 원천적으로 해결하고 효율적인 조회/다운로드 경험을 제공합니다.
todos:
  - id: setup-livewire
    content: 새 Livewire 컴포넌트(SalesforceFileManager) 및 블레이드 뷰 생성
    status: completed
  - id: routes-menu
    content: 라우트(/salesforce-files) 및 사이드바 메뉴 재등록
    status: completed
  - id: build-master-view
    content: "좌측 패널: SF_Account 기관 리스트 페이징, 검색 기능 구현"
    status: completed
  - id: build-detail-view
    content: "우측 패널: 선택된 기관(Account) 기반 종속 파일 목록(SF_Files/Contract) 조회 로직 구현"
    status: completed
  - id: unlinked-tab
    content: 미분류 파일(Unlinked)을 위한 별도 탭 상태 및 전용 조회 쿼리 추가
    status: completed
  - id: file-actions
    content: 물리 파일 스토리지 존재 여부에 따른 다운로드/미리보기 버튼 상태 분기 처리
    status: completed
  - id: ui-styling
    content: 좌우 분할(Split Pane) 형태의 반응형 Tailwind CSS 레이아웃 적용
    status: completed
isProject: false
---

# 아키텍처 및 메모리 문제 해결 (비판적 사고)
이전의 '전체 통합 리스트' 방식은 수만 건의 파일과 기관을 한 번에 JOIN하여 메모리 초과(OOM)를 유발했습니다. 제공해주신 이미지처럼 **좌/우 분할(Master-Detail) 구조**를 채택하면, 한 번에 조회하는 데이터가 '한 페이지 분량의 기관 목록'과 '선택한 특정 기관 1개의 파일 목록'으로 한정되므로 데이터가 100만 건이 되어도 메모리 문제가 발생하지 않습니다.

## 주요 구현 사항

### 1. 좌측 패널 (Master: 기관 목록)
- **데이터소스**: `SF_Account` 테이블만 독립적으로 페이징(약 15건) 처리하여 렌더링합니다.
- **검색 및 필터**: 기관명, `account_ID`, `GSKR_Contract__c` 등의 키워드 검색을 제공합니다.
- **UI/UX**: 데스크톱 뷰 기준 화면을 반으로 나누고, 행을 클릭하면 페이지 새로고침 없이(Livewire) 우측 패널에 파일이 뜹니다.

### 2. 우측 패널 (Detail: 파일 목록)
- **조회 로직**: 좌측에서 선택된 기관의 `account_ID`를 기반으로, 해당 문자열로 시작하는 `SF_Files` 레코드와 연결된 `contract_documents`만 가져옵니다.
- **파일 표시 범위**: SF 원본 기록(메타데이터)과 내부 업로드된 계약서를 함께 정렬하여 보여줍니다.
- **액션 제어**: 물리적 파일(`contract_documents.stored_path`)이 스토리지에 존재하는지 검사하여, [다운로드] 및 [미리보기] 버튼을 활성화하거나 비활성화 상태(Dimmed)로 표시합니다. (사용자 선택 사항 반영)

### 3. 미분류 파일 조회 (Unlinked Files)
- **탭/필터 구성**: 좌측 패널 상단에 [기관 목록]과 [미분류 파일]을 전환할 수 있는 탭을 추가합니다.
- **기능**: 파일명 규칙 어긋남 등의 이유로 `account_ID`가 없거나 매칭 실패한 파일들만 따로 모아서 조회하고 관리할 수 있도록 합니다. (사용자 선택 사항 반영)

### 4. 라우트 및 메뉴 복구
- 이전 단계에서 제거했던 `routes/web.php`의 `/salesforce-files` 라우트와 앱 사이드바의 메뉴를 다시 활성화합니다.
- 새로 만드는 통합 Livewire 컴포넌트의 이름은 직관성을 위해 `SalesforceFileManager`로 명명합니다.