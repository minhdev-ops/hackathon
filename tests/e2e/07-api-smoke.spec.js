import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

test.describe('API và tích hợp TideCloak', () => {
    test('API yêu cầu đăng nhập', async ({ request }) => {
        const response = await request.get('/api/v1/me', {
            headers: { Accept: 'application/json' },
        });

        expect(response.status()).toBe(401);
    });

    test('TideCloak realm trả discovery document', async ({ request }) => {
        const response = await request.get(
            'http://localhost:8080/realms/myrealm/.well-known/openid-configuration',
        );

        expect(response.status()).toBe(200);
        const body = await response.json();
        expect(body.issuer).toBe('http://localhost:8080/realms/myrealm');
        expect(body.jwks_uri).toContain('/realms/myrealm/protocol/openid-connect/certs');
    });

    test('owner đọc được me, playbooks, documents, tasks, audit', async ({ page }) => {
        await login(page, 'lan@example.com', '/');

        const me = await page.request.get('/api/v1/me');
        expect(me.status()).toBe(200);
        expect((await me.json()).data.email).toBe('lan@example.com');

        const playbooks = await page.request.get('/api/v1/playbooks');
        expect(playbooks.status()).toBe(200);
        expect((await playbooks.json()).data.length).toBeGreaterThanOrEqual(2);

        const documents = await page.request.get('/api/v1/documents');
        expect(documents.status()).toBe(200);
        expect((await documents.json()).data.length).toBeGreaterThanOrEqual(5);

        const tasks = await page.request.get('/api/v1/tasks');
        expect(tasks.status()).toBe(200);
        expect((await tasks.json()).data.length).toBeGreaterThanOrEqual(1);

        const audit = await page.request.get('/api/v1/audit');
        expect(audit.status()).toBe(200);
        expect((await audit.json()).data.length).toBeGreaterThan(0);

        const verify = await page.request.get('/api/v1/audit/verify');
        expect(verify.status()).toBe(200);
        expect((await verify.json()).data.valid).toBe(true);
    });

    test('admin đọc meta quorum và bị chặn đổi trực tiếp', async ({ page }) => {
        await login(page, 'admin1@example.com', '/admin/quorum');

        const meta = await page.request.get('/api/v1/admin/quorum/meta');
        expect(meta.status()).toBe(200);
        expect((await meta.json()).data.threshold).toBeGreaterThanOrEqual(1);

        const direct = await page.request.patch('/api/v1/admin/direct/quorum-threshold', {
            data: { required_approvals: 1 },
        });
        expect(direct.status()).toBe(409);
        expect((await direct.json()).error.code).toBe('QUORUM_REQUIRED');
    });

    test('agent xin tài liệu ngoài phạm vi bị 403 OUT_OF_SCOPE', async ({ page }) => {
        await login(page, 'agent@example.com', '/');

        const response = await page.request.post('/api/v1/agent/documents/request', {
            data: { doc_type: 'medical_summary' },
        });

        expect(response.status()).toBe(403);
        expect((await response.json()).error.code).toBe('OUT_OF_SCOPE');
    });
});
