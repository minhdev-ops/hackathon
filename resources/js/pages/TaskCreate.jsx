import { useCallback, useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import PageLayout from '../components/layout/PageLayout';
import { Button } from '../components/ui/Button';
import { Input, Label, Select, Textarea } from '../components/ui/Input';
import { SkeletonLine } from '../components/ui/Skeleton';
import { useToast } from '../components/ui/Toast';
import { listPlaybooks } from '../api/playbooks';
import { createTask } from '../api/tasks';

export default function TaskCreate() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const { toast } = useToast();
    const [playbooks, setPlaybooks] = useState([]);
    const [selectedSlug, setSelectedSlug] = useState(searchParams.get('playbook') ?? '');
    const [instruction, setInstruction] = useState('');
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);

    const load = useCallback(async () => {
        try {
            const data = await listPlaybooks();
            setPlaybooks(data);
            if (!selectedSlug && data.length > 0) {
                setSelectedSlug(data[0].slug);
            }
        } catch (err) {
            setError(err?.message || 'Không tải được danh sách playbook.');
            toast({
                title: 'Lỗi',
                description: err?.message || 'Không tải được danh sách playbook.',
                type: 'danger',
            });
        } finally {
            setLoading(false);
        }
    }, [selectedSlug, toast]);

    useEffect(() => {
        load();
    }, [load]);

    const handleSubmit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setError(null);
        if (!selectedSlug || !instruction.trim()) {
            setError('Vui lòng chọn playbook và nhập chỉ dẫn.');
            setSubmitting(false);
            return;
        }
        try {
            const task = await createTask({ playbook_slug: selectedSlug, instruction });
            toast({
                title: 'Tạo task thành công',
                description: 'Agent đang đề xuất các tài liệu cần thiết.',
                type: 'success',
            });
            navigate(`/tasks/${task.id}`);
        } catch (err) {
            setError(err?.message || 'Tạo task thất bại.');
            toast({
                title: 'Tạo task thất bại',
                description: err?.message || 'Không thể tạo task.',
                type: 'danger',
            });
        } finally {
            setSubmitting(false);
        }
    };

    if (loading) {
        return (
            <PageLayout title="Tạo task mới" subtitle="Chọn playbook và nhập chỉ dẫn cho agent thực hiện." showHomeLink={false}>
                <div className="flex flex-col items-center justify-center py-12">
                    <SkeletonLine count={3} className="w-72" />
                    <p className="mt-4 text-sm text-muted">Đang tải playbook…</p>
                </div>
            </PageLayout>
        );
    }

    const selectedPlaybook = playbooks.find((p) => p.slug === selectedSlug);

    return (
        <PageLayout title="Tạo task mới" subtitle="Chọn playbook và nhập chỉ dẫn cho agent thực hiện." showHomeLink={false}>
            {error && (
                <div className="rounded-lg border border-danger/20 bg-danger/5 p-4 text-sm text-danger" role="alert">
                    {error}
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-5 max-w-2xl">
                <div>
                    <Label htmlFor="task-playbook">Chọn playbook</Label>
                    <Select
                        id="task-playbook"
                        value={selectedSlug}
                        onChange={(event) => setSelectedSlug(event.target.value)}
                        required
                    >
                        {playbooks.map((pb) => (
                            <option key={pb.id} value={pb.slug}>
                                {pb.name} · {pb.recipient_label}
                            </option>
                        ))}
                    </Select>
                    {selectedPlaybook && (
                        <p className="mt-1.5 text-xs text-muted">{selectedPlaybook.description}</p>
                    )}
                </div>

                <div>
                    <Label htmlFor="task-instruction">Chỉ dẫn cho agent</Label>
                    <Textarea
                        id="task-instruction"
                        value={instruction}
                        onChange={(event) => setInstruction(event.target.value)}
                        required
                        rows={4}
                        placeholder="Ví dụ: Nộp hồ sơ thuê căn hộ A"
                    />
                    <p className="mt-1.5 text-xs text-muted">
                        Chỉ dẫn tự do, agent sẽ đề xuất các loại tài liệu cần thiết theo playbook.
                    </p>
                </div>

                <div className="pt-4 flex gap-3">
                    <Button type="submit" disabled={submitting} loading={submitting}>
                        Tạo task
                    </Button>
                    <Button
                        variant="secondary"
                        type="button"
                        onClick={() => navigate('/playbooks')}
                    >
                        Hủy
                    </Button>
                </div>
            </form>
        </PageLayout>
    );
}
