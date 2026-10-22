const BASE = '/api/v1/admin';

export class ApiError extends Error {
    constructor(status, error) {
        super(error?.message || 'Yêu cầu thất bại.');
        this.name = 'ApiError';
        this.status = status;
        this.code = error?.code ?? 'UNKNOWN';
        this.details = error?.details ?? null;
    }
}

export function formatApiError(error) {
    if (!(error instanceof ApiError)) {
        return 'Không kết nối được máy chủ. Kiểm tra lại phiên đăng nhập.';
    }

    if (error.code === 'REAUTH_REQUIRED') {
        return 'Cần xác thực lại trước khi duyệt Change Request.';
    }

    if (error.code === 'VALIDATION_ERROR' && error.details) {
        const first = Object.values(error.details)[0];

        if (Array.isArray(first) && first.length > 0) {
            return first[0];
        }
    }

    return `${error.code}: ${error.message}`;
}

async function request(path, { method = 'GET', body } = {}) {
    const response = await fetch(`${BASE}${path}`, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    const payload = await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(response.status, payload?.error);
    }

    return payload;
}

export const quorumApi = {
    meta: () => request('/quorum/meta'),
    list: () => request('/change-requests'),
    create: (type, payload) => request('/change-requests', { method: 'POST', body: { type, payload } }),
    approve: (id) => request(`/change-requests/${id}/approve`, { method: 'POST', body: {} }),
    reject: (id) => request(`/change-requests/${id}/reject`, { method: 'POST', body: {} }),
    reauth: (password) => request('/reauth', { method: 'POST', body: { password } }),
    directThreshold: () =>
        request('/direct/quorum-threshold', { method: 'PATCH', body: { required_approvals: 1 } }),
};

export async function logout() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    await fetch('/logout', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': token },
    });

    window.location.href = '/login';
}
