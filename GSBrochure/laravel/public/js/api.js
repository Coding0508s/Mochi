// API 유틸리티 함수 (Laravel: window.API_BASE_URL 사용)
const API_BASE_URL = typeof window !== 'undefined' && window.API_BASE_URL
    ? window.API_BASE_URL
    : (() => {
        if (typeof window !== 'undefined' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
            return (window.location.origin || '') + '/api';
        }
        return (window.location.origin || 'http://localhost:8000') + '/api';
    })();

async function apiCall(endpoint, method = 'GET', data = null, fetchOpts = {}) {
    const isFormData = data instanceof FormData;
    const options = { ...fetchOpts, method, headers: { 'Accept': 'application/json', ...(fetchOpts.headers || {}) } };
    if (!isFormData) options.headers['Content-Type'] = 'application/json';
    if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) options.body = isFormData ? data : JSON.stringify(data);
    try {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, options);
        if (!response.ok) {
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const ct = response.headers.get('content-type');
                if (ct && ct.includes('application/json')) {
                    const err = await response.json();
                    errorMessage = err.error || err.message || errorMessage;
                    if (errorMessage === `HTTP error! status: ${response.status}` && err.errors && typeof err.errors === 'object') {
                        const keys = Object.keys(err.errors);
                        if (keys.length && err.errors[keys[0]] && err.errors[keys[0]][0]) errorMessage = err.errors[keys[0]][0];
                    }
                } else errorMessage = (await response.text()) || errorMessage;
            } catch (_) {}
            if (response.status === 401) errorMessage = '인증 실패: 아이디 또는 비밀번호가 올바르지 않습니다.';
            throw new Error(errorMessage);
        }
        const ct = response.headers.get('content-type');
        if (ct && !ct.includes('application/json')) {
            const text = await response.text();
            if (text && text.trimStart().startsWith('<')) {
                throw new Error('서버가 HTML을 반환했습니다. API 주소 또는 서버 구성을 확인해 주세요. (이미지 업로드: ' + response.status + ')');
            }
        }
        return await response.json();
    } catch (e) {
        if (e instanceof SyntaxError && e.message && e.message.includes('JSON')) {
            console.error('API 호출 오류: 서버 응답이 JSON이 아닙니다.', e);
            throw new Error('서버가 JSON 대신 HTML을 반환했습니다. API 경로(/api/brochures/.../image) 및 storage 링크(php artisan storage:link)를 확인해 주세요.');
        }
        console.error('API 호출 오류:', e);
        throw e;
    }
}

const BrochureAPI = {
    getAll: () => apiCall('/brochures?_=' + Date.now(), 'GET', null, { cache: 'no-store' }),
    create: (data) => apiCall('/brochures', 'POST', data),
    update: (id, data) => apiCall(`/brochures/${id}`, 'PUT', data),
    delete: (id) => apiCall(`/brochures/${id}`, 'DELETE'),
    updateStock: (id, quantity, date, memo) => apiCall(`/brochures/${id}/stock`, 'PUT', { quantity, date, memo: memo || '' }),
    updateWarehouseStock: (id, quantity, date, memo) => apiCall(`/brochures/${id}/stock-warehouse`, 'PUT', { quantity, date, memo: memo || '' }),
    transferToHq: (id, quantity, date, memo) => apiCall(`/brochures/${id}/transfer-to-hq`, 'PUT', { quantity, date, memo: memo || '' }),
    uploadImage: (id, file) => {
        const formData = new FormData();
        formData.append('image', file);
        return apiCall(`/brochures/${id}/image`, 'POST', formData);
    },
    deleteImage: (id) => apiCall(`/brochures/${id}/image`, 'DELETE')
};
const ContactAPI = {
    getAll: () => apiCall('/contacts'),
    create: (data) => apiCall('/contacts', 'POST', data),
    update: (id, data) => apiCall(`/contacts/${id}`, 'PUT', data),
    delete: (id) => apiCall(`/contacts/${id}`, 'DELETE')
};
const RequestAPI = {
    getAll: () => apiCall('/requests'),
    search: (params) => {
        const q = new URLSearchParams();
        const sn = params && params.schoolname != null ? String(params.schoolname).trim() : '';
        const ph = params && params.phone != null ? String(params.phone).trim() : '';
        if (sn) q.set('schoolname', sn);
        if (ph) q.set('phone', ph);
        const query = q.toString();
        return apiCall('/requests/search' + (query ? '?' + query : ''));
    },
    create: (data) => apiCall('/requests', 'POST', data),
    update: (id, data) => apiCall(`/requests/${id}`, 'PUT', data),
    addInvoices: (id, invoices) => apiCall(`/requests/${id}/invoices`, 'POST', { invoices }),
    deleteInvoices: (id) => apiCall(`/requests/${id}/invoices`, 'DELETE')
};
const StockHistoryAPI = {
    getAll: () => apiCall('/stock-history'),
    create: (data) => apiCall('/stock-history', 'POST', data)
};
const AdminAPI = {
    login: (username, password) => apiCall('/admin/login', 'POST', { username, password }),
    getAllUsers: () => apiCall('/admin/users'),
    createUser: (username, password) => apiCall('/admin/users', 'POST', { username, password }),
    changePassword: (userId, currentPassword, newPassword) => apiCall(`/admin/users/${userId}/password`, 'PUT', { password: currentPassword, newPassword }),
    deleteUser: (userId) => apiCall(`/admin/users/${userId}`, 'DELETE'),
    resetData: (type) => apiCall('/admin/reset', 'POST', { type })
};
const VerificationAPI = {
    sendCode: (phone) => apiCall('/verification/send', 'POST', { phone }),
    verify: (phone, code) => apiCall('/verification/verify', 'POST', { phone, code })
};
const InstitutionAPI = {
    getList: (page, params) => {
        var q = new URLSearchParams();
        if (page) q.set('page', String(page));
        if (params && params.search) q.set('search', params.search);
        if (params && params.is_active !== undefined && params.is_active !== '') q.set('is_active', String(params.is_active));
        var query = q.toString();
        return apiCall('/admin/institutions' + (query ? '?' + query : ''));
    },
    getOne: (id) => apiCall('/admin/institutions/' + id),
    create: (data) => apiCall('/admin/institutions', 'POST', data),
    update: (id, data) => apiCall('/admin/institutions/' + id, 'PUT', data),
    delete: (id) => apiCall('/admin/institutions/' + id, 'DELETE'),
    bulkSetActive: (ids, isActive) => apiCall('/admin/institutions/bulk', 'PATCH', { ids: ids, is_active: !!isActive })
};

window.BrochureAPI = BrochureAPI;
window.ContactAPI = ContactAPI;
window.RequestAPI = RequestAPI;
window.StockHistoryAPI = StockHistoryAPI;
window.AdminAPI = AdminAPI;
window.VerificationAPI = VerificationAPI;
window.InstitutionAPI = InstitutionAPI;

/** Public: fetch institutions for autocomplete (active only). search optional. */
async function fetchInstitutionsForAutocomplete(search) {
    const q = new URLSearchParams();
    if (search && String(search).trim()) q.set('search', String(search).trim());
    const query = q.toString();
    return apiCall('/institutions' + (query ? '?' + query : ''));
}
window.fetchInstitutionsForAutocomplete = fetchInstitutionsForAutocomplete;