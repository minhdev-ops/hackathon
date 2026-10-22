import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import PageLayout from '../components/layout/PageLayout';
import { Button } from '../components/ui/Button';
import { Input, Label, Select } from '../components/ui/Input';
import { SkeletonLine } from '../components/ui/Skeleton';
import { ConfirmDialog } from '../components/ui/ConfirmDialog';
import { useToast } from '../components/ui/Toast';
import {
    listDocuments,
    uploadDocument,
    downloadDocument,
    deleteDocument,
} from '../api/documents';
import { createCryptoProvider } from '../crypto/provider';

const ENC_MODE = import.meta.env.VITE_ENC_MODE ?? 'fallback';
const cryptoProvider = createCryptoProvider(ENC_MODE);

const DOC_TYPES = [
    'id_card',
    'income_proof',
    'rental_history',
    'reference_letter',
    'insurance_card',
    'medical_summary',
    'lab_result',
];

const DOC_LABELS = {
    id_card: 'Căn cước / CMND',
    income_proof: 'Chứng minh thu nhập',
    rental_history: 'Lịch sử thuê nhà',
    reference_letter: 'Thư giới thiệu',
    insurance_card: 'Thẻ bảo hiểm',
    medical_summary: 'Tóm tắt bệnh án',
    lab_result: 'Kết quả xét nghiệm',
};

