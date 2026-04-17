# GS Brochure Management (Laravel)

Laravel Blade 템플릿 기반 GS Brochure 관리 앱입니다. 기존 Node/Express API를 Laravel API로 대체했습니다.

## 요구사항

- PHP 8.2+
- Composer
- PostgreSQL (권장) 또는 SQLite

## 설치 및 실행

```bash
cd laravel
cp .env.example .env
php artisan key:generate
```

### PostgreSQL 사용 시

`.env`에서 다음을 설정하세요.

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=postgres
DB_PASSWORD=비밀번호
```

### 마이그레이션 및 시더

```bash
php artisan migrate
php artisan db:seed
```

시더는 기본 브로셔·담당자·관리자 계정(admin/admin123, temp/temp123)을 생성합니다.

### 서버 실행

```bash
php artisan serve
```

브라우저에서 http://localhost:8000 접속

### 브로셔 이미지 업로드 (관리자)

관리자 대시보드에서 브로셔 이미지를 파일로 업로드하려면 **storage 링크**가 필요합니다. 로컬/배포 환경에서 한 번 실행하세요.

```bash
php artisan storage:link
```

`public/storage` → `storage/app/public` 심볼릭 링크가 생성되며, 업로드된 이미지는 `storage/app/public/brochures/`에 저장됩니다.

## 라우트

| 경로 | 설명 |
|------|------|
| `/` | 메인 (브로셔 신청/송장조회/관리자 로그인 링크) |
| `/admin/login` | 관리자 로그인 |
| `/admin` | 관리자 대시보드 (로그인 후) |
| `/requestbrochure` | 브로셔 신청 폼 |
| `/requestbrochure-list` | 신청 목록 |
| `/requestbrochure-logistics` | 운송장 입력 |
| `/requestbrochure-completed` | 운송장 완료 내역 |
| `/api/*` | REST API (브로셔, 담당자, 신청, 입출고, 관리자) |

## API 호스트

Blade 레이아웃에서 `window.API_BASE_URL`을 `url('/api')`로 설정하므로, 같은 도메인에서 Laravel이 서빙할 때는 `/api`로 요청됩니다.

## Blade 템플릿 구성

다음 HTML 파일을 Blade 템플릿으로 변환해 두었습니다.

| 원본 파일 | Blade 뷰 |
|-----------|-----------|
| brochuremain.html | main.blade.php |
| admin-login.html | admin/login.blade.php |
| admin.html | admin/dashboard.blade.php |
| requestbrochure.html | request/form.blade.php |
| requestbrochure-list.html | request/list.blade.php |
| requestbrochure logistics.html | request/logistics.blade.php |
| requestbrochure-completed.html | request/completed.blade.php |

페이지 내 링크는 `url('requestbrochure')`, `url('admin/login')` 등 Laravel `url()` 헬퍼로 연결되어 있으며, `js/api.js`는 `asset('js/api.js')`로 로드되고 `window.API_BASE_URL`은 Laravel `/api`로 설정됩니다. 배포·설정 참고는 프로젝트 루트의 DEPLOYMENT.md, RAILWAY_DEPLOY.md, GITHUB_PAGES_SETUP.md 등을 참고하세요.

## 스케줄 작업 (운송장 완료 건 자동 삭제)

운송장이 입력된 신청 건은 **가장 최근 운송장 등록일 기준 3일이 지나면** 자동으로 삭제됩니다.

- **명령**: `php artisan completed-requests:clean`
- **스케줄**: 매일 1회 실행 (`routes/console.php`에 등록됨)
- **프로덕션**: cron에 `* * * * * cd /path/to/laravel && php artisan schedule:run >> /dev/null 2>&1` 를 등록하면 매일 해당 명령이 실행됩니다.
