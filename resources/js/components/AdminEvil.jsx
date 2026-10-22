import { useState, useEffect, useRef } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { api } from '../api/client';

const btn = 'px-4 py-2 rounded border text-sm font-medium transition';
const btnPrimary = `${btn} bg-[#1b1b18] text-[#FDFDFC] border-transparent hover:bg-[#3a3a38]`;
const btnOutline = `${btn} border-[#1b1b18] text-[#1b1b18] hover:bg-[#f3f3f2]`;
const btnDanger = `${btn} border-[#b91c1c] text-[#b91c1c] bg-[#fef2f2] hover:bg-[#fecaca]`;
const card = 'p-4 rounded-lg border bg-[#FDFDFC] shadow-sm';
const code = 'bg-[#f3f3f2] border border-[#e3e3e0] rounded p-2 text-xs overflow-x-auto font-mono text-[#8a6d1d]';
const row = 'flex items-center gap-2 mb-1 text-sm';

function logEvent(entry) {
    return entry.event ?? entry.step ?? 'event';
}

function logContext(entry) {
    if (entry.context && typeof entry.context === 'object') {
        return Object.keys(entry.context).length > 0 ? JSON.stringify(entry.context) : null;
    }

    const { event, step, at, ...context } = entry;

    return Object.keys(context).length > 0 ? JSON.stringify(context) : null;
}