export default function Vault() {
    const navigate = useNavigate();
    const { toast } = useToast();
    const [documents, setDocuments] = useState([]);
    const [loading, setLoading] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [openUpload, setOpenUpload] = useState(false);
    const [deletingId, setDeletingId] = useState(null);
    const [error, setError] = useState(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const docs = await listDocuments();
            setDocuments(docs);
        } catch (err) {
            setError(err?.message || 'Không tải được danh sách tài liệu.');
            toast({
                title: 'Lỗi',
                description: err?.message || 'Không tải được danh sách tài liệu.',
                type: 'danger',
            });
        } finally {
            setLoading(false);
        }
    }, [toast]);

    useEffect(() => {
        load();
    }, [load]);

    const handleUpload = async (event) => {
        event.preventDefault();
        setUploading(true);
        setError(null);

        const formData = new FormData(event.currentTarget);

        try {
            const file = formData.get('file');
            if (!file) {
                throw new Error('Vui lòng chọn file.');
            }
            const arrayBuffer = await file.arrayBuffer();

            const { ciphertext, iv, sha256 } = await cryptoProvider.encryptDocument(arrayBuffer);
            const titleCt = await cryptoProvider.encryptText(String(formData.get('title')));

            const encryptedFile = new File([ciphertext], file.name, { type: file.type });

            formData.set('file', encryptedFile);
            formData.set('iv', btoa(String.fromCharCode(...iv)));
            formData.set('sha256', sha256);
            formData.set('title_ct', titleCt);
            formData.set('enc_mode', ENC_MODE);

            await uploadDocument(formData);
            setOpenUpload(false);
            event.currentTarget.reset();
            await load();
            toast({
                title: 'Tải lên thành công',
                description: 'Tài liệu đã được mã hoá và lưu vào két.',
                type: 'success',
            });
        } catch (err) {
            setError(err?.message || 'Tải lên thất bại.');
            toast({
                title: 'Tải lên thất bại',
                description: err?.message || 'Không thể tải lên tài liệu.',
                type: 'danger',
            });
        } finally {
            setUploading(false);
        }
    };

    const handleDownload = async (doc) => {
        setError(null);
        try {
            const blob = await downloadDocument(doc.id);
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `document-${doc.doc_type}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(() => URL.revokeObjectURL(url), 60_000);
        } catch (err) {
            setError(err?.message || 'Tải xuống thất bại.');
            toast({
                title: 'Tải xuống thất bại',
                description: err?.message || 'Không thể tải bản mã về.',
                type: 'danger',
            });
        }
    };

    const handleDelete = async (id) => {
        setDeletingId(id);
        try {
            await deleteDocument(id);
            await load();
            toast({
                title: 'Đã xoá',
                description: 'Tài liệu đã bị xoá khỏi két.',
                type: 'success',
            });
        } catch (err) {
            setError(err?.message || 'Xoá thất bại.');
            toast({
                title: 'Xoá thất bại',
                description: err?.message || 'Không thể xoá tài liệu.',
                type: 'danger',
            });
        } finally {
            setDeletingId(null);
        }
    };

    return (
        <PageLayout
            title="Két tài liệu"
            subtitle="Tài liệu được mã hoá tại trình duyệt trước khi gửi lên server. Server chỉ lưu bản mã."
            showHomeLink={false}
        >
            {error && (
                <div className="rounded-lg border border-danger/20 bg-danger/5 p-4 text-sm text-danger" role="alert">
                    {error}
                </div>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3 mb-6">
                <Button variant="primary" onClick={() => setOpenUpload(true)}>
                    + Tải lên tài liệu
                </Button>
                <Link
                    to="/playbooks"
                    onClick={() => navigate('/playbooks')}
                    className="rounded-md border border-border bg-surface px-4 py-2 text-sm font-medium text-text hover:bg-elevated transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                >
                    Chọn playbook → Giao việc cho agent
                </Link>
            </div>

            {loading && (
                <div className="flex flex-col items-center justify-center py-12">
                    <SkeletonLine count={2} className="w-64" />
                    <p className="mt-4 text-sm text-muted">Đang tải kết…</p>
                </div>
            )}

            {!loading && documents.length === 0 && (
                <div className="rounded-xl border border-dashed border-border bg-surface p-16 text-center">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-border bg-elevated mb-4">
                        <svg className="h-8 w-8 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 className="text-sm font-semibold text-text">Chưa có tài liệu nào trong két.</h3>
                    <p className="mt-1 text-xs text-muted">Bấm «Tải lên tài liệu» để bắt đầu.</p>
                </div>
            )}

            {!loading && documents.length > 0 && (
                <div className="rounded-xl border border-border bg-surface overflow-hidden shadow-sm">
                    <table className="w-full text-left text-sm divide-y divide-border" role="grid" aria-label="Danh sách tài liệu trong két">
                        <thead>
                            <tr className="border-b border-border bg-elevated">
                                <th className="p-3 text-left text-xs font-semibold text-muted uppercase tracking-wider" scope="col">Loại tài liệu</th>
                                <th className="p-3 text-left text-xs font-semibold text-muted uppercase tracking-wider" scope="col">Tên (mã hoá)</th>
                                <th className="p-3 text-left text-xs font-semibold text-muted uppercase tracking-wider" scope="col">Kích thước</th>
                                <th className="p-3 text-left text-xs font-semibold text-muted uppercase tracking-wider" scope="col">Chế độ</th>
                                <th className="p-3 text-right text-xs font-semibold text-muted uppercase tracking-wider" scope="col">Hành động</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {documents.map((doc) => (
                                <tr key={doc.id} className="hover:bg-elevated transition" role="row">
                                    <td className="p-3 font-mono text-xs text-text" scope="row">{doc.doc_type}</td>
                                    <td className="p-3 font-mono text-xs text-muted truncate max-w-xs">{doc.title_ct.slice(0, 24)}…</td>
                                    <td className="p-3 text-muted">
                                        {(doc.size_bytes / 1024).toFixed(1)} KB
                                    </td>
                                    <td className="p-3 text-muted">{doc.enc_mode}</td>
                                    <td className="p-3 text-right">
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => handleDownload(doc)}
                                            className="mr-2 px-2.5 py-1.5 text-[11px] border-info/20 text-info hover:bg-info/5"
                                            aria-label={`Tải bản mã ${doc.doc_type}`}
                                        >
                                            Bản mã
                                        </Button>
                                        <Button
                                            variant="danger"
                                            size="sm"
                                            disabled={deletingId === doc.id}
                                            onClick={() => handleDelete(doc.id)}
                                            className="px-2.5 py-1.5 text-[11px]"
                                            aria-label={`Xoá ${doc.doc_type}`}
                                        >
                                            {deletingId === doc.id ? 'Đang xoá…' : 'Xoá'}
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {openUpload && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="upload-dialog-title"
                    onClick={(e) => {
                        if (e.target === e.currentTarget) setOpenUpload(false);
                    }}
                >
                    <div className="fixed inset-0 bg-black/40 backdrop-blur-sm" />
                    <div
                        className="relative bg-surface rounded-xl border border-border p-6 max-w-md w-full shadow-lg"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <h3 id="upload-dialog-title" className="text-base font-bold mb-4">
                            Tải lên tài liệu
                        </h3>
                        <form onSubmit={handleUpload} className="space-y-4">
                            <div>
                                <Label htmlFor="vault-file">File (PDF/PNG/JPG, tối đa 10 MB)</Label>
                                <input
                                    id="vault-file"
                                    type="file"
                                    name="file"
                                    required
                                    accept=".pdf,.png,.jpg,.jpeg"
                                    className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-accent focus:outline-none transition disabled:opacity-60"
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="vault-doc-type">Loại tài liệu</Label>
                                    <Select id="vault-doc-type" name="doc_type" required>
                                        {DOC_TYPES.map((t) => (
                                            <option key={t} value={t}>
                                                {DOC_LABELS[t] ?? t}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                                <div>
                                    <Label htmlFor="vault-title">Tên (đã mã hoá)</Label>
                                    <Input
                                        id="vault-title"
                                        name="title"
                                        type="text"
                                        required
                                        placeholder="Ví dụ: CMND Lan"
                                    />
                                </div>
                            </div>
                            <div className="flex gap-3">
                                <Button
                                    variant="secondary"
                                    type="button"
                                    onClick={() => setOpenUpload(false)}
                                    className="flex-1 px-3 py-2 text-sm"
                                >
                                    Hủy
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={uploading}
                                    loading={uploading}
                                    className="flex-1 px-3 py-2 text-sm font-medium"
                                >
                                    {uploading ? 'Đang mã hoá…' : 'Mã hoá và tải lên'}
                                </Button>
                            </div>
                        </form>
                        <p className="mt-3 text-xs text-muted">
                            Nội dung được mã hoá tại trình duyệt trước khi gửi. Server chỉ lưu bản mã.
                        </p>
                    </div>
                </div>
            )}

            {deletingId !== null && (
                <ConfirmDialog
                    isOpen={deletingId !== null}
                    title="Xác nhận xoá tài liệu"
                    description="Tài liệu sẽ bị xoá khỏi két và không thể khôi phục."
                    confirmLabel="Xoá"
                    cancelLabel="Hủy"
                    onConfirm={() => {
                        const doc = documents.find((d) => d.id === deletingId);
                        if (doc) handleDelete(doc.id);
                        setDeletingId(null);
                    }}
                    onCancel={() => setDeletingId(null)}
                />
            )}
        </PageLayout>
    );
}
