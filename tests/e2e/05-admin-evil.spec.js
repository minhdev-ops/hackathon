import { test, expect } from '@playwright/test';
import { login, shot } from './helpers.js';

test.describe('Demo admin xấu', () => {
    test('dump DB, tự cấp quyền, out-of-scope, slow-run và reset', async ({ page, browser }) => {
        await login(page, 'admin1@example.com', '/admin/quorum');
        await page.goto('/admin/evil');

        await expect(page.getByRole('heading', { name: 'Làm admin xấu' })).toBeVisible();
        await shot(page, '18-admin-evil');

        await page.getByRole('button', { name: '1. Đổ database' }).click();
        await expect(
            page.getByRole('heading', { name: 'Kết quả: Đổ database (FR-DEMO-01)' }),
        ).toBeVisible();
        await expect(page.getByRole('heading', { name: 'documents' })).toBeVisible();
        await expect(page.getByText(/ciphertext \/ iv \/ title_ct/)).toBeVisible();
        await shot(page, '19-evil-dump-db');

        await page.getByRole('button', { name: '2. Tự cấp quyền' }).click();
        await expect(
            page.getByRole('heading', { name: 'Kết quả: Tự cấp quyền (FR-DEMO-02)' }),
        ).toBeVisible();
        await expect(page.getByText('CR đã tạo. Chờ quorum duyệt, quyền chưa đổi.')).toBeVisible();
        await shot(page, '20-evil-self-elevate');

        await page.getByRole('button', { name: '3. Agent xin ngoài phạm vi' }).click();
        await expect(page.getByText(/OUT_OF_SCOPE/)).toBeVisible();
        await shot(page, '21-evil-out-of-scope');

        await page.getByRole('button', { name: '4. Thu hồi giữa chừng' }).click();
        await expect(
            page.getByRole('heading', { name: 'Kết quả: Thu hồi giữa chừng (FR-DEMO-04)' }),
        ).toBeVisible();
        await expect(page.getByText('Task ID:')).toBeVisible({ timeout: 30_000 });

        const grantRow = await page.getByText('Grant ID:').locator('..').innerText();
        const grantId = grantRow.split(':').slice(1).join(':').trim();
        expect(grantId).toMatch(/^[0-9a-f-]{36}$/i);
        await shot(page, '22-evil-slow-run');

        const ownerContext = await browser.newContext();
        const ownerPage = await ownerContext.newPage();
        await login(ownerPage, 'lan@example.com', '/');
        await ownerPage.goto(`/grants/${grantId}`);
        await expect(ownerPage.getByRole('heading', { name: 'Duyệt chia sẻ dữ liệu' })).toBeVisible();
        await expect(ownerPage.getByText('Grant đang hiệu lực')).toBeVisible();
        await shot(ownerPage, '23-slow-run-owner-grant');

        ownerPage.once('dialog', (dialog) => dialog.accept());
        await ownerPage.getByRole('button', { name: 'Thu hồi', exact: true }).click();
        await expect(ownerPage.getByText('Đã thu hồi grant và xoá khoá.')).toBeVisible();
        await shot(ownerPage, '24-slow-run-owner-revoked');
        await ownerContext.close();

        await expect(page.getByText('failed_revoked')).toBeVisible({ timeout: 30_000 });
        await expect(page.getByText(/slow_run\.(stopped|aborted)/)).toBeVisible({ timeout: 30_000 });
        await shot(page, '25-evil-slow-run-revoked');

        await page.getByRole('button', { name: '5. Đặt lại dữ liệu demo' }).click();
        await expect(
            page.getByRole('heading', { name: 'Kết quả: Đặt lại dữ liệu demo (FR-DEMO-05)' }),
        ).toBeVisible();
        await expect(page.getByText(/Đã đặt lại dữ liệu demo/)).toBeVisible();
        await shot(page, '26-evil-reset');
    });
});
