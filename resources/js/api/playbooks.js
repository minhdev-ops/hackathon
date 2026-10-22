import { api } from './client';

export async function listPlaybooks() {
    const res = await api.get('/playbooks');
    return res.data.data;
}
