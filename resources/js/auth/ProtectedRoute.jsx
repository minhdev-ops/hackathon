import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from './AuthProvider';

/**
 * Guard theo vai trò cho React Router (issue 02).
 *
 * - Chưa đăng nhập (401 từ /me) → chuyển /login kèm `from` để quay lại.
 * - Sai vai trò → trang 403 tiếng Việt, không crash.
 * - Guard phía server (middleware `role:...`) vẫn là lớp bảo vệ chính;
 *   guard này chỉ để UX không vấp 403 giữa chừng.
 */
export default function ProtectedRoute({ children, roles }) {
    const { user, loading } = useAuth();
    const location = useLocation();

    if (loading) {
        return <div className="p-8 text-sm text-muted">Đang kiểm tra phiên…</div>;
    }

    if (!user) {
        return <Navigate to="/login" replace state={{ from: location.pathname }} />;
    }

    if (Array.isArray(roles) && roles.length > 0 && !roles.includes(user.role)) {
        return (
            <div className="mx-auto max-w-xl p-8">
                <div className="rounded-xl border border-border bg-surface p-6">
                    <h1 className="text-lg font-semibold">403 — Không có quyền truy cập</h1>
                    <p className="mt-2 text-sm text-muted">
                        Tài khoản <strong>{user.email}</strong> đang ở vai trò «{user.role}» nên không vào
                        được màn hình này. Vai trò yêu cầu: {roles.join(', ')}.
                    </p>
                    <a href="/" className="mt-4 inline-block rounded border border-border px-4 py-2 text-sm hover:bg-elevated">
                        Về trang chủ
                    </a>
                </div>
            </div>
        );
    }

    return children;
}
