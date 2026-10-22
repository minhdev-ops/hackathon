import { test, expect } from '@playwright/test';
import { login, logout, shot } from './helpers.js';

async function reauth(page) {
    await page.getByPlaceholder('Mật khẩu admin').fill('password');
    await page.getByRole('button', { name: 'Xác thực' }).click();
    await expect(page.getByText('Đã xác thực lại', { exact: true })).toBeVisible();
}

test.describe('Quorum quản trị', () => {
    test('reauth, tạo CR và duyệt đủ 2 admin', async ({ page }) => {
        await login(page, 'admin1@example.com', '/admin/quorum');
        await shot(page, '09-quorum-before-reauth');

        await reauth(page);
        await shot(page, '10-quorum-reauth');

        await page.locator('#cr-email').selectOption('admin3@example.com');
        await page.getByRole('button', { name: 'Gửi Change Request' }).click();
        await expect(
            page.getByText('Đã tạo Change Request ở trạng thái chờ duyệt.'),
        ).toBeVisible();

        const card = page.locator('article').filter({ hasText: 'Cấp vai trò' }).first();
        await expect(card).toContainText('Chờ duyệt');
        await card.getByRole('button', { name: 'Duyệt', exact: true }).click();
        await expect(
            page.getByText('Đã ghi nhận phiếu duyệt, đang chờ admin khác.'),
        ).toBeVisible();
        await shot(page, '11-quorum-one-vote');

        await logout(page);
        await login(page, 'admin2@example.com', '/admin/quorum');
        await reauth(page);

        const pendingCard = page.locator('article').filter({ hasText: 'Cấp vai trò' }).first();
        await pendingCard.getByRole('button', { name: 'Duyệt', exact: true }).click();
        await expect(
            page.getByText('Quorum đủ ngưỡng — payload đã được thực thi.'),
        ).toBeVisible();
        await expect(page.locator('article').filter({ hasText: 'Cấp vai trò' }).first()).toContainText(
            'Đã thực thi',
        );
        await shot(page, '12-quorum-executed');

        await page.getByRole('button', { name: 'Thử đổi ngưỡng trực tiếp' }).click();
        await expect(page.getByText(/409 QUORUM_REQUIRED/).first()).toBeVisible();
        await shot(page, '13-quorum-direct-blocked');
    });
});
