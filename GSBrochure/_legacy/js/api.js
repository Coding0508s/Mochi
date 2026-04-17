// API 유틸리티 함수
// 환경에 따라 API URL 자동 설정
const API_BASE_URL = (() => {
    // 프로덕션 환경 (GitHub Pages 등)
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        // Railway 백엔드 서버 URL
        return 'https://gsbrochure.up.railway.app/api';
    }
    // 개발 환경
    return 'http://localhost:3000/api';
})();

// 공통 API 호출 함수
async function apiCall(endpoint, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        }
    };

    if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, options);
        
        if (!response.ok) {
            // 응답 본문을 안전하게 파싱
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const error = await response.json();
                    errorMessage = error.error || errorMessage;
                } else {
                    const text = await response.text();
                    errorMessage = text || errorMessage;
                }
            } catch (parseError) {
                // JSON 파싱 실패 시 기본 메시지 사용
                console.warn('응답 파싱 오류:', parseError);
            }
            
            // 401 에러의 경우 사용자 친화적 메시지
            if (response.status === 401) {
                errorMessage = '인증 실패: 아이디 또는 비밀번호가 올바르지 않습니다.';
            }
            
            throw new Error(errorMessage);
        }

        const result = await response.json();
        return result;
    } catch (error) {
        console.error('API 호출 오류:', error);
        throw error;
    }
}

// ==================== 브로셔 API ====================
const BrochureAPI = {
    // 모든 브로셔 조회
    getAll: () => apiCall('/brochures'),
    
    // 브로셔 추가
    create: (data) => apiCall('/brochures', 'POST', data),
    
    // 브로셔 수정
    update: (id, data) => apiCall(`/brochures/${id}`, 'PUT', data),
    
    // 브로셔 삭제
    delete: (id) => apiCall(`/brochures/${id}`, 'DELETE'),
    
    // 재고 업데이트
    updateStock: (id, quantity, date) => apiCall(`/brochures/${id}/stock`, 'PUT', { quantity, date })
};

// ==================== 담당자 API ====================
const ContactAPI = {
    // 모든 담당자 조회
    getAll: () => apiCall('/contacts'),
    
    // 담당자 추가
    create: (data) => apiCall('/contacts', 'POST', data),
    
    // 담당자 수정
    update: (id, data) => apiCall(`/contacts/${id}`, 'PUT', data),
    
    // 담당자 삭제
    delete: (id) => apiCall(`/contacts/${id}`, 'DELETE')
};

// ==================== 신청 내역 API ====================
const RequestAPI = {
    // 모든 신청 내역 조회
    getAll: () => apiCall('/requests'),
    
    // 신청 내역 추가
    create: (data) => apiCall('/requests', 'POST', data),
    
    // 신청 내역 수정
    update: (id, data) => apiCall(`/requests/${id}`, 'PUT', data),
    
    // 운송장 번호 추가
    addInvoices: (id, invoices) => apiCall(`/requests/${id}/invoices`, 'POST', { invoices }),
    
    // 운송장 번호 삭제
    deleteInvoices: (id) => apiCall(`/requests/${id}/invoices`, 'DELETE')
};

// ==================== 입출고 내역 API ====================
const StockHistoryAPI = {
    // 입출고 내역 조회
    getAll: () => apiCall('/stock-history'),
    
    // 입출고 내역 추가
    create: (data) => apiCall('/stock-history', 'POST', data)
};

// ==================== 관리자 API ====================
const AdminAPI = {
    // 로그인
    login: (username, password) => apiCall('/admin/login', 'POST', { username, password }),
    
    // 모든 관리자 계정 조회
    getAllUsers: () => apiCall('/admin/users', 'GET'),
    
    // 관리자 계정 추가
    createUser: (username, password) => apiCall('/admin/users', 'POST', { username, password }),
    
    // 비밀번호 변경
    changePassword: (userId, currentPassword, newPassword) => apiCall(`/admin/users/${userId}/password`, 'PUT', {
        password: currentPassword,
        newPassword: newPassword
    }),
    
    // 관리자 계정 삭제
    deleteUser: (userId) => apiCall(`/admin/users/${userId}`, 'DELETE')
};

// 전역으로 내보내기
window.BrochureAPI = BrochureAPI;
window.ContactAPI = ContactAPI;
window.RequestAPI = RequestAPI;
window.StockHistoryAPI = StockHistoryAPI;
window.AdminAPI = AdminAPI;

