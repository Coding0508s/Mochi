# GS Brochure Management — 워크플로우

이 문서는 GS 브로셔 관리 시스템의 **개발·운영 워크플로우**를 정리한 것입니다. 로컬 개발, 수정, 배포, 트러블슈팅 시 참고하세요.

---

## 1. 프로젝트 구조

| 경로 | 설명 |
|------|------|
| **`laravel/`** | 메인 앱 (Laravel 11 + Blade + REST API). 여기만 수정·배포 대상. |
| **`_legacy/`** | 예전 HTML/Node 백엔드·문서. 참고용, 현재 서비스와 무관. |

### Laravel 내부 핵심 경로

- **API 컨트롤러**: `laravel/app/Http/Controllers/Api/`
- **모델**: `laravel/app/Models/`
- **API 라우트**: `laravel/routes/api.php`
- **웹 라우트**: `laravel/routes/web.php`
- **뷰(Blade)**: `laravel/resources/views/` (admin, request, layouts)
- **공개 JS**: `laravel/public/js/api.js` (프론트에서 호출하는 API 래퍼)
- **마이그레이션**: `laravel/database/migrations/`
- **시더**: `laravel/database/seeders/`

---

## 2. 로컬 개발 환경 설정

### 요구사항

- PHP 8.2+
- Composer
- SQLite(기본) 또는 PostgreSQL

### 한 번만 실행

```bash
cd laravel
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

### DB가 PostgreSQL인 경우

`.env`에서 다음만 설정:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=postgres
DB_PASSWORD=비밀번호
```

### 서버 실행

```bash
cd laravel
php artisan serve
```

- 접속: **http://localhost:8000**
- 관리자: `/admin/login` → admin / admin123 (또는 temp / temp123)

---

## 3. 일상 개발 워크플로우

### 3.1 백엔드(API·DB) 수정 시

1. **API 동작 변경**: `laravel/app/Http/Controllers/Api/` 해당 컨트롤러 수정.
2. **라우트 추가/변경**: `laravel/routes/api.php` 수정.
3. **DB 스키마 변경**: 새 마이그레이션 생성 후 실행.
   ```bash
   cd laravel
   php artisan make:migration describe_your_change
   # migration 파일 편집 후
   php artisan migrate
   ```
4. **모델/관계 변경**: `laravel/app/Models/` 수정. `fillable`, `casts` 등 확인.

### 3.2 프론트(뷰·JS) 수정 시

1. **페이지 UI/문구**: `laravel/resources/views/` 내 해당 Blade 파일 수정.
   - 메인: `main.blade.php`
   - 관리자: `admin/dashboard.blade.php`, `admin/login.blade.php`
   - 신청 폼: `request/form.blade.php`
   - 신청 목록: `request/list.blade.php`
   - 운송장: `request/logistics.blade.php`, `request/completed.blade.php`
2. **API 호출 방식**: `laravel/public/js/api.js` 수정. (캐시 방지, 에러 메시지 등)
3. **레이아웃/공통 UI**: `laravel/resources/views/layouts/` (shell.blade.php 등).  
   `window.API_BASE_URL`은 레이아웃에서 `url('/api')`로 설정됨.

### 3.3 브로셔·재고·입출고 관련 로직

- **브로셔 CRUD·입출고**: `BrochureController.php`
  - 신규 브로셔 생성 시 `StockHistory`에 '등록' 타입으로 물류/본사 각 1건 생성.
  - 브로셔 삭제 시 해당 브로셔의 `StockHistory` 선 삭제 후 브로셔 삭제. 발송 내역(request_items) 사용 중이면 422 + 안내 메시지.
- **입출고 내역 API**: `StockHistoryController.php`, 라우트 `GET/POST /api/stock-history`.
- **신청 폼 브로셔 드롭다운**: `request/form.blade.php`에서 `loadBrochureOptions()` → `BrochureAPI.getAll()` 호출, 드롭다운 열 때마다 최신 목록 반영. 640px 이상에서도 보이도록 드롭다운은 `position: fixed`로 위치 계산.

### 3.4 테스트

```bash
cd laravel
php artisan test
```

---

## 4. 주요 라우트·API 요약

| 구분 | 경로 | 비고 |
|------|------|------|
| 메인 | `/` | 신청/송장/관리자 링크 |
| 관리자 로그인 | `/admin/login` | |
| 관리자 대시보드 | `/admin` | 로그인 후 |
| 브로셔 신청 폼 | `/requestbrochure` | |
| 신청 목록 | `/requestbrochure-list` | |
| 운송장 입력 | `/requestbrochure-logistics` | |
| 운송장 완료 내역 | `/requestbrochure-completed` | |
| API | `/api/*` | brochures, contacts, requests, stock-history, admin 등 |

API 베이스 URL은 프론트에서 `window.API_BASE_URL`(레이아웃에서 `/api`로 설정) 사용.

---

## 5. Git·배포 워크플로우

### 커밋 전 체크

- `laravel/` 기준으로 동작 확인 (php artisan serve 후 브라우저/API 호출).
- 민감 정보는 `.env`에만 두고 커밋하지 않기.

### 푸시

- 기본 브랜치 `main` 사용 시: `git push origin main`.
- 원격 저장소: 예) `github.com:Coding0508s/GSBrochure.git`.

### 배포

- 실제 서버에서는 Laravel을 document root로 서빙 (또는 `public`을 document root).
- 상세: `laravel/DEPLOYMENT.md` 참고.
- 스케줄(운송장 완료 건 정리): cron에서 `php artisan schedule:run` 실행.  
  명령: `php artisan completed-requests:clean`.

---

## 6. 문제 해결

| 현상 | 확인·조치 |
|------|-----------|
| 브로셔 목록이 폼에 안 보임 | `BrochureAPI.getAll()` 캐시 방지 확인(`api.js`). 드롭다운 열 때 `loadBrochureOptions()` 재호출 여부 확인. |
| 드롭다운이 640px 이상에서 안 보임 | `request/form.blade.php`에서 드롭다운 `position: fixed` 및 트리거 기준 위치 계산 적용 여부 확인. |
| 브로셔 삭제 시 오류 | 입출고 내역은 컨트롤러에서 선 삭제됨. “발송 내역이 있어 삭제할 수 없습니다”면 request_items에 사용 중인 브로셔. |
| API 401 | 관리자 전용 라우트는 세션/쿠키 확인. 일반 API는 인증 없음. |
| 마이그레이션 실패 | DB 연결·`.env` 확인. 외래키 순서 맞는지 마이그레이션 순서 확인. |

---

## 7. 참고 문서

- **프로젝트 루트**: `README.md` — 전체 개요·빠른 시작.
- **Laravel 앱**: `laravel/README_GS_BROCHURE.md` — 라우트, Blade 매핑, API 호스트, 스케줄.
- **배포**: `laravel/DEPLOYMENT.md`.
- **레거시**: `_legacy/README.md`, `_legacy/DEPLOYMENT.md` 등 (참고용).

이 워크플로우는 프로젝트 변경에 따라 갱신해 사용하세요.
