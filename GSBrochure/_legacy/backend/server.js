const express = require('express');
const cors = require('cors');
const bodyParser = require('body-parser');
const path = require('path');
const fs = require('fs');
const { runQuery, getQuery, allQuery, pool } = require('./database/db');
const bcrypt = require('bcrypt');
const { initDatabase } = require('./database/init-db');

const app = express();
const PORT = process.env.PORT || 3000;
let server = null;

// 미들웨어
// CORS 설정 - 프로덕션 환경에서 특정 도메인만 허용하도록 설정 가능
const corsOptions = {
    origin: process.env.CORS_ORIGIN ? process.env.CORS_ORIGIN.split(',') : [
        'https://coding0508s.github.io',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        '*' // 개발 환경용
    ],
    credentials: true
};
app.use(cors(corsOptions));
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// 정적 파일 제공 (프론트엔드 파일) - 로컬 개발 환경에서만 활성화
// Railway 배포 환경에서는 프론트엔드가 GitHub Pages에서 제공되므로 비활성화
if (process.env.NODE_ENV !== 'production' || fs.existsSync(path.join(__dirname, '..', 'requestbrochure.html'))) {
    app.use(express.static(path.join(__dirname, '..')));
}

// 루트 경로 라우팅
app.get('/', (req, res) => {
    // Railway 배포 환경에서는 HTML 페이지를 직접 제공
    const html = `
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GS Brochure Management API Server</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: '맑은 고딕', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        h1 {
            color: #440b86;
            margin-bottom: 20px;
            text-align: center;
        }
        .info {
            margin-bottom: 30px;
        }
        .info p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        .endpoints {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .endpoints h2 {
            color: #440b86;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .endpoint {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .endpoint:last-child {
            border-bottom: none;
        }
        .endpoint strong {
            color: #440b86;
            display: inline-block;
            min-width: 150px;
        }
        .frontend-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #440b86;
        }
        .frontend-link a {
            display: inline-block;
            background: #440b86;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background 0.3s;
        }
        .frontend-link a:hover {
            background: #0ca22c;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>GS Brochure Management API Server</h1>
        <div class="info">
            <p><strong>버전:</strong> 1.0.0</p>
            <p>이 서버는 GS Brochure Management System의 백엔드 API 서버입니다.</p>
        </div>
        <div class="endpoints">
            <h2>사용 가능한 API 엔드포인트</h2>
            <div class="endpoint">
                <strong>브로셔:</strong> <code>/api/brochures</code>
            </div>
            <div class="endpoint">
                <strong>담당자:</strong> <code>/api/contacts</code>
            </div>
            <div class="endpoint">
                <strong>신청 내역:</strong> <code>/api/requests</code>
            </div>
            <div class="endpoint">
                <strong>입출고 내역:</strong> <code>/api/stock-history</code>
            </div>
            <div class="endpoint">
                <strong>관리자 로그인:</strong> <code>/api/admin/login</code>
            </div>
        </div>
        <div class="frontend-link">
            <p style="margin-bottom: 15px; color: #666;">실제 애플리케이션을 사용하시려면:</p>
            <a href="https://coding0508s.github.io/GSBrochure/" target="_blank">프론트엔드 애플리케이션 열기</a>
        </div>
    </div>
</body>
</html>
    `;
    res.send(html);
});

// ==================== 브로셔 관리 API ====================

// 데이터베이스 연결 상태 확인
app.get('/api/health', async (req, res) => {
    try {
        const { pool } = require('./database/db');
        const result = await pool.query('SELECT NOW() as current_time, version() as pg_version');
        const tables = await pool.query(`
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public'
            ORDER BY table_name
        `);
        
        res.json({
            status: 'ok',
            database: {
                connected: true,
                current_time: result.rows[0].current_time,
                version: result.rows[0].pg_version.split(' ')[0] + ' ' + result.rows[0].pg_version.split(' ')[1],
                tables: tables.rows.map(t => t.table_name)
            }
        });
    } catch (error) {
        res.status(500).json({
            status: 'error',
            error: error.message
        });
    }
});

