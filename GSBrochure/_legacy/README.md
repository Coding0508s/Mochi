# 레거시 파일 (참고용)

이 폴더는 **현재 프로젝트(Laravel)에 사용되지 않는** 예전 버전 파일을 모아 둔 곳입니다.  
실행·배포는 프로젝트 루트의 **`laravel/`** 를 사용하세요.

## 포함된 내용

| 경로 | 설명 |
|------|------|
| `*.html` | 예전 정적 HTML 페이지 (메인, 로그인, 관리자, 신청/목록/운송장 등) |
| `js/` | 예전 프론트엔드 API 스크립트 (`api.js`) |
| `backend/` | Node.js + Express + PostgreSQL 백엔드 (Railway 등 예전 배포용) |
| `DEPLOYMENT.md` | 예전 배포 가이드 |
| `GITHUB_PAGES_SETUP.md` | GitHub Pages 설정 가이드 |
| `QUICK_START.md` | 예전 빠른 시작 가이드 |
| `RAILWAY_DEPLOY.md` | Railway 배포 가이드 |

## 현재 프로젝트와의 관계

- **웹 앱**: `laravel/` 에서 Laravel + Blade 템플릿으로 동일 기능 제공
- **API**: Laravel `routes/api.php` 및 컨트롤러가 동일 API 제공
- 이 레거시 파일을 수정해도 **laravel/** 또는 현재 서비스에는 반영되지 않습니다.

삭제해도 되고, 참고·복원용으로만 보관해 두셔도 됩니다.
