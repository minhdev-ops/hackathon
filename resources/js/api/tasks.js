import { api } from './client';

export async function listTasks() {
    const res = await api.get('/tasks');
    return res.data.data;
}

export async function showTask(id) {
    const res = await api.get(`/tasks/${id}`);
    return res.data.data;
}

export async function createTask(payload) {
    const res = await api.post('/tasks', payload);
    return res.data.data;
}

export const STATUS_LABELS = {
    proposing: 'Đang đề xuất',
    proposal_ready: 'Đề xuất sẵn sàng',
    awaiting_approval: 'Chờ duyệt grant',
    running: 'Agent đang chạy',
    done: 'Hoàn tất',
    failed_revoked: 'Bị thu hồi giữa chừng',
    denied: 'Đã từ chối',
};
