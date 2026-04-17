# GS Brochure Management Backend API

## 설치 및 실행

### 1. 의존성 설치
```bash
npm install
```

### 2. 데이터베이스 초기화
```bash
npm run init-db
```

### 3. 서버 실행
```bash
# 개발 모드 (nodemon 사용)
npm run dev

# 프로덕션 모드
npm start
```

서버는 기본적으로 `http://localhost:3000`에서 실행됩니다.

## API 엔드포인트

### 브로셔 관리
- `GET /api/brochures` - 모든 브로셔 조회
- `POST /api/brochures` - 브로셔 추가
- `PUT /api/brochures/:id` - 브로셔 수정
- `DELETE /api/brochures/:id` - 브로셔 삭제
- `PUT /api/brochures/:id/stock` - 브로셔 재고 업데이트

### 담당자 관리
- `GET /api/contacts` - 모든 담당자 조회
- `POST /api/contacts` - 담당자 추가
- `PUT /api/contacts/:id` - 담당자 수정
- `DELETE /api/contacts/:id` - 담당자 삭제

### 신청 내역 관리
- `GET /api/requests` - 모든 신청 내역 조회
- `POST /api/requests` - 신청 내역 추가
- `PUT /api/requests/:id` - 신청 내역 수정
- `POST /api/requests/:id/invoices` - 운송장 번호 추가
- `DELETE /api/requests/:id/invoices` - 운송장 번호 삭제

### 입출고 내역
- `GET /api/stock-history` - 입출고 내역 조회
- `POST /api/stock-history` - 입출고 내역 추가

### 관리자 인증
- `POST /api/admin/login` - 관리자 로그인

## 기본 관리자 계정
- Username: `admin`
- Password: `admin123`

## 데이터베이스

이 프로젝트는 **PostgreSQL**을 사용합니다. 로컬 SQLite는 사용하지 않습니다.

### 환경 변수 설정

**필수 환경 변수:**
- `DATABASE_URL`: PostgreSQL 연결 문자열
  - 형식: `postgresql://username:password@host:port/database`
  - 예시: `postgresql://postgres:password@localhost:5432/brochure_db`

### Railway 배포 시

Railway에서 PostgreSQL 서비스를 추가하면 `DATABASE_URL` 환경 변수가 자동으로 설정됩니다.

### 로컬 개발 환경

로컬에서 개발하려면 PostgreSQL을 설치하고 `DATABASE_URL` 환경 변수를 설정하세요:

```bash
# .env 파일 생성 (선택사항)
echo "DATABASE_URL=postgresql://postgres:password@localhost:5432/brochure_db" > .env

# 또는 환경 변수로 직접 설정
export DATABASE_URL=postgresql://postgres:password@localhost:5432/brochure_db
```

### 데이터베이스 초기화

데이터베이스 초기화는 서버 시작 시 자동으로 실행됩니다. 수동으로 실행하려면:

```bash
npm run init-db
```

