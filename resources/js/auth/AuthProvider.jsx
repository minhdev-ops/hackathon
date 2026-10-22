import { useCallback, useEffect, useState } from 'react';
import { createContext, useContext } from 'react';
import { api } from '../api/client';
import { clearToken } from './tokenStore';

/**
 * Phiên người dùng phía React (issue 02).
 *
 * - `AUTH_MODE=session` (demo hiện tại): phiên nằm ở cookie HttpOnly của
 *   Laravel; AuthProvider chỉ gọi `GET /me` để lấy thông tin.
 * - `AUTH_MODE=tide`: access token do AuthProvider giữ trong tokenStore
 *   (bộ nhớ), axios tự gắn Bearer; `GET /me` vẫn là hợp đồng duy nhất.
 */

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    const refresh = useCallback(async () => {
        setLoading(true);
        try {
            const response = await api.get('/me');
            setUser(response.data?.data ?? null);
            return response.data?.data ?? null;
        } catch (error) {
            if (error.response?.status === 401) {
                setUser(null);
                return null;
            }
            throw error;
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        refresh().catch(() => setUser(null));
    }, [refresh]);

    const logout = useCallback(() => {
        // Chế độ tide: xoá token trong bộ nhớ.
        clearToken();

        // Chế độ session: POST /logout kèm CSRF token (giống form logout
        // truyền thống) rồi chuyển hướng về /login.
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/logout';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_token';
        input.value = csrf;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }, []);

    const value = { user, loading, refresh, logout };

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    const context = useContext(AuthContext);

    if (!context) {
        throw new Error('useAuth phải được dùng bên trong <AuthProvider>.');
    }

    return context;
}
