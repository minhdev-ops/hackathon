import { api } from './client';

export async function listDocuments() {
    const res = await api.get('/documents');
    return res.data.data;
}

export async function uploadDocument(formData) {
    const res = await api.post('/documents', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return res.data.data;
}

export async function downloadDocument(id) {
    const res = await api.get(`/documents/${id}/ciphertext`, {
        responseType: 'blob',
    });
    return res.data;
}

export async function deleteDocument(id) {
    await api.delete(`/documents/${id}`);
}
