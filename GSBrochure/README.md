# GS Brochure Management System

GS 브로셔 관리 시스템 — 브로셔 신청, 재고 관리, 운송장 번호 입력을 통합 관리하는 웹 애플리케이션입니다.

## 현재 프로젝트 구조

- **`laravel/`** — 메인 애플리케이션 (Laravel + Blade 템플릿 + API)
- **`_legacy/`** — 예전 HTML/Node 백엔드·문서 (참고용, 현재 서비스와 무관)

## 시작하기 (Laravel)

### 요구사항

- PHP 8.2+
- Composer
- SQLite(기본) 또는 PostgreSQL

### 설치 및 실행

```bash
cd laravel
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

브라우저에서 **http://localhost:8000** 접속

### 기본 계정

- **아이디**: admin  
- **비밀번호**: admin123  
- 임시 계정: temp / temp123  

자세한 설정·라우트·API는 **`laravel/README_GS_BROCHURE.md`** 를 참고하세요.

## 주요 기능

- 브로셔 신청 및 신청 내역 조회
- 재고·입출고 관리, Excel 다운로드
- 운송장 번호 입력 및 완료 내역 조회
- 담당자·관리자 계정 관리

## 레거시 파일

예전 HTML/Node 백엔드와 배포 문서는 **`_legacy/`** 에 있습니다.  
현재 서비스에는 사용되지 않으며, 참고용으로만 보관됩니다.  
→ `_legacy/README.md` 참고

## 라이선스

ISC
