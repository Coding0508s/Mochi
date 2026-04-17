require('dotenv').config({ path: require('path').join(__dirname, '..', '.env') });
const { Pool } = require('pg');
const fs = require('fs');
const path = require('path');
const bcrypt = require('bcrypt');

// PostgreSQL 연결 풀 생성
// DATABASE_URL 환경 변수를 필수로 사용 (로컬 SQLite 대신)
if (!process.env.DATABASE_URL) {
    console.error('오류: DATABASE_URL 환경 변수가 설정되지 않았습니다.');
    console.error('로컬 개발 환경에서는 PostgreSQL 연결 문자열을 설정하세요.');
    console.error('예: DATABASE_URL=postgresql://user:password@localhost:5432/dbname');
    process.exit(1);
}

const pool = new Pool({
    connectionString: process.env.DATABASE_URL,
    ssl: process.env.DATABASE_URL.includes('railway') || process.env.DATABASE_URL.includes('amazonaws')
        ? { rejectUnauthorized: false }
        : false
});

const schemaPath = path.join(__dirname, 'schema-postgres.sql');

// 데이터베이스 초기화
async function initDatabase() {
    const client = await pool.connect();
    try {
        console.log('PostgreSQL 데이터베이스에 연결되었습니다.');
        
        // 스키마 파일 읽기 및 실행
        const schema = fs.readFileSync(schemaPath, 'utf8');
        
        // 스키마 실행 (각 문장을 개별적으로 실행)
        const statements = schema.split(';').filter(stmt => stmt.trim().length > 0);
        
        for (const statement of statements) {
            const trimmed = statement.trim();
            if (trimmed) {
                try {
                    await client.query(trimmed);
                } catch (err) {
                    // 테이블이 이미 존재하는 경우 무시
                    if (!err.message.includes('already exists')) {
                        console.error('스키마 실행 오류:', err.message);
                        throw err;
                    }
                }
            }
        }
        
        console.log('데이터베이스 스키마가 생성되었습니다.');
        
        // 기본 데이터 삽입
        await insertDefaultData(client);
        console.log('기본 데이터가 삽입되었습니다.');
        
    } catch (err) {
        console.error('데이터베이스 초기화 오류:', err);
        throw err;
    } finally {
        client.release();
    }
}

// 기본 데이터 삽입
async function insertDefaultData(client) {
    // 기본 브로셔 데이터
    const defaultBrochures = [
        { name: 'LittleSEED Play in English', stock: 0 },
        { name: 'Think in English, Speak in English', stock: 0 },
        { name: '어린이 영어교육, 왜 확실한 구어습득이 필요한가?', stock: 0 },
        { name: 'GrapeSEED Elementary', stock: 0 },
        { name: 'Information for Parents', stock: 0 },
        { name: 'LittleSEED at Home Guide', stock: 0 },
        { name: '성공적인 GrapeSEED를 위한 가이드', stock: 0 },
        { name: 'GS Baby', stock: 0 },
        { name: 'GS Online 리플렛', stock: 0 }
    ];

    // 기본 담당자 데이터
    const defaultContacts = [
        { name: 'Addy Kim' },
        { name: 'Peter Kim' },
        { name: 'Ryan Koh' },
        { name: 'Daniel Kim' },
        { name: 'Ron Shin' }
    ];

    // 브로셔 데이터 삽입 (ON CONFLICT 사용)
    for (const brochure of defaultBrochures) {
        await client.query(
            'INSERT INTO brochures (name, stock) VALUES ($1, $2) ON CONFLICT (name) DO NOTHING',
            [brochure.name, brochure.stock]
        );
    }

    // 담당자 데이터 삽입
    for (const contact of defaultContacts) {
        await client.query(
            'INSERT INTO contacts (name) VALUES ($1) ON CONFLICT (name) DO NOTHING',
            [contact.name]
        );
    }

    // 기본 관리자 계정 생성 (admin/admin123)
    const adminHash = await bcrypt.hash('admin123', 10);
    await client.query(
        'INSERT INTO admin_users (username, password_hash) VALUES ($1, $2) ON CONFLICT (username) DO NOTHING',
        ['admin', adminHash]
    );

    // 임시 관리자 계정 생성 (temp/temp123) — 테스트/임시용, 사용 후 삭제 권장
    const tempHash = await bcrypt.hash('temp123', 10);
    await client.query(
        'INSERT INTO admin_users (username, password_hash) VALUES ($1, $2) ON CONFLICT (username) DO NOTHING',
        ['temp', tempHash]
    );
    
    console.log('기본 관리자 계정이 생성되었습니다. (username: admin, password: admin123)');
    console.log('임시 계정이 생성되었습니다. (username: temp, password: temp123)');
}

// 실행
if (require.main === module) {
    initDatabase()
        .then(() => {
            console.log('데이터베이스 초기화가 완료되었습니다.');
            pool.end();
            process.exit(0);
        })
        .catch((err) => {
            console.error('데이터베이스 초기화 오류:', err);
            pool.end();
            process.exit(1);
        });
}

module.exports = { initDatabase };
