const { Pool } = require('pg');
const bcrypt = require('bcrypt');
const readline = require('readline');

// PostgreSQL 연결 풀 생성
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

// 사용자 입력을 받기 위한 인터페이스
const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
});

// 비밀번호 입력 (에코 없이)
function question(query) {
    return new Promise(resolve => rl.question(query, resolve));
}

function questionHidden(query) {
    return new Promise((resolve, reject) => {
        const stdin = process.stdin;
        const stdout = process.stdout;
        
        stdout.write(query);
        stdin.setRawMode(true);
        stdin.resume();
        stdin.setEncoding('utf8');
        
        let password = '';
        stdin.on('data', function(char) {
            char = char + '';
            switch (char) {
                case '\n':
                case '\r':
                case '\u0004':
                    stdin.setRawMode(false);
                    stdin.pause();
                    stdout.write('\n');
                    resolve(password);
                    break;
                case '\u0003':
                    stdin.setRawMode(false);
                    stdin.pause();
                    stdout.write('\n');
                    process.exit();
                    break;
                case '\u007f': // 백스페이스
                    if (password.length > 0) {
                        password = password.slice(0, -1);
                        stdout.write('\b \b');
                    }
                    break;
                default:
                    password += char;
                    stdout.write('*');
                    break;
            }
        });
    });
}

async function resetAdminPassword() {
    const client = await pool.connect();
    try {
        console.log('=== Admin 비밀번호 재설정 ===\n');
        
        // 현재 admin 사용자 목록 조회
        const users = await client.query('SELECT id, username FROM admin_users ORDER BY id');
        
        if (users.rows.length === 0) {
            console.log('관리자 계정이 없습니다. init-db.js를 실행하여 기본 계정을 생성하세요.');
            return;
        }
        
        console.log('현재 관리자 계정 목록:');
        users.rows.forEach((user, index) => {
            console.log(`  ${index + 1}. ID: ${user.id}, Username: ${user.username}`);
        });
        
        // 사용자 선택
        let selectedUser;
        if (users.rows.length === 1) {
            selectedUser = users.rows[0];
            console.log(`\n선택된 계정: ${selectedUser.username} (ID: ${selectedUser.id})`);
        } else {
            const userIndex = await question('\n비밀번호를 재설정할 계정 번호를 입력하세요: ');
            const index = parseInt(userIndex) - 1;
            if (index < 0 || index >= users.rows.length) {
                console.log('잘못된 번호입니다.');
                return;
            }
            selectedUser = users.rows[index];
        }
        
        // 새 비밀번호 입력
        console.log(`\n${selectedUser.username} 계정의 새 비밀번호를 입력하세요:`);
        const newPassword = await questionHidden('새 비밀번호: ');
        
        if (!newPassword || newPassword.length < 4) {
            console.log('비밀번호는 최소 4자 이상이어야 합니다.');
            return;
        }
        
        const confirmPassword = await questionHidden('비밀번호 확인: ');
        
        if (newPassword !== confirmPassword) {
            console.log('\n비밀번호가 일치하지 않습니다.');
            return;
        }
        
        // 비밀번호 해싱
        console.log('\n비밀번호를 재설정하는 중...');
        const passwordHash = await bcrypt.hash(newPassword, 10);
        
        // 데이터베이스 업데이트
        await client.query(
            'UPDATE admin_users SET password_hash = $1, updated_at = CURRENT_TIMESTAMP WHERE id = $2',
            [passwordHash, selectedUser.id]
        );
        
        console.log(`\n✅ ${selectedUser.username} 계정의 비밀번호가 성공적으로 재설정되었습니다!`);
        console.log(`새 비밀번호: ${newPassword}`);
        console.log('\n⚠️  보안을 위해 비밀번호를 안전한 곳에 저장하세요.');
        
    } catch (error) {
        console.error('오류 발생:', error.message);
        process.exit(1);
    } finally {
        client.release();
        rl.close();
        pool.end();
    }
}

// 실행
if (require.main === module) {
    resetAdminPassword()
        .then(() => {
            process.exit(0);
        })
        .catch((err) => {
            console.error('비밀번호 재설정 오류:', err);
            process.exit(1);
        });
}

module.exports = { resetAdminPassword };

