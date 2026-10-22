import { createContext, useCallback, useContext, useEffect, useState } from 'react';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [me, setMe] = useState(null);
    const [loading, setLoading] = useState(true);

    const fetchMe = useCallback(async () => {
        try {
            const response = await fetch('/api/v1/me', {
                credentials: 'include',
                headers: { Accept: 'application/json' },
            });
            if (response.ok) {
                const data = await response.json();
                setMe(data.data ?? null);
            } else {
                setMe(null);
            }
        } catch (err) {
            setMe(null);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchMe();
    }, [fetchMe]);

    const isAdmin = me ? me.role === 'admin' : false;
    const isOwner = me ? me.role === 'owner' : false;
    const isAgent = me ? me.role === 'agent' : false;

    return (
        <AuthContext.Provider value={{ me, loading, isAdmin, isOwner, isAgent }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    return useContext(AuthContext);
}
