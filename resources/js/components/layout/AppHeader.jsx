import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';

const NAV_LINKS = [
    { to: '/', label: 'Trang chủ', roles: ['owner', 'admin', 'agent'] },
    { to: '/vault', label: 'Két tài liệu', roles: ['owner', 'admin'] },
    { to: '/playbooks', label: 'Playbook', roles: ['owner', 'admin'] },
    { to: '/agent-console', label: 'Agent Console', roles: ['owner', 'admin', 'agent'] },
    { to: '/timeline', label: 'Nhật ký', roles: ['owner', 'admin'] },
];

export default function AppHeader() {
    const { me, isAdmin, isOwner, isAgent, loading } = useAuth();
    const location = useLocation();
    const navigate = useNavigate();

    if (loading) {
        return (
            <header className="sticky top-0 z-40 border-b border-border bg-surface/85 backdrop-blur-md">
                <div className="max-w-5xl mx-auto px-4 lg:px-8 py-4">
                    <div className="flex items-center gap-2">
                        <span className="rounded-lg bg-accent text-[#FDFDFC] px-2.5 py-1 text-xs font-bold shadow-sm">
                            TG
                        </span>
                        <span className="text-lg font-bold tracking-tight">TrustGate</span>
                    </div>
                </div>
            </header>
        );
    }

    return (
        <header className="sticky top-0 z-40 border-b border-border bg-surface/90 backdrop-blur-md">
            <div className="max-w-5xl mx-auto px-4 lg:px-8">
                <div className="flex flex-wrap items-center justify-between gap-4 py-3 border-b border-border/30 pb-3">
                    <div className="flex items-center gap-2">
                        <Link
                            to="/"
                            onClick={() => navigate('/')}
                            className="flex items-center gap-2"
                            aria-label="Trang chủ TrustGate"
                        >
                            <span className="rounded-lg bg-accent text-[#FDFDFC] px-2.5 py-1 text-xs font-bold shadow-sm">
                                TG
                            </span>
                            <span className="text-lg font-bold tracking-tight">TrustGate</span>
                        </Link>
                        <span className="w-px h-5 bg-border mt-0.5" />
                        <span className={`text-xs font-semibold px-2 py-0.5 rounded-full border ${
                            isAdmin
                                ? 'border-accent text-accent bg-accent/10'
                                : isOwner
                                ? 'border-info text-info bg-info/10'
                                : 'border-muted text-muted bg-muted/10'
                        }`}>
                            {isAdmin ? 'ADMIN' : isOwner ? 'OWNER' : 'AGENT'}
                        </span>
                        <span className="text-xs text-muted">· {me?.email}</span>
                    </div>

                    <nav className="hidden md:flex flex-1 items-center gap-1 text-sm" aria-label="Chính đầu điều hướng">
                        {NAV_LINKS.filter((link) => link.roles.includes(isAdmin ? 'admin' : isOwner ? 'owner' : 'agent')).map((link) => {
                            const isActive = location.pathname === link.to || (link.to !== '/' && location.pathname.startsWith(link.to));
                            return (
                                <Link
                                    key={link.to}
                                    to={link.to}
                                    onClick={() => navigate(link.to)}
                                    className={`rounded-lg px-3 py-2 text-sm font-medium transition-all ${
                                        isActive
                                            ? 'bg-accent text-[#FDFDFC] shadow-sm'
                                            : 'text-text hover:bg-elevated hover:shadow'
                                    }`}
                                    aria-current={isActive ? 'page' : undefined}
                                >
                                    {link.label}
                                </Link>
                            );
                        })}
                    </nav>

                    <div className="ml-auto flex items-center gap-2">
                        <button
                            onClick={async () => {
                                await fetch('/logout', {
                                    method: 'POST',
                                    headers: {
                                        Accept: 'application/json',
                                        'X-XSRF-TOKEN': document.cookie
                                            .split('; ')
                                            .find((row) => row.startsWith('XSRF-TOKEN='))
                                            ?.split('=')[1] || '',
                                    },
                                });
                                window.location.href = '/login';
                            }}
                            className="rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs font-medium text-text hover:bg-elevated transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                        >
                            Đăng xuất
                        </button>
                    </div>
                </div>
            </div>
        </header>
    );
}
