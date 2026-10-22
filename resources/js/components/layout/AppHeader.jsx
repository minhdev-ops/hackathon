import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../../auth/AuthProvider';

const NAV_LINKS = [
    { to: '/', label: 'Trang chủ' },
    { to: '/vault', label: 'Két tài liệu' },
    { to: '/playbooks', label: 'Playbook' },
    { to: '/agent-console', label: 'Agent Console' },
    { to: '/timeline', label: 'Nhật ký' },
];

export default function AppHeader() {
    const location = useLocation();
    const navigate = useNavigate();
    const { user, logout } = useAuth();

    return (
        <header className="sticky top-0 z-40 border-b border-border bg-surface/95 backdrop-blur bg-#FDFDFC/80">
            <div className="max-w-5xl mx-auto px-4 lg:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4 py-3">
                    <Link
                        to="/"
                        onClick={() => navigate('/')}
                        className="flex items-center gap-2"
                        aria-label="Trang chủ TrustGate"
                    >
                        <span className="rounded-md bg-accent text-[#FDFDFC] px-2 py-1 text-xs font-bold">TG</span>
                        <span className="text-base font-bold tracking-tight">TrustGate</span>
                    </Link>

                    <nav
                        className="hidden md:flex flex-1 items-center gap-1 text-sm"
                        aria-label="Chính đầu điều hướng"
                    >
                        {NAV_LINKS.map((link) => {
                            const isActive = location.pathname === link.to || (link.to !== '/' && location.pathname.startsWith(link.to));
                            return (
                                <Link
                                    key={link.to}
                                    to={link.to}
                                    onClick={() => navigate(link.to)}
                                    className={`rounded-md px-3 py-2 text-sm font-medium transition ${
                                        isActive
                                            ? 'bg-accent text-[#FDFDFC]'
                                            : 'text-text hover:bg-elevated'
                                    }`}
                                    aria-current={isActive ? 'page' : undefined}
                                >
                                    {link.label}
                                </Link>
                            );
                        })}
                    </nav>

                    <div className="ml-auto flex items-center gap-2">
                        {user && (
                            <span className="hidden sm:inline-flex items-center gap-1.5 text-xs text-muted">
                                <span className="font-medium text-text">{user.name}</span>
                                <span className="rounded-full bg-elevated px-2 py-0.5">{user.role}</span>
                            </span>
                        )}
                        {user && (
                            <button
                                type="button"
                                onClick={logout}
                                className="rounded-md border border-border bg-surface px-3 py-2 text-xs font-medium text-muted hover:bg-elevated transition"
                            >
                                Đăng xuất
                            </button>
                        )}
                        <Link
                            to="/admin/evil"
                            className="hidden sm:inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-2 text-xs font-medium text-muted hover:bg-elevated transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                        >
                            Demo admin xấu
                        </Link>
                    </div>
                </div>
            </div>
        </header>
    );
}
