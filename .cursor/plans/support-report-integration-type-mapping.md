# 지원 보고서 통합 — 타입 매핑 초안

> **상태:** 초안 (Draft) — 구현 전 PRD·합의용  
> **작성 배경:** 기관 지원 보고서와 교사 지원 보고서 **통합 예정**. 연동 삭제(cascade) 등 파생 작업은 통합 설계 확정 전 **보류**.  
> **Goal:** 업무 관점 **목표 타입 체계**와 **현재 MOCHI 구현**을 1:1로 매핑하고, 통합 시 필드·화면·마이그레이션 방향을 잡는다.

---

## 1. 목표 타입 체계 (업무 관점)

### 1.1 기관 지원 — 3가지 대분류

| 코드(안) | 대분류 | 세부 주제(예) |
|----------|--------|----------------|
| `inst_edu_consulting` | **교육 컨설팅** | GS 교수법 관련, 신규 기관 환경 세팅, AUPP, Portal 관리, 교실 운영 컨설팅 |
| `inst_teacher_support` | **교사 지원** | 영어교사, 담임교사 |
| `inst_parent_briefing` | **학부모 설명회** | — |

### 1.2 교사 지원 — 4가지 + 공통(안)

사용자 정의상 **「4가지 타입」**이면서 **교육 컨설팅**이 별도로 함께 적혀 있음 → **§6 오픈 이슈 #1** 참고.

| 코드(안) | 대분류 | 비고 |
|----------|--------|------|
| `teacher_new` | **신규교사지원** | |
| `teacher_onsite_lva` | **On-Site LVA** | |
| `teacher_lva` | **LVA** | FR/FB 등 세부는 report_template 또는 하위 필드 |
| `teacher_open_house` | **Open House 지원** | |
| `teacher_edu_consulting` | **교육 컨설팅** | GS 교수법, 신규 기관 세팅, AUPP, Portal, 교실 운영 컨설팅 |

---

## 2. 현재 MOCHI 구조 (As-Is)

### 2.1 데이터가 나뉘어 있는 3층

| 층 | 저장소 | 역할 |
|----|--------|------|
| A | `S_SupportInfo_Account` (`SupportRecord`) | 기관지원보고서 목록 (`/supports`) |
| B | `teacher_*_support_reports` (10종) + Legacy `S_Support_*` | 교사 지원 보고서 본문·모달 |
| C | `Teachers._1st~4th_Support_Date/Type` | 교사 지원 **현황** 표·KPI (`/coach/teacher-support`) |

교사 지원 **완료** 시 A·B·C가 동시에 갱신될 수 있음. 기관지원만 삭제해도 B·C는 유지되는 **현재 동작** (연동 삭제는 보류).

### 2.2 기관 지원 — 현재 UI/필드

| 항목 | 현재 값 |
|------|---------|
| 화면 | `/supports`, `/supports/create`, 기관 상세 지원 이력 |
| `Support_Type` (대표) | 작성 시 **지원 방법**: `전화`, `대면`, `화상` (`config/support_report_defaults.php`) |
| 동기화 행 | 교사 보고서 완료 시 `LVA + FB`, `On-Site`, `Pro Con` 등 **교사 라벨**이 `Support_Type`에 들어가기도 함 |
| 목표 3대분류 | **전용 enum/컬럼 없음** (`학부모 설명회`, `교육 컨설팅` 대분류 미구현) |

### 2.3 교사 지원 — 현재 보고서 템플릿 10종

`config/coach_teacher_support_create.php` · `config/coach_teacher_legacy_support.php`

