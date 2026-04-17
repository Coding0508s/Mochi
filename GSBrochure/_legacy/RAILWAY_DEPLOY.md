# Railway 배포 가이드

## Railway CLI를 사용한 배포

### 1단계: 프로젝트 디렉토리로 이동

```bash
cd "/Users/boseokhur/Desktop/GS Brochure management/backend"
```

### 2단계: Railway 프로젝트 초기화

```bash
# Railway 프로젝트 초기화 (처음 한 번만)
railway init

# 또는 기존 프로젝트에 연결
railway link
```

### 3단계: 환경 변수 설정

```bash
# Railway 대시보드에서 설정하거나 CLI로 설정
railway variables set NODE_ENV=production
railway variables set CORS_ORIGIN=https://coding0508s.github.io
```

### 4단계: 배포

```bash
# 배포 실행
railway up
```

### 5단계: 데이터베이스 초기화

배포가 완료된 후:

```bash
# Railway CLI를 통해 데이터베이스 초기화
railway run npm run init-db
```

## Railway 웹 대시보드 사용 (권장)

### 1단계: Railway 웹사이트 접속
- https://railway.app 접속
- GitHub 계정으로 로그인

### 2단계: 새 프로젝트 생성
1. "New Project" 클릭
2. "Deploy from GitHub repo" 선택
3. `GSBrochure` 저장소 선택

### 3단계: 서비스 설정
1. "Add Service" > "Empty Service" 선택
2. Settings 탭에서:
   - **Root Directory**: `backend` 설정
   - **Start Command**: `npm start`

### 4단계: 환경 변수 설정
Variables 탭에서 추가:
- `NODE_ENV` = `production`
- `CORS_ORIGIN` = `https://coding0508s.github.io`

### 5단계: 배포 확인
- Deployments 탭에서 배포 상태 확인
- 배포 완료 후 생성된 URL 확인 (예: `https://your-app.railway.app`)

### 6단계: 데이터베이스 초기화
Railway 콘솔에서:
```bash
npm run init-db
```

또는 Railway 대시보드의 "Shell" 탭에서 실행

### 7단계: API URL 업데이트
배포된 URL을 받으면 `js/api.js` 파일 수정:

```javascript
const API_BASE_URL = (() => {
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        return 'https://your-app.railway.app/api';  // Railway URL로 변경
    }
    return 'http://localhost:3000/api';
})();
```

## 문제 해결

### 에러: "Could not read package.json"
- **원인**: 잘못된 디렉토리에서 명령 실행
- **해결**: `backend` 디렉토리로 이동 후 실행

### 에러: "Railway command not found"
- **원인**: Railway CLI가 설치되지 않음
- **해결**: 
  ```bash
  npm install -g @railway/cli
  ```

### 배포 후 데이터베이스 오류
- Railway 콘솔에서 `npm run init-db` 실행 확인
- 환경 변수 설정 확인