export default function AdminEvil() {
    const navigate = useNavigate();
    const [dumpResult, setDumpResult] = useState(null);
    const [dumpError, setDumpError] = useState(null);
    const [elevateResult, setElevateResult] = useState(null);
    const [elevateError, setElevateError] = useState(null);
    const [agentResult, setAgentResult] = useState(null);
    const [agentError, setAgentError] = useState(null);
    const [slowRunning, setSlowRunning] = useState(false);
    const [slowResult, setSlowResult] = useState(null);
    const [slowError, setSlowError] = useState(null);
    const [slowLog, setSlowLog] = useState([]);
    const [slowStatus, setSlowStatus] = useState(null);
    const [pollStop, setPollStop] = useState(false);
    const [resetResult, setResetResult] = useState(null);
    const [resetError, setResetError] = useState(null);
    const [loading, setLoading] = useState({});
    const pollRef = useRef(null);

    const setLoadingState = (key, value) => setLoading((prev) => ({ ...prev, [key]: value }));

    const handleDumpDb = async () => {
        setDumpResult(null);
        setDumpError(null);
        setLoadingState('dump', true);
        try {
            const res = await api.post(`/demo/evil/dump-db`);
            setDumpResult(res.data.data);
        } catch (err) {
            setDumpError(err.response?.data?.error || err.message);
        } finally {
            setLoadingState('dump', false);
        }
    };

    const handleSelfElevate = async () => {
        setElevateResult(null);
        setElevateError(null);
        setLoadingState('elevate', true);
        try {
            const res = await api.post(`/demo/evil/self-elevate`);
            setElevateResult(res.data.data);
        } catch (err) {
            setElevateError(err.response?.data?.error || err.message);
        } finally {
            setLoadingState('elevate', false);
        }
    };

    const handleAgentOutOfScope = async () => {
        setAgentResult(null);
        setAgentError(null);
        setLoadingState('agent', true);
        try {
            await api.post('/demo/evil/out-of-scope');
        } catch (err) {
            // Giữ nguyên object lỗi để hiển thị mã OUT_OF_SCOPE (FR-DEMO-03).
            const error = err.response?.data?.error || { message: err.message };
            setAgentResult(error);
            setAgentError(error);
        } finally {
            setLoadingState('agent', false);
        }
    };

    const handleSlowRun = async () => {
        setSlowResult(null);
        setSlowError(null);
        setSlowLog([]);
        setSlowStatus(null);
        setPollStop(false);
        if (pollRef.current) clearInterval(pollRef.current);
        setSlowRunning(true);
        setLoadingState('slow', true);
        try {
            const res = await api.post(`/demo/evil/slow-run`);
            setSlowResult(res.data.data);
            setSlowStatus(res.data.data.status);
            startPolling(res.data.data.task_id);
        } catch (err) {
            setSlowError(err.response?.data?.error || err.message);
            setSlowRunning(false);
        } finally {
            setLoadingState('slow', false);
        }
    };

    const startPolling = (taskId) => {
        const fetchLog = async () => {
            try {
                const res = await api.get(`/demo/evil/slow-run-log/${taskId}`);
                setSlowLog(res.data.data.log ?? []);
                setSlowStatus(res.data.data.status);
                if (res.data.data.status !== 'running' && res.data.data.status !== null) {
                    // Job ghi dòng `run.stopped`/`slow_run.stopped` SAU khi đổi
                    // trạng thái → poll thêm 2 lần nữa để không bỏ dòng cuối.
                    clearInterval(pollRef.current);
                    pollRef.current = setInterval(fetchLog, 1200);
                    setTimeout(() => {
                        clearInterval(pollRef.current);
                        setPollStop(true);
                        setSlowRunning(false);
                    }, 3000);
                }
            } catch (err) {
                // ignore poll errors until task settles
            }
        };
        fetchLog();
        pollRef.current = setInterval(fetchLog, 1000);
        // Auto-stop after ~25 seconds (demo job runs ~12s).
        setTimeout(() => {
            setPollStop(true);
            setSlowRunning(false);
            if (pollRef.current) clearInterval(pollRef.current);
        }, 25000);
    };

    const handleReset = async () => {
        setResetResult(null);
        setResetError(null);
        setLoadingState('reset', true);
        try {
            const res = await api.post(`/demo/reset`);
            setResetResult(res.data.data);
        } catch (err) {
            setResetError(err.response?.data?.error || err.message);
        } finally {
            setLoadingState('reset', false);
        }
    };

    useEffect(() => {
        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, []);

    return (
        <div className="min-h-screen bg-[#FDFDFC] text-[#1b1b18] p-4 lg:p-8">
            <div className="max-w-5xl mx-auto">
                <div className="mb-8 flex items-center gap-2">
                    <span className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-[#fef2f2] text-[#b91c1c] border border-[#fecaca]">!</span>
                    <div>
                        <h1 className="text-xl font-bold">Làm admin xấu</h1>
                        <p className="text-[#706f6c] text-sm">
                            Chỉ bật khi <code className="bg-[#f3f3f2] px-1 py-0.5 rounded">APP_DEMO=true</code>. Mục đích demo: chứng minh dữ liệu chỉ
                            tồn tại ở dạng bản mã và admin xấu không thể leo thang quyền một mình.
                        </p>
                    </div>
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                    <button onClick={handleDumpDb} disabled={loading.dump} className={btnOutline}>
                        {loading.dump ? 'Đang đổ...' : '1. Đổ database'}
                    </button>
                    <button onClick={handleSelfElevate} disabled={loading.elevate} className={btnOutline}>
                        {loading.elevate ? 'Đang tạo CR...' : '2. Tự cấp quyền'}
                    </button>
                    <button onClick={handleAgentOutOfScope} disabled={loading.agent} className={btnOutline}>
                        {loading.agent ? 'Đang gọi...' : '3. Agent xin ngoài phạm vi'}
                    </button>
                    <button onClick={handleSlowRun} disabled={loading.slow || slowRunning} className={btnOutline}>
                        {slowRunning ? 'Agent đang chạy...' : '4. Thu hồi giữa chừng'}
                    </button>
                    <button onClick={handleReset} disabled={loading.reset} className={btnDanger}>
                        {loading.reset ? 'Đang reset...' : '5. Đặt lại dữ liệu demo'}
                    </button>
                </div>

                {(dumpResult || dumpError) && (
                    <section className={`mt-6 ${card}`}>
                        <h2 className="font-semibold mb-3">Kết quả: Đổ database (FR-DEMO-01)</h2>
                        {dumpError && (
                            <div className="text-[#b91c1c] text-sm mb-3">{dumpError.message ?? dumpError}</div>
                        )}
                        {dumpResult && (
                            <>
                                <p className="text-xs text-[#706f6c] mb-3">{dumpResult.note}</p>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <h3 className="text-sm font-bold mb-2">documents</h3>
                                        {Array.isArray(dumpResult.documents) ? (
                                            <ul className="space-y-1">
                                                {dumpResult.documents.slice(0, 8).map((d) => (
                                                    <li key={d.id} className={row}>
                                                        <span className="text-[#1b1b18] font-mono text-xs">{d.doc_type}</span>
                                                        <span className="ml-2 text-[#706f6c] text-xs">title_ct={String(d.title_ct).slice(0, 24)}…</span>
                                                        <span className="ml-2 text-[#706f6c] text-xs">iv={d.iv}</span>
                                                        <span className="ml-2 text-[#706f6c] text-xs">cipher_b64={String(d.ciphertext_b64 ?? '').slice(0, 24)}…</span>
                                                    </li>
                                                ))}
                                                {dumpResult.documents.length > 8 && <li className="text-[#706f6c] text-xs">… và {dumpResult.documents.length - 8} dòng khác</li>}
                                            </ul>
                                        ) : <div className="text-[#706f6c] text-xs">Không có dữ liệu.</div>}
                                    </div>
                                    <div>
                                        <h3 className="text-sm font-bold mb-2">grants</h3>
                                        {Array.isArray(dumpResult.grants) ? (
                                            <ul className="space-y-1">
                                                {dumpResult.grants.slice(0, 8).map((g) => (
                                                    <li key={g.id} className={row}>
                                                        <span className="font-mono text-xs">{g.status}</span>
                                                        <span className="ml-2 text-[#706f6c] text-xs">expires_at={g.expires_at}</span>
                                                        <span className="ml-2 text-[#706f6c] text-xs">bundle_iv={g.bundle_iv}</span>
                                                    </li>
                                                ))}
                                                {dumpResult.grants.length > 8 && <li className="text-[#706f6c] text-xs">… và {dumpResult.grants.length - 8} dòng khác</li>}
                                            </ul>
                                        ) : <div className="text-[#706f6c] text-xs">Không có dữ liệu.</div>}
                                    </div>
                                </div>
                                <div className={`mt-3 ${code}`}>
                                    {JSON.stringify(dumpResult, null, 2).slice(0, 1500)}
                                </div>
                            </>
                        )}
                    </section>
                )}

                {(elevateResult || elevateError) && (
                    <section className={`mt-6 ${card}`}>
                        <h2 className="font-semibold mb-3">Kết quả: Tự cấp quyền (FR-DEMO-02)</h2>
                        {elevateError && <div className="text-[#b91c1c] text-sm">{elevateError.message ?? elevateError}</div>}
                        {elevateResult && (
                            <div className="space-y-2">
                                <div className={row}><span className="font-mono text-xs">CR ID:</span> <span className="ml-2">{elevateResult.change_request_id}</span></div>
                                <div className={row}><span className="font-mono text-xs">Loại:</span> <span className="ml-2">{elevateResult.type}</span></div>
                                <div className={row}><span className="font-mono text-xs">Trạng thái:</span> <span className="ml-2 font-semibold text-[#b91c1c]">{elevateResult.status}</span></div>
                                <div className={row}><span className="font-mono text-xs">Quorum:</span> <span className="ml-2 font-semibold">{elevateResult.approvals}</span></div>
                                <div className="mt-2 p-3 bg-[#fef2f2] border border-[#fecaca] rounded text-[#b91c1c] text-sm">
                                    {elevateResult.message}
                                </div>
                            </div>
                        )}
                    </section>
                )}

                {(agentResult || agentError) && (
                    <section className={`mt-6 ${card}`}>
                        <h2 className="font-semibold mb-3">Kết quả: Agent xin ngoài phạm vi (FR-DEMO-03)</h2>
                        {agentError && (
                            <div className="p-3 bg-[#fef2f2] border border-[#fecaca] rounded text-[#b91c1c] text-sm font-mono">
                                {agentError.code}: {agentError.message}
                            </div>
                        )}
                        {agentResult && !agentError && (
                            <div className="text-[#15803d] text-sm">Không có lỗi (không mong muốn).</div>
                        )}
                    </section>
                )}

                {(slowResult || slowError || slowLog.length > 0) && (
                    <section className={`mt-6 ${card}`}>
                        <h2 className="font-semibold mb-3">Kết quả: Thu hồi giữa chừng (FR-DEMO-04)</h2>
                        {slowError && <div className="text-[#b91c1c] text-sm mb-3">{slowError.message ?? slowError}</div>}
                        {slowResult && (
                            <div className="space-y-2 mb-3">
                                <div className={row}><span className="font-mono text-xs">Task ID:</span> <span className="ml-2">{slowResult.task_id}</span></div>
                                <div className={row}><span className="font-mono text-xs">Grant ID:</span> <span className="ml-2">{slowResult.grant_id}</span></div>
                                <div className={row}><span className="font-mono text-xs">Trạng thái:</span> <span className="ml-2 font-semibold">{slowStatus}</span></div>
                                <p className="text-[#706f6c] text-xs">{slowResult.message}</p>
                            </div>
                        )}
                        <div className="bg-[#f3f3f2] border border-[#e3e3e0] rounded p-3 max-h-60 overflow-y-auto font-mono text-xs">
                            {slowLog.length === 0 ? (
                                <span className="text-[#706f6c]">Chưa có log. Bấm «Thu hồi giữa chừng» để bắt đầu, sau đó (là Owner) bấm Thu hồi grant để thấy agent hỏng ngay.</span>
                            ) : (
                                slowLog.map((entry, i) => {
                                    const context = logContext(entry);

                                    return (
                                        <div key={i} className="mb-1">
                                            <span className="text-[#706f6c]">{entry.at}</span>{' '}
                                            <span className="font-semibold">{logEvent(entry)}</span>{' '}
                                            {context && (
                                                <span className="ml-1 text-[#706f6c]">({context})</span>
                                            )}
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </section>
                )}

                {(resetResult || resetError) && (
                    <section className={`mt-6 ${card}`}>
                        <h2 className="font-semibold mb-3">Kết quả: Đặt lại dữ liệu demo (FR-DEMO-05)</h2>
                        {resetError && <div className="text-[#b91c1c] text-sm">{resetError.message ?? resetError}</div>}
                        {resetResult && (
                            <div className="p-3 bg-[#f0fdf4] border border-[#86efac] rounded text-[#15803d] text-sm">
                                {resetResult.message} ({resetResult.seeder})
                            </div>
                        )}
                    </section>
                )}

                <p className="mt-8 text-xs text-[#706f6c]">
                    Lưu ý: các route /demo/* trả 404 khi <code>APP_DEMO</code> không phải true. Cần đăng nhập với tư cách admin để gọi.
                </p>
                <div className="mt-4">
                    <Link to="/" className={btnOutline}>← Trở về trang chủ</Link>
                </div>
            </div>
        </div>
    );
}