| report_template (action) | support_type_label (기관 동기화·현황 타입) | Legacy 테이블 |
|--------------------------|---------------------------------------------|---------------|
| `demo_lesson` | 신규교사 시연수업 | `S_Support_NewTeacher` |
| `lva_fr` | LVA + FR | `S_Support_LVA` |
| `lva_fb` | LVA + FB | `S_Support_LVA` |
| `ls_onsite_lva` | LS On-Site & LVA | `S_SupportLittleSEED_ONLVA` |
| `littleseed_con` | LittleSEED Con | — |
| `onsite` | On-Site | `S_Support_OnSite` |
| `pro_con` | Pro Con | `S_SolutionConsulting` |
| `open_class` | Open-Class | `S_Support_OpenClass` |
| `unit21_plus` | Unit 21+ | `S_Support_U21` |
| `unit31_plus` | Unit 31+ | `S_Support_U31` |

---

## 3. 목표 ↔ 현재 매핑표

### 3.1 기관 지원 3대분류 → 현재

| 목표 대분류 | 현재 MOCHI에서의 후보 | 매핑 신뢰도 | 메모 |
|-------------|----------------------|-------------|------|
| **교육 컨설팅** | 순수 기관 작성 + `Pro Con`/`LittleSEED Con`/`Unit 21+`/`Unit 31+` 동기화 행 | **부분** | 세부 주제(AUPP, Portal 등)는 **별도 필드 없음** — 잠재기관 `컨설팅타입`과도 다른 축 |
| **교사 지원 (영어/담임)** | `Target`에 교사명, `Support_Type`에 교사 보고서 라벨 | **간접** | **대상(영어/담임)** vs **지원 형태(LVA 등)** 구분 없음 |
| **학부모 설명회** | *(없음)* | **없음** | `Open-Class`와 동일 개념인지, 완전 신규인지 **미정** |

### 3.2 교사 지원 4+1대분류 → report_template

| 목표 대분류 | 매핑되는 report_template | 포함 라벨 |
|-------------|---------------------------|-----------|
| **신규교사지원** | `demo_lesson` | 신규교사 시연수업, Legacy `교사 지원(신규교사)` |
| **On-Site LVA** | `onsite`, `ls_onsite_lva` | On-Site, LS On-Site & LVA |
| **LVA** | `lva_fr`, `lva_fb` | LVA + FR, LVA + FB |
| **Open House 지원** | `open_class` | Open-Class |
| **교육 컨설팅** | `pro_con`, `littleseed_con`, `unit21_plus`, `unit31_plus` | Pro Con, LittleSEED Con, Unit 21+, Unit 31+ |

> **압축 비율:** 목표 4~5개 ← 현재 10종 + Legacy 8테이블. 통합 시 **UI 대분류는 줄이고**, `report_template`은 당분간 유지하는 **2단계 모델** 권장.

---

## 4. 통합 후 권장 데이터 모델 (To-Be 초안)

### 4.1 필드 분리 (2~3단계)

```
support_category     — 사용자-facing 대분류 (§1 목표 타입)
support_topics[]     — 세부 주제 (AUPP, Portal, GS 교수법 …) — 다중 선택 또는 태그
target_audience      — inst_teacher_support 일 때: english_teacher | homeroom_teacher
report_template      — 내부: demo_lesson | lva_fb | … (기존 10종, 점진적 통합)
synced_support_round — 1~4 (교사 현황 차수; 저장·삭제·되돌리기용 — 미구현)
support_record_id    — 기관 행 연결 (현재 존재, 통합 후 역할 재정의)
```

### 4.2 단일 SSOT 방향 (통합 PRD에서 확정)

| 옵션 | 설명 | 장단점 |
|------|------|--------|
| **A. Unified `support_reports` 테이블** | category + template + JSON payload | 깔끔하나 마이그레이션 큼 |
| **B. 기존 B 유지 + category 컬럼 추가** | 10테이블에 `support_category`만 추가 | 변경 작음, 테이블 분산 유지 |
| **C. A를 목표로 B → A 단계적 이관** | v1 category, v2 merge | **현실적 추천** |

### 4.3 삭제·완료·현황 (보류 중인 정책)

