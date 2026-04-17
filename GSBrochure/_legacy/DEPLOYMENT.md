# 배포 가이드

GS Brochure Management System 배포 가이드입니다.

## 배포 구조

이 시스템은 프론트엔드(HTML/JS)와 백엔드(Node.js/Express/SQLite)로 구성되어 있습니다.

### 옵션 1: 전체 스택을 하나의 서버에 배포 (권장)

백엔드 서버가 프론트엔드 파일도 함께 서빙하므로 가장 간단한 방법입니다.

1. **서버 준비**
   - Node.js 설치 (v14 이상)
   - 서버에 프로젝트 클론

2. **백엔드 설정**
   ```bash
   cd backend  # ⚠️ 중요: 반드시 backend 디렉토리로 이동해야 합니다!
   npm install
   npm run init-db
   ```

3. **환경 변수 설정**
   ```bash
   cp .env.example .env
   # .env 파일 편집 (필요시)
   ```

4. **서버 실행**
   ```bash
   # 개발 환경
   npm start
   
   # 프로덕션 환경 (PM2 사용 권장)
   npm install -g pm2
   npm run pm2:start
   pm2 save
   pm2 startup
   ```

5. **접속**
   - 브라우저에서 서버 주소로 접속 (예: `http://your-server.com`)
   - API는 자동으로 `/api` 경로로 제공됨

### 옵션 2: GitHub Pages + 별도 백엔드 서버

1. **프론트엔드 배포 (GitHub Pages)**
   - GitHub 저장소의 Settings > Pages에서 활성화
   - 소스 브랜치 선택 (예: main)
   - 프론트엔드 파일들이 자동으로 배포됨

2. **백엔드 서버 배포**
   - Heroku, Railway, Render, Vercel 등 서비스 사용
   - 또는 자체 서버에 Node.js 설치 후 배포

3. **API URL 설정**
   - `js/api.js` 파일에서 `API_BASE_URL` 수정
   - 백엔드 서버 URL로 변경 (예: `https://your-backend.com/api`)


## 환경 변수

`.env` 파일에서 다음 변수들을 설정할 수 있습니다:

- `PORT`: 서버 포트 (기본값: 3000)
- `NODE_ENV`: 환경 모드 (development/production)
- `CORS_ORIGIN`: CORS 허용 도메인 (프로덕션에서는 특정 도메인으로 설정)

## 데이터베이스 초기화

처음 배포 시 데이터베이스를 초기화해야 합니다:

```bash
cd backend
npm run init-db
```

이 명령은 기본 브로셔와 담당자 데이터를 생성합니다.

## 보안 고려사항

1. **비밀번호 해싱**: 현재 관리자 비밀번호는 평문으로 저장됩니다. 프로덕션에서는 bcrypt를 사용하여 해싱하도록 수정하세요.

2. **CORS 설정**: 프로덕션 환경에서는 `CORS_ORIGIN`을 특정 도메인으로 제한하세요.

3. **환경 변수**: 민감한 정보는 `.env` 파일에 저장하고 Git에 커밋하지 마세요.

4. **HTTPS**: 프로덕션 환경에서는 반드시 HTTPS를 사용하세요.

## 백엔드 서버 배포 예시 (Heroku)

1. Heroku CLI 설치 및 로그인
2. 프로젝트 디렉토리에서:
   ```bash
   heroku create your-app-name
   git push heroku main
   heroku run npm run init-db
   ```

## 백엔드 서버 배포 예시 (Railway)

1. Railway에 GitHub 저장소 연결
2. 루트 디렉토리를 `backend`로 설정
3. 시작 명령: `npm start`
4. 환경 변수 설정
5. 배포 후 데이터베이스 초기화

## 문제 해결

### API 연결 오류
- 브라우저 콘솔에서 CORS 오류 확인
- `js/api.js`의 `API_BASE_URL` 확인
- 백엔드 서버가 실행 중인지 확인

### 데이터베이스 오류
- 데이터베이스 파일(`brochure.db`)이 생성되었는지 확인
- `npm run init-db` 실행 여부 확인

### 포트 충돌
- 환경 변수 `PORT`로 다른 포트 사용
- 또는 서버의 포트 설정 확인

