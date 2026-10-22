import axios from 'axios';
import { authHeader, clearToken } from '../auth/tokenStore';

export const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

/*
 * AUTH_MODE=tide: đính kèm Bearer token từ bộ nhớ (tokenStore).
 * AUTH_MODE=session: không có token trong store → interceptor gửi
 * cookie session HttpOnly như cũ, same-origin qua Vite proxy.
 */
api.interceptors.request.use((config) => ({
    ...config,
    headers: { ...config.headers, ...authHeader() },
}));

/*
 * Xử lý 401 toàn cục (issue 02): xoá token trong bộ nhớ và chuyển
 * về /login một lần duy nhất (không loop). Trang chủ công khai (`/`)
 * cho phép khách xem nên không redirect.
 */
let redirectingToLogin = false;
let redirectingToVerify = false;

api.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const code = error.response?.data?.error?.code;
        const path = typeof window !== 'undefined' ? window.location.pathname : '/';

        // Chưa xác thực email (403 EMAIL_NOT_VERIFIED) → về trang xác nhận
        // một lần duy nhất, không loop.
        if (
            status === 403
            && code === 'EMAIL_NOT_VERIFIED'
            && !redirectingToVerify
            && path !== '/email/verify'
        ) {
            redirectingToVerify = true;

            if (typeof window !== 'undefined') {
                window.location.assign('/email/verify');
            }
        }

        if (
            status === 401
            && !redirectingToLogin
            && path !== '/'
            && path !== '/login'
        ) {
            redirectingToLogin = true;
            clearToken();

            if (typeof window !== 'undefined') {
                window.location.assign('/login');
            }
        }

        return Promise.reject(error);
    },
);