**합의된 방향 (대화 기록):**  
「기관지원보고서 페이지에서 삭제 = 교사 지원 현황에서도 삭제」  
→ **통합 설계 확정 후** 단일 삭제 서비스에서 처리 (현재 **구현 보류**).

---

## 5. 화면·라우트 영향 (통합 시)

| 현재 | 통합 후 (안) |
|------|----------------|
| `/supports` 기관 목록 | 통합 목록 또는 category 필터 |
| `/supports/create` | category 선택 → template 분기 |
| `/coach/teacher-support` | 현황(1~4차)은 **집계 뷰** 유지 또는 category별 KPI |
| 10개 coach modal | `report_template`별 폼 — 단기 유지 |
| `Teachers._N_Support_*` | SSOT에서 파생 vs 직접 기록 — **ADR 필요** |

---

## 6. 오픈 이슈 (합의 필요)

| # | 질문 | 선택지 |
|---|------|--------|
| 1 | 교사 **「교육 컨설팅」**은 4종에 포함? | A) 5번째 공통 category B) 기관 전용만 C) Pro Con 등 template만 |
| 2 | **학부모 설명회** vs **Open House** | 동일 / 별도 / Open House ⊂ 학부모 설명회 |
| 3 | 기관 **「교사 지원(영어/담임)」** | A) category B) `target_audience` 필드 C) 참석자(`Target`) 텍스트만 |
| 4 | **지원 방법**(전화/대면/화상) vs **업무 타입** | 별도 축(`delivery_method`)으로 유지 vs category에 흡수 |
| 5 | Legacy `S_Support_*` | 읽기 전용 유지 / category backfill / 단계적 폐기 |
| 6 | `Teachers` 1~4차 완료 | denormalized 유지 vs report 집계로 대체 |

---

## 7. Out of scope (본 문서·당분간)

- 연동 삭제 `SupportRecordDeletionService` 구현
- `synced_support_round` migration
- 10종 modal / Store Action 리팩터
- KPI·필터 전면 개편
- 잠재기관 미팅/컨설팅(`CoNewTargetDetail`)과의 통합

---

## 8. 관련 코드·설정 (참고)

| 구분 | 경로 |
|------|------|
| 기관 지원 Livewire | `app/Livewire/SupportList.php`, `SupportCreateForm.php` |
| 교사 현황 | `app/Livewire/CoachTeacherSupportList.php` |
| Store 10종 | `app/Actions/StoreTeacher*SupportReport.php` |
| 현황 차수 sync | `app/Support/TeacherSupportSlotSync.php` |
| 기관↔교사 이력 집계 | `app/Support/TeacherSupportHistoryAggregator.php` |
| 기관 동기화 | `app/Support/TeacherSupportReportSupportRecordSync.php` |
| 교사 작성 타입 pill | `config/coach_teacher_support_create.php` |
| Legacy·MOCHI 테이블 map | `config/coach_teacher_legacy_support.php` |
| 완료 타입 옵션 | `config/coach_teacher_support.php` → `completion_support_types` |
| 기관 작성 기본값 | `config/support_report_defaults.php` |

---

## 9. 다음 단계 (통합 PR 재개 시)

1. **§6 오픈 이슈** 1~3번 사용자 확정
2. **To-Be SSOT** 옵션 C(v1 category 추가) vs A(단일 테이블) ADR
3. **category ↔ report_template** 확정 매핑을 `config/support_report_categories.php`(신규)로 코드화
4. Legacy backfill 규칙 + Feature test (매핑 회귀)
5. 삭제·완료·현황 정책을 통합 PRD에 포함 후 cascade 구현

---

## 10. 복붙용 handoff (구현 챗)

```
@.cursor/plans/support-report-integration-type-mapping.md

다음 목표: §6 오픈 이슈 #1~#3 확정 후 config/support_report_categories.php 초안 + category 컬럼 migration 설계 (코드 최소).

보류: 연동 삭제, Teachers SSOT 변경.
```
