// 간단한 API 테스트 스크립트
const http = require('http');

const API_BASE = 'http://localhost:3000/api';

// 테스트 함수
function testAPI(method, endpoint, data = null) {
    return new Promise((resolve, reject) => {
        const url = new URL(endpoint, API_BASE);
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            }
        };

        const req = http.request(url, options, (res) => {
            let body = '';
            res.on('data', (chunk) => {
                body += chunk;
            });
            res.on('end', () => {
                try {
                    const result = body ? JSON.parse(body) : {};
                    resolve({ status: res.statusCode, data: result });
                } catch (e) {
                    resolve({ status: res.statusCode, data: body });
                }
            });
        });

        req.on('error', (error) => {
            reject(error);
        });

        if (data) {
            req.write(JSON.stringify(data));
        }

        req.end();
    });
}

// 테스트 실행
async function runTests() {
    console.log('=== API 테스트 시작 ===\n');

    try {
        // 1. 브로셔 조회
        console.log('1. 브로셔 조회 테스트...');
        const brochures = await testAPI('GET', '/brochures');
        console.log('결과:', brochures.status === 200 ? '성공' : '실패', brochures.data.length, '개');
        console.log('');

        // 2. 담당자 조회
        console.log('2. 담당자 조회 테스트...');
        const contacts = await testAPI('GET', '/contacts');
        console.log('결과:', contacts.status === 200 ? '성공' : '실패', contacts.data.length, '개');
        console.log('');

        // 3. 관리자 로그인 테스트
        console.log('3. 관리자 로그인 테스트...');
        const login = await testAPI('POST', '/admin/login', {
            username: 'admin',
            password: 'admin123'
        });
        console.log('결과:', login.status === 200 ? '성공' : '실패');
        console.log('');

        // 4. 신청 내역 조회
        console.log('4. 신청 내역 조회 테스트...');
        const requests = await testAPI('GET', '/requests');
        console.log('결과:', requests.status === 200 ? '성공' : '실패', requests.data.length, '개');
        console.log('');

        // 5. 입출고 내역 조회
        console.log('5. 입출고 내역 조회 테스트...');
        const history = await testAPI('GET', '/stock-history');
        console.log('결과:', history.status === 200 ? '성공' : '실패', history.data.length, '개');
        console.log('');

        console.log('=== 모든 테스트 완료 ===');
    } catch (error) {
        console.error('테스트 오류:', error.message);
        console.log('\n서버가 실행 중인지 확인하세요: npm start');
    }
}

// 실행
runTests();

