const { Pool } = require('pg');

// PostgreSQL 연결 풀 생성
// DATABASE_URL 환경 변수를 필수로 사용 (로컬 SQLite 대신)
if (!process.env.DATABASE_URL) {
    console.error('오류: DATABASE_URL 환경 변수가 설정되지 않았습니다.');
    console.error('로컬 개발 환경에서는 PostgreSQL 연결 문자열을 설정하세요.');
    console.error('예: DATABASE_URL=postgresql://user:password@localhost:5432/dbname');
    // Railway 배포 환경에서는 서버가 시작되지 않도록 종료
    // 하지만 로그를 남기기 위해 약간의 지연 후 종료
    setTimeout(() => {
        process.exit(1);
    }, 1000);
}

const pool = new Pool({
    connectionString: process.env.DATABASE_URL,
    ssl: process.env.DATABASE_URL.includes('railway') || process.env.DATABASE_URL.includes('amazonaws') 
        ? { rejectUnauthorized: false } 
        : false
});

// 연결 테스트
pool.on('connect', () => {
    console.log('PostgreSQL 데이터베이스에 연결되었습니다.');
});

pool.on('error', (err) => {
    console.error('PostgreSQL 연결 오류:', err);
});

// Promise 기반 쿼리 실행 (INSERT, UPDATE, DELETE)
function runQuery(query, params = []) {
    return new Promise((resolve, reject) => {
        pool.query(query, params, (err, result) => {
            if (err) {
                reject(err);
            } else {
                resolve({ 
                    lastID: result.rows[0]?.id || null,
                    changes: result.rowCount || 0,
                    rows: result.rows || []
                });
            }
        });
    });
}

// Promise 기반 데이터 조회 (단일 행)
function getQuery(query, params = []) {
    return new Promise((resolve, reject) => {
        pool.query(query, params, (err, result) => {
            if (err) {
                reject(err);
            } else {
                resolve(result.rows[0] || null);
            }
        });
    });
}

// Promise 기반 여러 데이터 조회
function allQuery(query, params = []) {
    return new Promise((resolve, reject) => {
        pool.query(query, params, (err, result) => {
            if (err) {
                reject(err);
            } else {
                resolve(result.rows || []);
            }
        });
    });
}

// 트랜잭션 실행
function transaction(queries) {
    return new Promise(async (resolve, reject) => {
        const client = await pool.connect();
        try {
            await client.query('BEGIN');
            
            const results = [];
            for (const { query, params } of queries) {
                const result = await client.query(query, params || []);
                results.push(result);
            }
            
            await client.query('COMMIT');
            resolve(results);
        } catch (err) {
            await client.query('ROLLBACK');
            reject(err);
        } finally {
            client.release();
        }
    });
}

module.exports = {
    pool,
    runQuery,
    getQuery,
    allQuery,
    transaction
};
