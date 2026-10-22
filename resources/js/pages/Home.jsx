import { Link, useNavigate } from 'react-router-dom';

const ACTIONS = [
    {
        to: '/vault',
        label: 'Két tài liệu',
        desc: 'Tải lên, mã hoá và quản lý giấy tờ nhạy cảm trong két.',
        featured: true,
        icon: 'M5 19a2 2 0 01-2-2V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v10a2 2 0 01-2-2H7zm0 0H5a2 2 0 01-2-2v-2a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2zM9 14a1 1 0 100-2 1 1 0 000 2zM9 10a1 1 0 100-2 1 1 0 000 2zM9 6a1 1 0 100-2 1 1 0 000 2z',
    },
    {
        to: '/playbooks',
        label: 'Chọn playbook',
        desc: 'Xem lĩnh vực và giao việc cho agent thực hiện.',
        featured: false,
        icon: 'M12 2L2 7l10 5 10-5-10-5zm0 9l9-5H3l9 5z',
    },
    {
        to: '/agent-console',
        label: 'Agent Console',
        desc: 'Theo dõi log agent và kiểm tra quy tắc phạm vi.',
        featured: false,
        icon: 'M19 4H5c-1.1 0-2 .9-2 2V18c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H6v-3.514c0-1.934 1.567-3.514 3.5-3.514S13 12.552 13 14.486V20h10v-3.514c0-1.934 1.567-3.514 3.5-3.514S22 15.552 22 17.486V6c0-.552-.211-1.057-.59-1.438z',
    },
    {
        to: '/timeline',
        label: 'Nhật ký hoạt động',
        desc: 'Xem chuỗi sự kiện append-only và xác minh hash-chain.',
        featured: false,
        icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4m8 0V9m0 0l-4-4m4 4l4 4m-8 11v-6m0 0l4 4m-4-4l-4-4m8 11V5m0 0l4 4m-4-4l-4-4m0 0m0 6l-4 4m4-4l4-4',
    },
    {
        to: '/admin/evil',
        label: 'Demo «Làm admin xấu»',
        desc: 'Kịch bản thử thách bảo mật (chỉ khi bật demo).',
        featured: false,
        icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
];

export default function Home() {
    const navigate = useNavigate();

    return (
        <div className="min-h-screen bg-bg text-text">
            <header className="mb-12 text-center max-w-2xl mx-auto px-4 lg:px-8">
                <div className="inline-flex items-center gap-2 rounded-lg border border-accent/20 bg-accent/5 px-3 py-1 text-xs font-semibold text-accent uppercase tracking-wider mb-6">
                    TrustGate · Consent Broker
                </div>
                <h1 className="text-3xl md:text-4xl font-bold tracking-tight mb-4">
                    Két dữ liệu và Consent Broker cho AI Agent
                </h1>
                <p className="text-lg md:text-xl text-muted leading-relaxed mb-8">
                    Agent chỉ nhận đúng phần được duyệt, cho đúng mục đích, trong thời hạn nhất định, và quyền này
                    thu hồi được. Mọi thay đổi quản trị đều cần quorum admin duyệt.
                </p>
                <div className="flex flex-col sm:flex-row items-center justify-center gap-3">
                    <Link
                        to="/vault"
                        onClick={() => navigate('/vault')}
                        className="w-full sm:w-auto bg-accent text-[#FDFDFC] px-6 py-3 text-sm font-semibold rounded-lg shadow-sm hover:bg-accent-hover hover:shadow hover:-translate-y-0.5 transition-all"
                    >
                        Bắt đầu với Két tài liệu
                    </Link>
                    <Link
                        to="/playbooks"
                        onClick={() => navigate('/playbooks')}
                        className="w-full sm:w-auto border border-border text-text px-6 py-3 text-sm font-semibold rounded-lg bg-surface hover:bg-elevated hover:shadow hover:-translate-y-0.5 transition-all"
                    >
                        Xem Playbook
                    </Link>
                </div>
            </header>

            <section className="max-w-5xl mx-auto px-4 lg:px-8">
                <h2 className="text-lg font-semibold text-text mb-6">Bắt đầu từ</h2>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {ACTIONS[0] && (
                        <Link
                            to={ACTIONS[0].to}
                            onClick={() => navigate(ACTIONS[0].to)}
                            className="col-span-1 lg:col-span-2 rounded-xl border border-border bg-surface p-6 hover:shadow-xl hover:border-accent/50 hover:bg-elevated transition-all group"
                            aria-label={ACTIONS[0].label}
                        >
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg border border-accent bg-accent text-[#FDFDFC] mb-4 group-hover:bg-accent-hover transition-all">
                                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={ACTIONS[0].icon} />
                                </svg>
                            </div>
                            <h3 className="font-semibold text-text">{ACTIONS[0].label}</h3>
                            <p className="mt-1 text-sm text-muted">{ACTIONS[0].desc}</p>
                            <div className="mt-4 flex items-center gap-1.5">
                                <span className="w-px h-3 bg-accent rounded-full" />
                                <span className="text-xs font-semibold text-accent uppercase tracking-wider">Bắt đầu từ đây</span>
                            </div>
                        </Link>
                    )}

                    {ACTIONS.slice(1).map((action) => (
                        <Link
                            key={action.to}
                            to={action.to}
                            onClick={() => navigate(action.to)}
                            className="rounded-xl border border-border bg-surface p-6 hover:shadow-lg hover:border-accent/50 hover:bg-elevated transition-all group"
                            aria-label={action.label}
                        >
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg border border-border bg-elevated mb-3 group-hover:border-accent/50 transition-all">
                                <svg className="h-5 w-5 text-muted group-hover:text-accent transition-all" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={action.icon} />
                                </svg>
                            </div>
                            <h3 className="font-semibold text-text">{action.label}</h3>
                            <p className="mt-1 text-sm text-muted">{action.desc}</p>
                        </Link>
                    ))}
                </div>
            </section>

            <footer className="mt-16 pt-8 border-t border-border text-center px-4 lg:px-8">
                <p className="text-xs text-subtle">
                    Prototype hackathon · 2 playbook: Thuê nhà, Khám bệnh · Chế độ mã hoá fallback (WebCrypto)
                </p>
            </footer>
        </div>
    );
}
