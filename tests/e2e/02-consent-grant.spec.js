import { test, expect } from '@playwright/test';
import { login, shot } from './helpers.js';

test.describe('Owner duyệt grant', () => {
    test('mở két, duyệt grant, xem grant hiệu lực và thu hồi', async ({ page }) => {
        await login(page, 'lan@example.com', '/');

        await page.getByRole('link', { name: 'Mở màn duyệt' }).first().click();
        await expect(page.getByRole('heading', { name: 'Duyệt chia sẻ dữ liệu' })).toBeVisible();
        await expect(page.getByText('TrustGate Agent')).toBeVisible();
        await expect(page.getByText('Tài liệu được yêu cầu')).toBeVisible();
        await expect(page.getByText('Chờ duyệt')).toBeVisible();
        await shot(page, '05-consent-pending');

        await page.getByPlaceholder('Mật khẩu két').fill('password');
        await page.getByRole('button', { name: 'Mở két' }).click();
        await expect(page.getByText('Két đã mở.')).toBeVisible();
        await shot(page, '06-vault-unlocked');

        await page.getByRole('button', { name: 'Duyệt', exact: true }).click();
        await expect(page.getByText(/Đã duyệt\./)).toBeVisible({ timeout: 60_000 });
        await expect(page.getByText('Grant đang hiệu lực')).toBeVisible();
        await shot(page, '07-grant-active');

        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Thu hồi', exact: true }).click();
        await expect(page.getByText('Đã thu hồi grant và xoá khoá.')).toBeVisible();
        await expect(page.getByText('Đã thu hồi')).toBeVisible();
        await shot(page, '08-grant-revoked');
    });
});
