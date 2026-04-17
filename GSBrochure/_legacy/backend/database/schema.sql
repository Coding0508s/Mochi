-- GS Brochure Management Database Schema

-- 브로셔 마스터 테이블
CREATE TABLE IF NOT EXISTS brochures (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    stock INTEGER DEFAULT 0,
    last_stock_quantity INTEGER DEFAULT 0,
    last_stock_date TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 담당자 마스터 테이블
CREATE TABLE IF NOT EXISTS contacts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 브로셔 신청 내역 테이블
CREATE TABLE IF NOT EXISTS requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date TEXT NOT NULL,
    schoolname TEXT NOT NULL,
    address TEXT NOT NULL,
    phone TEXT NOT NULL,
    contact_id INTEGER,
    contact_name TEXT,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id)
);

-- 브로셔 신청 상세 (한 신청에 여러 브로셔 포함)
CREATE TABLE IF NOT EXISTS request_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER NOT NULL,
    brochure_id INTEGER NOT NULL,
    brochure_name TEXT NOT NULL,
    quantity INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (brochure_id) REFERENCES brochures(id)
);

-- 운송장 번호 테이블
CREATE TABLE IF NOT EXISTS invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER NOT NULL,
    invoice_number TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
);

-- 입출고 내역 테이블
CREATE TABLE IF NOT EXISTS stock_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL, -- '입고' or '출고'
    date TEXT NOT NULL,
    brochure_id INTEGER NOT NULL,
    brochure_name TEXT NOT NULL,
    quantity INTEGER NOT NULL,
    contact_name TEXT,
    schoolname TEXT,
    before_stock INTEGER NOT NULL,
    after_stock INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (brochure_id) REFERENCES brochures(id)
);

-- 관리자 계정 테이블
CREATE TABLE IF NOT EXISTS admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 인덱스 생성
CREATE INDEX IF NOT EXISTS idx_requests_date ON requests(date);
CREATE INDEX IF NOT EXISTS idx_requests_schoolname ON requests(schoolname);
CREATE INDEX IF NOT EXISTS idx_request_items_request_id ON request_items(request_id);
CREATE INDEX IF NOT EXISTS idx_invoices_request_id ON invoices(request_id);
CREATE INDEX IF NOT EXISTS idx_stock_history_date ON stock_history(date);
CREATE INDEX IF NOT EXISTS idx_stock_history_brochure_id ON stock_history(brochure_id);