// 모든 브로셔 조회
app.get('/api/brochures', async (req, res) => {
    try {
        const brochures = await allQuery('SELECT * FROM brochures ORDER BY id');
        res.json(brochures);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 브로셔 추가
app.post('/api/brochures', async (req, res) => {
    try {
        const { name, stock = 0 } = req.body;
        if (!name) {
            return res.status(400).json({ error: '브로셔명은 필수입니다.' });
        }
        
        console.log(`[POST /api/brochures] 브로셔 추가 시도: ${name}, 재고: ${stock}`);
        const result = await runQuery(
            'INSERT INTO brochures (name, stock) VALUES ($1, $2) RETURNING id',
            [name, stock]
        );
        console.log(`[POST /api/brochures] 브로셔 추가 성공: ID=${result.rows[0].id}`);
        res.json({ id: result.rows[0].id, name, stock });
    } catch (error) {
        console.error('[POST /api/brochures] 오류:', error.message);
        console.error('[POST /api/brochures] 스택:', error.stack);
        res.status(500).json({ error: error.message });
    }
});

// 브로셔 수정
app.put('/api/brochures/:id', async (req, res) => {
    try {
        const { id } = req.params;
        const { name, stock } = req.body;
        
        await runQuery(
            'UPDATE brochures SET name = $1, stock = $2, updated_at = CURRENT_TIMESTAMP WHERE id = $3',
            [name, stock, id]
        );
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 브로셔 삭제
app.delete('/api/brochures/:id', async (req, res) => {
    try {
        const { id } = req.params;
        await runQuery('DELETE FROM brochures WHERE id = $1', [id]);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 브로셔 재고 업데이트
app.put('/api/brochures/:id/stock', async (req, res) => {
    try {
        const { id } = req.params;
        const { quantity, date } = req.body;
        
        const brochure = await getQuery('SELECT * FROM brochures WHERE id = $1', [id]);
        if (!brochure) {
            return res.status(404).json({ error: '브로셔를 찾을 수 없습니다.' });
        }
        
        const newStock = brochure.stock + quantity;
        await runQuery(
            'UPDATE brochures SET stock = $1, last_stock_quantity = $2, last_stock_date = $3, updated_at = CURRENT_TIMESTAMP WHERE id = $4',
            [newStock, quantity, date, id]
        );
        
        res.json({ success: true, stock: newStock });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// ==================== 담당자 관리 API ====================

// 모든 담당자 조회
app.get('/api/contacts', async (req, res) => {
    try {
        const contacts = await allQuery('SELECT * FROM contacts ORDER BY id');
        res.json(contacts);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 담당자 추가
app.post('/api/contacts', async (req, res) => {
    try {
        const { name } = req.body;
        if (!name) {
            return res.status(400).json({ error: '담당자명은 필수입니다.' });
        }
        
        const result = await runQuery('INSERT INTO contacts (name) VALUES ($1) RETURNING id', [name]);
        res.json({ id: result.rows[0].id, name });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 담당자 수정
app.put('/api/contacts/:id', async (req, res) => {
    try {
        const { id } = req.params;
        const { name } = req.body;
        
        await runQuery(
            'UPDATE contacts SET name = $1, updated_at = CURRENT_TIMESTAMP WHERE id = $2',
            [name, id]
        );
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 담당자 삭제
app.delete('/api/contacts/:id', async (req, res) => {
    try {
        const { id } = req.params;
        await runQuery('DELETE FROM contacts WHERE id = $1', [id]);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// ==================== 신청 내역 API ====================

// 모든 신청 내역 조회
app.get('/api/requests', async (req, res) => {
    try {
        const requests = await allQuery(`
            SELECT r.*, 
                   STRING_AGG(DISTINCT ri.brochure_id::text || ':' || ri.brochure_name || ':' || ri.quantity::text, '|') as items,
                   STRING_AGG(DISTINCT i.invoice_number, '|') as invoices
            FROM requests r
            LEFT JOIN request_items ri ON r.id = ri.request_id
            LEFT JOIN invoices i ON r.id = i.request_id
            GROUP BY r.id, r.date, r.schoolname, r.address, r.phone, r.contact_id, r.contact_name, r.submitted_at, r.updated_at
            ORDER BY r.submitted_at DESC
        `);
        
        // 데이터 파싱
        const parsedRequests = requests.map(req => ({
            ...req,
            items: req.items ? req.items.split('|').map(item => {
                const [id, name, qty] = item.split(':');
                return { brochure_id: id, brochure_name: name, quantity: parseInt(qty) };
            }) : [],
            invoices: req.invoices ? req.invoices.split('|') : []
        }));
        
        res.json(parsedRequests);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 신청 내역 추가
app.post('/api/requests', async (req, res) => {
    try {
        const { date, schoolname, address, phone, contact_id, contact_name, brochures, invoices = [] } = req.body;
        
        if (!date || !schoolname || !address || !phone || !brochures || brochures.length === 0) {
            return res.status(400).json({ error: '필수 필드가 누락되었습니다.' });
        }
        
        console.log(`[POST /api/requests] 신청 내역 추가 시도: ${schoolname}, 브로셔 수: ${brochures.length}`);
        const { pool } = require('./database/db');
        const client = await pool.connect();
        
        try {
            await client.query('BEGIN');
            
            // 신청 내역 추가
            const requestResult = await client.query(
                'INSERT INTO requests (date, schoolname, address, phone, contact_id, contact_name) VALUES ($1, $2, $3, $4, $5, $6) RETURNING id',
                [date, schoolname, address, phone, contact_id, contact_name]
            );
            
            const requestId = requestResult.rows[0].id;
            console.log(`[POST /api/requests] 신청 내역 생성: ID=${requestId}`);
            
            // 브로셔 항목 추가
            for (const brochure of brochures) {
                await client.query(
                    'INSERT INTO request_items (request_id, brochure_id, brochure_name, quantity) VALUES ($1, $2, $3, $4)',
                    [requestId, brochure.brochure, brochure.brochureName, brochure.quantity]
                );
            }
            console.log(`[POST /api/requests] 브로셔 항목 ${brochures.length}개 추가 완료`);
            
            // 운송장 번호 추가
            if (invoices && invoices.length > 0) {
                for (const invoice of invoices) {
                    if (invoice && invoice.trim()) {
                        await client.query(
                            'INSERT INTO invoices (request_id, invoice_number) VALUES ($1, $2)',
                            [requestId, invoice.trim()]
                        );
                    }
                }
                console.log(`[POST /api/requests] 운송장 번호 ${invoices.length}개 추가 완료`);
            }
            
            await client.query('COMMIT');
            console.log(`[POST /api/requests] 신청 내역 추가 성공: ID=${requestId}`);
            res.json({ id: requestId });
        } catch (err) {
            await client.query('ROLLBACK');
            console.error('[POST /api/requests] 트랜잭션 오류:', err.message);
            console.error('[POST /api/requests] 스택:', err.stack);
            throw err;
        } finally {
            client.release();
        }
    } catch (error) {
        console.error('[POST /api/requests] 오류:', error.message);
        console.error('[POST /api/requests] 스택:', error.stack);
        res.status(500).json({ error: error.message });
    }
});

// 신청 내역 수정
app.put('/api/requests/:id', async (req, res) => {
    try {
        const { id } = req.params;
        const { date, schoolname, address, phone, contact_id, contact_name, brochures } = req.body;
        
        const { pool } = require('./database/db');
        const client = await pool.connect();
        
        try {
            await client.query('BEGIN');
            
            // 신청 내역 업데이트
            await client.query(
                'UPDATE requests SET date = $1, schoolname = $2, address = $3, phone = $4, contact_id = $5, contact_name = $6, updated_at = CURRENT_TIMESTAMP WHERE id = $7',
                [date, schoolname, address, phone, contact_id, contact_name, id]
            );
            
            // 기존 항목 삭제
            await client.query('DELETE FROM request_items WHERE request_id = $1', [id]);
            
            // 새 항목 추가
            if (brochures && brochures.length > 0) {
                for (const brochure of brochures) {
                    await client.query(
                        'INSERT INTO request_items (request_id, brochure_id, brochure_name, quantity) VALUES ($1, $2, $3, $4)',
                        [id, brochure.brochure, brochure.brochureName, brochure.quantity]
                    );
                }
            }
            
            await client.query('COMMIT');
            res.json({ success: true });
        } catch (err) {
            await client.query('ROLLBACK');
            throw err;
        } finally {
            client.release();
        }
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 운송장 번호 추가
app.post('/api/requests/:id/invoices', async (req, res) => {
    try {
        const { id } = req.params;
        const { invoices } = req.body;
        
        if (!invoices || !Array.isArray(invoices)) {
            return res.status(400).json({ error: '운송장 번호 배열이 필요합니다.' });
        }
        
        console.log(`[POST /api/requests/${id}/invoices] 운송장 번호 추가 시도: ${invoices.length}개`);
        const { pool } = require('./database/db');
        const client = await pool.connect();
        
        try {
            for (const invoice of invoices) {
                if (invoice && invoice.trim()) {
                    await client.query(
                        'INSERT INTO invoices (request_id, invoice_number) VALUES ($1, $2)',
                        [id, invoice.trim()]
                    );
                    console.log(`[POST /api/requests/${id}/invoices] 운송장 번호 추가: ${invoice.trim()}`);
                }
            }
            console.log(`[POST /api/requests/${id}/invoices] 운송장 번호 추가 성공`);
            res.json({ success: true });
        } finally {
            client.release();
        }
    } catch (error) {
        console.error(`[POST /api/requests/${req.params.id}/invoices] 오류:`, error.message);
        console.error(`[POST /api/requests/${req.params.id}/invoices] 스택:`, error.stack);
        res.status(500).json({ error: error.message });
    }
});

// 운송장 번호 삭제 (신청 내역의 모든 운송장 번호 제거)
app.delete('/api/requests/:id/invoices', async (req, res) => {
    try {
        const { id } = req.params;
        await runQuery('DELETE FROM invoices WHERE request_id = $1', [id]);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// ==================== 입출고 내역 API ====================

// 입출고 내역 조회
app.get('/api/stock-history', async (req, res) => {
    try {
        const history = await allQuery('SELECT * FROM stock_history ORDER BY created_at DESC');
        res.json(history);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 입출고 내역 추가
app.post('/api/stock-history', async (req, res) => {
    try {
        const { type, date, brochure_id, brochure_name, quantity, contact_name, schoolname, before_stock, after_stock } = req.body;
        
        await runQuery(
            'INSERT INTO stock_history (type, date, brochure_id, brochure_name, quantity, contact_name, schoolname, before_stock, after_stock) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)',
            [type, date, brochure_id, brochure_name, quantity, contact_name, schoolname, before_stock, after_stock]
        );
        
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// ==================== 관리자 인증 API ====================

// 관리자 로그인
app.post('/api/admin/login', async (req, res) => {
    try {
        const { username, password } = req.body;
        
        if (!username || !password) {
            return res.status(400).json({ error: '사용자명과 비밀번호가 필요합니다.' });
        }
        
        const user = await getQuery('SELECT * FROM admin_users WHERE username = $1', [username]);
        
        if (!user) {
            return res.status(401).json({ error: '인증 실패' });
        }
        
        const isValid = await bcrypt.compare(password, user.password_hash);
        
        if (!isValid) {
            return res.status(401).json({ error: '인증 실패' });
        }
        
        res.json({ success: true, username: user.username });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 모든 관리자 계정 조회
app.get('/api/admin/users', async (req, res) => {
    try {
        const users = await allQuery('SELECT id, username, created_at, updated_at FROM admin_users ORDER BY id');
        res.json(users);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 관리자 계정 추가
app.post('/api/admin/users', async (req, res) => {
    try {
        const { username, password } = req.body;
        
        if (!username || !password) {
            return res.status(400).json({ error: '사용자명과 비밀번호가 필요합니다.' });
        }
        
        // 중복 확인
        const existingUser = await getQuery('SELECT * FROM admin_users WHERE username = $1', [username]);
        if (existingUser) {
            return res.status(400).json({ error: '이미 존재하는 사용자명입니다.' });
        }
        
        // 비밀번호 해싱
        const passwordHash = await bcrypt.hash(password, 10);
        
        // 계정 생성
        const result = await runQuery(
            'INSERT INTO admin_users (username, password_hash) VALUES ($1, $2) RETURNING id',
            [username, passwordHash]
        );
        
        res.json({ 
            success: true, 
            id: result.rows[0].id, 
            username: username,
            message: '계정이 생성되었습니다.' 
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 관리자 비밀번호 변경
app.put('/api/admin/users/:id/password', async (req, res) => {
    try {
        const { id } = req.params;
        const { password, newPassword } = req.body;
        
        if (!password || !newPassword) {
            return res.status(400).json({ error: '현재 비밀번호와 새 비밀번호가 필요합니다.' });
        }
        
        // 사용자 확인
        const user = await getQuery('SELECT * FROM admin_users WHERE id = $1', [id]);
        if (!user) {
            return res.status(404).json({ error: '사용자를 찾을 수 없습니다.' });
        }
        
        // 현재 비밀번호 확인
        const isValid = await bcrypt.compare(password, user.password_hash);
        if (!isValid) {
            return res.status(401).json({ error: '현재 비밀번호가 올바르지 않습니다.' });
        }
        
        // 새 비밀번호 해싱
        const newPasswordHash = await bcrypt.hash(newPassword, 10);
        
        // 비밀번호 업데이트
        await runQuery(
            'UPDATE admin_users SET password_hash = $1, updated_at = CURRENT_TIMESTAMP WHERE id = $2',
            [newPasswordHash, id]
        );
        
        res.json({ success: true, message: '비밀번호가 변경되었습니다.' });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// 관리자 계정 삭제
app.delete('/api/admin/users/:id', async (req, res) => {
    try {
        const { id } = req.params;
        
        // 사용자 확인
        const user = await getQuery('SELECT * FROM admin_users WHERE id = $1', [id]);
        if (!user) {
            return res.status(404).json({ error: '사용자를 찾을 수 없습니다.' });
        }
        
        // 마지막 계정인지 확인
        const allUsers = await allQuery('SELECT * FROM admin_users');
        if (allUsers.length <= 1) {
            return res.status(400).json({ error: '최소 하나의 관리자 계정이 필요합니다.' });
        }
        
        // 계정 삭제
        await runQuery('DELETE FROM admin_users WHERE id = $1', [id]);
        
        res.json({ success: true, message: '계정이 삭제되었습니다.' });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// PostgreSQL 사용 시 파일 시스템 체크 불필요

// 서버 시작 함수
function startServer() {
    server = app.listen(PORT, () => {
        console.log(`서버가 포트 ${PORT}에서 실행 중입니다.`);
        console.log(`API 엔드포인트: http://localhost:${PORT}/api`);
        console.log(`환경: ${process.env.NODE_ENV || 'development'}`);
        if (process.env.NODE_ENV === 'production') {
            console.log('프로덕션 모드 활성화');
        }
    });
}

// Graceful shutdown 처리
async function gracefulShutdown(signal) {
    console.log(`${signal} 신호를 받았습니다. 서버를 정상적으로 종료합니다...`);
    
    if (server) {
        server.close(() => {
            console.log('HTTP 서버가 종료되었습니다.');
            
            // 데이터베이스 연결 풀 종료
            pool.end(() => {
                console.log('데이터베이스 연결 풀이 종료되었습니다.');
                process.exit(0);
            });
        });
        
        // 강제 종료 타임아웃 (10초)
        setTimeout(() => {
            console.error('강제 종료: 타임아웃 초과');
            process.exit(1);
        }, 10000);
    } else {
        // 서버가 시작되지 않은 경우
        pool.end(() => {
            console.log('데이터베이스 연결 풀이 종료되었습니다.');
            process.exit(0);
        });
    }
}

// 종료 신호 처리
process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
process.on('SIGINT', () => gracefulShutdown('SIGINT'));

// 처리되지 않은 예외 처리
process.on('uncaughtException', (err) => {
    console.error('처리되지 않은 예외:', err);
    gracefulShutdown('uncaughtException');
});

process.on('unhandledRejection', (reason, promise) => {
    console.error('처리되지 않은 Promise 거부:', reason);
    gracefulShutdown('unhandledRejection');
});

// 데이터베이스 초기화 확인 및 실행
async function ensureDatabaseInitialized() {
    try {
        // PostgreSQL에서 테이블 존재 여부 확인
        const { allQuery } = require('./database/db');
        try {
            const tables = await allQuery(`
                SELECT table_name as name 
                FROM information_schema.tables 
                WHERE table_schema = 'public'
            `);
            
            // 필수 테이블이 없으면 초기화
            const requiredTables = ['brochures', 'contacts', 'requests', 'request_items', 'invoices', 'stock_history', 'admin_users'];
            const existingTables = tables.map(t => t.name);
            const missingTables = requiredTables.filter(t => !existingTables.includes(t));
            
            if (missingTables.length > 0) {
                console.log(`필수 테이블이 없습니다: ${missingTables.join(', ')}. 초기화를 시작합니다...`);
                await initDatabase();
                console.log('데이터베이스 자동 초기화 완료');
            } else {
                console.log('데이터베이스가 정상적으로 초기화되어 있습니다.');
            }
        } catch (err) {
            // 테이블 확인 중 오류 발생 시 초기화 시도
            console.log('데이터베이스 테이블 확인 중 오류 발생:', err.message);
            console.log('초기화를 시작합니다...');
            await initDatabase();
            console.log('데이터베이스 자동 초기화 완료');
        }
    } catch (err) {
        console.error('데이터베이스 초기화 오류:', err);
        // 초기화 실패해도 서버는 시작
    }
}

// 데이터베이스 초기화 확인 후 서버 시작
ensureDatabaseInitialized()
    .then(() => {
        console.log('데이터베이스 초기화 확인 완료. 서버를 시작합니다...');
        startServer();
    })
    .catch((err) => {
        console.error('데이터베이스 초기화 확인 중 오류:', err);
        console.error('오류 상세:', err.message);
        console.error('스택:', err.stack);
        console.log('오류가 발생했지만 서버는 시작합니다. 데이터베이스 연결을 확인하세요.');
        startServer();
    });

