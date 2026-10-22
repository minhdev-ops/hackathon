import { api } from './client';

export async function showGrant(id) {
    const res = await api.get(`/grants/${id}`);
    return res.data.data;
}

export async function approveGrant(id, { doc_ids, ttl_minutes, grant_key_b64, bundle_iv, bundle }) {
    const form = new FormData();
    for (const docId of doc_ids) {
        form.append('doc_ids[]', docId);
    }
    form.append('ttl_minutes', String(ttl_minutes));
    form.append('grant_key_b64', grant_key_b64);
    form.append('bundle_iv', bundle_iv);
    form.append('bundle', bundle, 'bundle.bin');

    const res = await api.post(`/grants/${id}/approve`, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return res.data.data;
}

export async function denyGrant(id, reason) {
    const res = await api.post(`/grants/${id}/deny`, { reason });
    return res.data.data;
}

export async function revokeGrant(id, reason) {
    const res = await api.post(`/grants/${id}/revoke`, { reason });
    return res.data.data;
}

export const STATUS_LABELS = {
    pending: 'Chờ duyệt',
    active: 'Đang hiệu lực',
    revoked: 'Đã thu hồi',
    expired: 'Đã hết hạn',
    denied: 'Đã từ chối',
};
