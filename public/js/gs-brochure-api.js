const API_BASE_URL = typeof window !== 'undefined' && window.API_BASE_URL
    ? window.API_BASE_URL
    : (typeof window !== 'undefined' ? (window.location.origin + '/api/gs-brochure') : '/api/gs-brochure');

function formatMaxUploadSize(bytes) {
    if (!Number.isFinite(bytes) || bytes <= 0) return '40MB';
    const mb = bytes / (1024 * 1024);
    if (mb >= 1) return `${mb.toFixed(mb < 10 ? 1 : 0)}MB`;
    const kb = bytes / 1024;
    return `${Math.max(1, Math.round(kb))}KB`;
}

function getEffectiveMaxUploadBytes() {
    const appMaxBytes = 40 * 1024 * 1024;
    const runtimeMax = typeof window !== 'undefined'
        ? Number(window.GS_BROCHURE_UPLOAD_MAX_BYTES || 0)
        : 0;
    return runtimeMax > 0 ? Math.min(appMaxBytes, runtimeMax) : appMaxBytes;
}

async function apiCall(endpoint, method = 'GET', data = null, fetchOpts = {}) {
    const isFormData = data instanceof FormData;
    const options = { ...fetchOpts, method, headers: { Accept: 'application/json', ...(fetchOpts.headers || {}) } };
    if (!isFormData) options.headers['Content-Type'] = 'application/json';
    if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
        options.body = isFormData ? data : JSON.stringify(data);
    }

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
            } else {
                errorMessage = (await response.text()) || errorMessage;
            }
        } catch (_) {
            // ignore parse fallback
        }
        if (response.status === 401) errorMessage = '인증 실패: 아이디 또는 비밀번호가 올바르지 않습니다.';
        if (response.status === 422 && /The image failed to upload\./i.test(errorMessage)) {
            errorMessage = `이미지 업로드에 실패했습니다. 파일 크기가 서버 업로드 제한(${formatMaxUploadSize(getEffectiveMaxUploadBytes())})을 초과했거나 업로드가 중단되었을 수 있습니다.`;
        }
        throw new Error(errorMessage);
    }

    return await response.json();
}

window.BrochureAPI = {
    getAll: () => apiCall('/brochures?_=' + Date.now(), 'GET', null, { cache: 'no-store' }),
    create: (data) => apiCall('/brochures', 'POST', data),
    update: (id, data) => apiCall(`/brochures/${id}`, 'PUT', data),
    delete: (id) => apiCall(`/brochures/${id}`, 'DELETE'),
    updateStock: (id, quantity, date, memo) => apiCall(`/brochures/${id}/stock`, 'PUT', { quantity, date, memo: memo || '' }),
    updateWarehouseStock: (id, quantity, date, memo) => apiCall(`/brochures/${id}/stock-warehouse`, 'PUT', { quantity, date, memo: memo || '' }),
    transferToHq: (id, quantity, date, memo) => apiCall(`/brochures/${id}/transfer-to-hq`, 'PUT', { quantity, date, memo: memo || '' }),
    uploadImage: (id, file) => {
        if (!file) {
            throw new Error('이미지 파일을 선택해 주세요.');
        }
        const maxBytes = getEffectiveMaxUploadBytes();
        if ((file.size || 0) > maxBytes) {
            throw new Error(`이미지 용량은 ${formatMaxUploadSize(maxBytes)} 이하로 올려 주세요.`);
        }
        const allowedMimeTypes = new Set([
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/avif',
        ]);
        const allowedExtensions = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif']);
        const filename = String(file.name || '').toLowerCase();
        const extension = filename.includes('.') ? filename.split('.').pop() : '';
        const mimeOk = file.type ? allowedMimeTypes.has(String(file.type).toLowerCase()) : false;
        const extensionOk = !!extension && allowedExtensions.has(extension);
        if (!mimeOk && !extensionOk) {
            throw new Error('jpg, jpeg, png, gif, webp, bmp, avif 형식의 이미지만 업로드할 수 있습니다. (heic/heif는 지원하지 않습니다.)');
        }
        const formData = new FormData();
        formData.append('image', file);
        return apiCall(`/brochures/${id}/image`, 'POST', formData);
    },
    deleteImage: (id) => apiCall(`/brochures/${id}/image`, 'DELETE'),
};

window.ContactAPI = {
    getAll: () => apiCall('/contacts'),
    create: (data) => apiCall('/contacts', 'POST', data),
    update: (id, data) => apiCall(`/contacts/${id}`, 'PUT', data),
    delete: (id) => apiCall(`/contacts/${id}`, 'DELETE'),
};

window.RequestAPI = {
    getAll: () => apiCall('/requests'),
    search: (params) => {
        const q = new URLSearchParams();
        const schoolname = params && params.schoolname != null ? String(params.schoolname).trim() : '';
        const phone = params && params.phone != null ? String(params.phone).trim() : '';
        if (schoolname) q.set('schoolname', schoolname);
        if (phone) q.set('phone', phone);
        const query = q.toString();
        return apiCall('/requests/search' + (query ? '?' + query : ''));
    },
    create: (data) => apiCall('/requests', 'POST', data),
    update: (id, data) => apiCall(`/requests/${id}`, 'PUT', data),
    addInvoices: (id, invoices) => apiCall(`/requests/${id}/invoices`, 'POST', { invoices }),
    deleteInvoices: (id) => apiCall(`/requests/${id}/invoices`, 'DELETE'),
};

window.StockHistoryAPI = {
    getAll: () => apiCall('/stock-history'),
    create: (data) => apiCall('/stock-history', 'POST', data),
};

window.AdminAPI = {
    login: (username, password) => apiCall('/admin/login', 'POST', { username, password }),
    getAllUsers: () => apiCall('/admin/users'),
    createUser: (username, password) => apiCall('/admin/users', 'POST', { username, password }),
    changePassword: (userId, currentPassword, newPassword) => apiCall(`/admin/users/${userId}/password`, 'PUT', { password: currentPassword, newPassword }),
    deleteUser: (userId) => apiCall(`/admin/users/${userId}`, 'DELETE'),
    resetData: (type) => apiCall('/admin/reset', 'POST', { type }),
};

window.VerificationAPI = {
    sendCode: (phone) => apiCall('/verification/send', 'POST', { phone }),
    verify: (phone, code) => apiCall('/verification/verify', 'POST', { phone, code }),
};

window.InstitutionAPI = {
    getList: (page, params) => {
        const q = new URLSearchParams();
        if (page) q.set('page', String(page));
        if (params && params.search) q.set('search', params.search);
        if (params && params.is_active !== undefined && params.is_active !== '') q.set('is_active', String(params.is_active));
        const query = q.toString();
        return apiCall('/admin/institutions' + (query ? '?' + query : ''));
    },
    getOne: (id) => apiCall('/admin/institutions/' + id),
    create: (data) => apiCall('/admin/institutions', 'POST', data),
    update: (id, data) => apiCall('/admin/institutions/' + id, 'PUT', data),
    delete: (id) => apiCall('/admin/institutions/' + id, 'DELETE'),
    bulkSetActive: (ids, isActive) => apiCall('/admin/institutions/bulk', 'PATCH', { ids, is_active: !!isActive }),
};

window.fetchInstitutionsForAutocomplete = async function (search) {
    const q = new URLSearchParams();
    if (search && String(search).trim()) q.set('search', String(search).trim());
    const query = q.toString();
    return apiCall('/institutions' + (query ? '?' + query : ''));
};
