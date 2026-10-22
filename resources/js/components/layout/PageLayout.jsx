import { Link, useNavigate } from 'react-router-dom';

export default function PageLayout({ title, subtitle, children, showHomeLink = true }) {
    const navigate = useNavigate();

    return (
        <div className="min-h-screen bg-bg text-text p-4 lg:p-8">
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:bg-accent focus:text-[#FDFDFC] focus:px-4 focus:py-2 focus:rounded-md"
            >
                Bỏ qua nội dung chính
            </a>
            <div className="max-w-5xl mx-auto" id="main-content">
                <header className="mb-8 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                        {subtitle && (
                            <p className="mt-1 text-sm text-muted">{subtitle}</p>
                        )}
                    </div>
                    {showHomeLink && (
                        <Link
                            to="/"
                            onClick={() => navigate('/')}
                            className="mt-2 lg:mt-0 inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-2 text-xs font-medium text-muted hover:bg-elevated transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                        >
                            ← Trang chủ
                        </Link>
                    )}
                </header>

                <main className="space-y-8">{children}</main>
            </div>
        </div>
    );
}
