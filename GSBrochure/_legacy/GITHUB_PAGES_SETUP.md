# GitHub Pages 배포 설정 가이드

현재 프론트엔드가 GitHub Pages에 배포되었습니다:
**https://coding0508s.github.io/GSBrochure/**

## ⚠️ 중요: 백엔드 서버 필요

GitHub Pages는 정적 파일(HTML, CSS, JS)만 호스팅하므로, 백엔드 API 서버를 별도로 배포해야 합니다.

## 해결 방법

### 옵션 1: 백엔드 서버 별도 배포 (권장)

#### 1단계: 백엔드 서버 배포

다음 서비스 중 하나를 선택하여 백엔드를 배포하세요:

**A. Railway (추천 - 무료 플랜 제공)**
1. https://railway.app 접속
2. GitHub 저장소 연결
3. 새 프로젝트 생성
4. 루트 디렉토리를 `backend`로 설정
5. 시작 명령: `npm start`
6. 환경 변수 설정:
   - `NODE_ENV=production`
   - `PORT=3000` (Railway가 자동 할당)
7. 배포 후 데이터베이스 초기화:
   - Railway 콘솔에서 `npm run init-db` 실행

**B. Render (무료 플랜 제공)**
1. https://render.com 접속
2. GitHub 저장소 연결
3. 새 Web Service 생성
4. 설정:
   - Root Directory: `backend`
   - Build Command: `npm install`
   - Start Command: `npm start`
5. 환경 변수 설정
6. 배포 후 데이터베이스 초기화

**C. Heroku**
1. Heroku CLI 설치 및 로그인
2. 프로젝트 디렉토리에서:
   ```bash
   cd backend
   heroku create your-app-name
   git push heroku main
   heroku run npm run init-db
   ```

#### 2단계: API URL 수정

백엔드 서버가 배포되면, `js/api.js` 파일을 수정하세요:

```javascript
// js/api.js
const API_BASE_URL = (() => {
    // 프로덕션 환경
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        // 백엔드 서버 URL로 변경
        return 'https://your-backend-server.railway.app/api';
        // 또는
        // return 'https://your-backend-server.render.com/api';
    }
    // 개발 환경
    return 'http://localhost:3000/api';
})();
```

#### 3단계: GitHub에 커밋 및 푸시

```bash
git add js/api.js
git commit -m "Update API URL for production"
git push origin main
```

### 옵션 2: 전체 스택을 하나의 서버에 배포

GitHub Pages 대신, 백엔드 서버가 프론트엔드도 함께 서빙하도록 설정:

1. 백엔드 서버 배포 (Railway, Render 등)
2. 서버가 프론트엔드 파일도 제공하도록 설정됨 (이미 설정 완료)
3. GitHub Pages는 사용하지 않음

## 현재 상태 확인

1. **프론트엔드**: ✅ GitHub Pages에 배포됨
   - URL: https://coding0508s.github.io/GSBrochure/

2. **백엔드**: ❌ 아직 배포 필요
   - 로컬에서만 실행 중 (http://localhost:3000)

## 빠른 테스트

현재 GitHub Pages에서 API를 호출하면 CORS 오류가 발생할 수 있습니다.
백엔드 서버의 CORS 설정을 확인하세요:

```javascript
// backend/server.js
const corsOptions = {
    origin: [
        'https://coding0508s.github.io',
        'http://localhost:3000'
    ],
    credentials: true
};
```

## 배포 체크리스트

- [ ] 백엔드 서버 배포 (Railway/Render/Heroku 등)
- [ ] 백엔드 서버 URL 확인
- [ ] `js/api.js`에서 API URL 수정
- [ ] 백엔드 서버에서 데이터베이스 초기화 (`npm run init-db`)
- [ ] CORS 설정 확인
- [ ] GitHub에 변경사항 푸시
- [ ] 프로덕션 환경에서 테스트

## 문제 해결

### CORS 오류
- 백엔드 서버의 CORS 설정에 GitHub Pages 도메인 추가

### API 연결 실패
- 브라우저 개발자 도구(F12) > Network 탭에서 오류 확인
- 백엔드 서버가 실행 중인지 확인
- API URL이 올바른지 확인

### 데이터베이스 오류
- 백엔드 서버에서 `npm run init-db` 실행 확인

