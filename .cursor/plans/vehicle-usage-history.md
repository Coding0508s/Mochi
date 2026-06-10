# 차량별 사용 내역 조회 기능 구현 계획

## 1. 개요
관리자가 특정 차량의 과거부터 현재까지의 사용 내역(운행 기록, 사용자, 목적지, 거리 등)을 빠르고 정확하게 조회하고 관리할 수 있는 기능을 구현합니다.

## 2. 제외된 범위 (Out of Scope)
- **출발지 데이터:** 현재 DB 스키마(`vehicle_usage_logs`)에 `arrival_location`(도착지)만 존재하므로 출발지는 UI에서 제외합니다.
- **예약 취소 상태:** `SharedSupply`가 Hard Delete로 처리되므로 취소된 예약 내역은 조회 대상에서 제외합니다.

## 3. UI/UX 디자인 결정 사항 (Design Review 반영)
- **검색/필터 적용 방식:** 명시적인 [조회] 버튼 없이, 차량이나 기간을 변경할 때 즉시(Auto-submit) 테이블이 업데이트되도록 구현합니다 (`wire:model.live` 활용).
- **요약 지표 레이아웃:** 3개의 거대한 카드 위젯 대신, 데이터 밀도를 높이기 위해 테이블 상단에 인라인 텍스트(`총 15건 ∙ 340km ∙ 12시간`)로 간결하게 표시합니다.
- **엑셀 다운로드 에러 상태:** 다운로드 실패 시 화면 전체 에러가 아닌 토스트(Toast) 알림으로 에러 메시지를 표시하여 기존 작업 화면을 유지합니다.
- **모바일 반응형:** 모바일 화면에서는 테이블을 카드로 변환하되, 스크롤 피로도를 줄이기 위해 핵심 정보(사용 일시, 목적, 운행 거리)만 표시합니다.

## 4. 기술 스택 및 아키텍처
- **Backend:** Laravel, Livewire 3/4
- **Frontend:** Blade, Tailwind CSS, Alpine.js (필요시)
- **Data:** `VehicleUsageLog` 모델, `SharedSupply` 모델 조인 (총 운행 시간 계산용)
- **Export:** `PhpOffice\PhpSpreadsheet`를 활용한 엑셀 다운로드

## 5. 구현 상세
1. **Livewire 컴포넌트 생성:** `App\Livewire\VehicleUsageHistoryList`
2. **Blade 뷰 생성:** `resources/views/livewire/vehicle-usage-history-list.blade.php`
3. **라우트 추가:** `routes/web.php` (관리자 미들웨어 적용)
4. **기능 구현:**
   - HTML `<datalist>`를 활용한 차량 검색 드롭다운
   - DB 집계 함수(`SUM`, `COUNT`)를 활용한 요약 지표 계산
   - 서버사이드 페이지네이션 (`WithPagination`)
   - 엑셀 다운로드 기능 구현

## 6. 테스트 계획
- `tests/Feature/Livewire/VehicleUsageHistoryListTest.php`
- 차량 및 기간 필터링 동작 확인
- 요약 지표 집계 로직 정확성 확인
- 엑셀 다운로드 파일 생성 확인
- 데이터가 없을 때 빈 상태(Empty State) 렌더링 확인
