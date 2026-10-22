import { test, expect } from '@playwright/test';
import { login, shot } from './helpers.js';

test.describe('Tạo việc và từ chối grant', () => {
    test('owner tạo task qua API rồi từ chối grant trên UI', async ({ page }) => {
        await login(page, 'lan@example.com', '/');

        const createResponse = await page.request.post('/api/v1/tasks', {
            data: {
                playbook_slug: 'rental',
                instruction: 'E2E từ chối grant',
            },
        });
        expect(createResponse.status()).toBe(201);

        const created = await createResponse.json();
        const taskId = created.data.id;
        expect(taskId).toBeTruthy();

        let grantId = null;
        await expect
            .poll(
                async () => {
                    const response = await page.request.get(`/api/v1/tasks/${taskId}`);
                    const body = await response.json();
                    grantId = body.data?.grant?.id ?? null;

                    return grantId;
                },
                { timeout: 30_000 },
            )
            .not.toBeNull();

        await page.reload();
        const taskCard = page.locator('article').filter({ hasText: 'E2E từ chối grant' });
        await expect(taskCard).toBeVisible();
        await taskCard.getByRole('link', { name: 'Mở màn duyệt' }).click();

        await expect(page.getByRole('heading', { name: 'Duyệt chia sẻ dữ liệu' })).toBeVisible();
        await expect(page).toHaveURL(new RegExp(`/grants/${grantId}$`));
        await shot(page, '27-consent-pending-deny');

        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Từ chối', exact: true }).click();
        await expect(page.getByText('Đã từ chối grant.')).toBeVisible();
        await shot(page, '28-grant-denied');
    });
});
