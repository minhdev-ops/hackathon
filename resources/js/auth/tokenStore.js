/**
 * Bộ nhớ token trong RAM cho chế độ `AUTH_MODE=tide` (issue 02).
 *
 * - Token chỉ nằm trong biến module (bộ nhớ trang), KHÔNG ghi
 *   localStorage/sessionStorage/cookie (SRS NFR-SEC-01).
 * - Chế độ `session` (đang chạy demo) không dùng token; interceptor
 *   của axios chỉ gắn Bearer khi có token thật.
 */

let accessToken = null;
let onCleared = null;

export function setToken(token) {
    accessToken = typeof token === 'string' && token.length > 0 ? token : null;
}

export function getToken() {
    return accessToken;
}

export function clearToken() {
    accessToken = null;
    if (typeof onCleared === 'function') {
        onCleared();
    }
}

/** Gọi khi token bị xoá (VD: để AuthProvider huỷ phiên phía client). */
export function onTokenCleared(handler) {
    onCleared = handler;
}

/** Trả về header Authorization nếu đang có token, ngược lại rỗng. */
export function authHeader() {
    return accessToken ? { Authorization: `Bearer ${accessToken}` } : {};
}
