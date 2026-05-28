# Mochi3 → Mochi4 수동 동기화

## Artisan (권장)

```bash
# 미리보기
php artisan db:sync-mochi3-to-mochi4 --dry-run

# Teachers 컬럼 추가 후 반영
php artisan db:sync-mochi3-to-mochi4 --migrate --yes
```

- **소스:** `Mochi3` (읽기만)
- **대상:** `.env`의 `DB_DATABASE` (기본 `Mochi4`)
- **제외:** `users`, `migrations`, `g5_*` 및 Mochi3에 없는 Mochi4 전용 테이블

## 대상 테이블 (19개)

### 기관 그룹
- `S_AccountName` (`PortalCampusID`는 Mochi3에 값이 있을 때만 덮음)
- `S_Account_Information` (INSERT 포함)
- `S_GSNumber`

### 교사 그룹
- `Teachers` (INSERT 포함, 마이그레이션으로 3·4차 컬럼 필요)
- `S_RetirementList` (INSERT 포함)
- `S_TeacherMasterDB` (INSERT 포함)

### 기관지원 보고서 그룹
- `S_SupportInfo_Account` (INSERT 포함)
- `S_Support_NewTeacher` (INSERT 포함)
- `S_Support_LVA` (INSERT 포함)
- `S_Support_OnSite` (INSERT 포함)
- `S_Support_OpenClass` (INSERT 포함)
- `S_SupportLittleSEED_ONLVA` (INSERT 포함)
- `S_Support_U21` (INSERT 포함)
- `S_Support_U31` (INSERT 포함)
- `S_SolutionConsulting` (INSERT 포함)

### 잠재 기관 그룹
- `S_CO_NewTarget` (INSERT 포함)
- `S_CO_NewTarget_Detail` (INSERT 포함)

### 인사 그룹
- `employee` (PK: `EMPNO`, INSERT 포함)
- `department` (PK: `DEPTNO`, INSERT 포함)

## 조건

- **UPDATE:** `FGC_LastModifyDate`가 Mochi3가 더 큰 행 (PK로 JOIN)
- **INSERT:** `insert_missing: true`인 테이블 — Mochi3에만 있는 PK 행 추가
- **SET 제외:** PK 컬럼(`join_on`)과 `FGC_Rowversion`은 UPDATE SET에서 제외
