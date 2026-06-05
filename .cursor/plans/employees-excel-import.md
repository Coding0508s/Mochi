# Employees 엑셀 Import — 구현 계획

## 개발 목표

- `/people` Employees 화면에서 HR 마스터 엑셀 업로드로 직원·부서·로그인 계정을 동기화한다.
- 엑셀에 없는 **재직(STATUS=1) + 이메일 보유** 직원은 비활성(STATUS=0) 처리한다.
- 적용 전 **dry-run 미리보기**로 blast radius를 확인한다.

## 확정 사양 (Locked)

| 항목 | 값 |
|------|-----|
| Match key | `LOWER(TRIM(email))` |
| Upsert 필드 | `KOREANAME`, `WORKDEPT`(DEPTNAME lookup), `PHONENO`(모바일), `EMAIL`, `STATUS→1` |
| EMPNO (신규) | `EMP-{Ymd}-{4자리}` |
| 신규 모바일 | 필수 |
| 신규 User | 생성 + 비밀번호 재설정 메일 |
| 숨김 | `STATUS=1` + email non-empty + excel에 없음 → `STATUS=0`, `User.is_active=false` |
| dry-run | 필수 (미리보기 → 적용) |
| JOB (신규) | `Staff` (`config('employee_import.default_job')`) |
| ENGLISHNAME (신규) | `KOREANAME`과 동일 |
| 전화/내선 | 1차 무시 |

## 엑셀 컬럼

| 엑셀 | DB |
|------|-----|
| 성명 | `KOREANAME` |
| 부서 | `WORKDEPT` via `department.DEPTNAME` (없으면 자동 생성) |
| 모바일 | `PHONENO` |
| Email | match + `EMAIL` |

## 구현 파일

| 파일 | 역할 |
|------|------|
| `config/employee_import.php` | default_job 등 |
| `app/Support/DepartmentCodeGenerator.php` | `nextDeptNo()` 공유 |
| `app/Support/EmployeeEmpNoGenerator.php` | EMPNO 발급 |
| `app/Support/EmployeeExcelImporter.php` | 파싱·upsert·hide |
| `app/Livewire/PeopleEmployeesList.php` | preview/apply Livewire |
| `resources/views/livewire/people-employees-list.blade.php` | 업로드 UI |
| `tests/Unit/EmployeeExcelImporterTest.php` | 단위 테스트 |
| `tests/Feature/PeopleEmployeeExcelImportTest.php` | 권한·Livewire |

## 검증

```bash
vendor/bin/pint --dirty
php artisan test --compact tests/Unit/EmployeeExcelImporterTest.php tests/Feature/PeopleEmployeeExcelImportTest.php
composer run verify
```

## NOT in scope

- employee/department 물리 삭제
- 전화·내선 저장
- JOB/ENGLISHNAME 엑셀 일괄 변경
- CSV

## GSTACK REVIEW REPORT

| Review | Trigger | Why | Runs | Status | Findings |
|--------|---------|-----|------|--------|----------|
| Eng Review | `/plan-eng-review` | Architecture & tests | 1 | CLEAR (PLAN) | 플랫폼 적합, dry-run·hide scope 확정 |

**VERDICT:** ENG CLEARED — 구현 진행
