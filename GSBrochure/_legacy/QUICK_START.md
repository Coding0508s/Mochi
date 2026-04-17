# 빠른 시작 가이드

## ⚠️ 중요: npm install 위치

**`npm install`은 반드시 `backend` 디렉토리 안에서 실행해야 합니다!**

프로젝트 루트 디렉토리(`GS Brochure management`)에는 `package.json`이 없습니다.
`package.json`은 `backend` 디렉토리에 있습니다.

## 올바른 실행 방법

```bash
# 1. 프로젝트 디렉토리로 이동
cd "GS Brochure management"

# 2. backend 디렉토리로 이동 (중요!)
cd backend

# 3. 의존성 설치
npm install

# 4. 데이터베이스 초기화
npm run init-db

# 5. 서버 실행
npm start
```

## 잘못된 실행 방법 (에러 발생)

```bash
# ❌ 이렇게 하면 안 됩니다!
cd "GS Brochure management"
npm install  # 에러: package.json을 찾을 수 없음
```

## 프로젝트 구조

```
GS Brochure management/        ← 루트 디렉토리 (package.json 없음)
├── backend/                   ← 여기에 package.json이 있음!
│   ├── package.json          ← npm install은 여기서 실행
│   ├── server.js
│   └── ...
├── js/
├── *.html
└── ...
```

## 문제 해결

### 에러: "Could not read package.json"
- **원인**: 루트 디렉토리에서 `npm install` 실행
- **해결**: `cd backend` 후 다시 실행

### 에러: "ENOENT: no such file or directory"
- **원인**: 잘못된 디렉토리에서 명령 실행
- **해결**: `backend` 디렉토리로 이동 확인

## 다음 단계

설치가 완료되면:
1. 브라우저에서 `http://localhost:3000` 접속
2. 또는 `requestbrochure.html` 파일 열기

