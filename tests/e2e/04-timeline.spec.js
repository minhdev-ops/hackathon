import { test, expect } from '@playwright/test';
import { login, shot } from './helpers.js';

test.describe('Nhật ký audit', () => {
    test('xem timeline, lọc hành động và xác minh hash-chain', async ({ page }) => {
        await login(page, 'admin1@example.com', '/admin/quorum');
        await page.goto('/timeline');

        await expect(page.getByRole('heading', { name: 'Nhật ký hoạt động' })).toBeVisible();
        await expect(page.locator('article').first()).toBeVisible();
        await shot(page, '14-timeline');

        await page.getByRole('button', { name: 'Xác minh chuỗi' }).click();
        await expect(page.getByRole('status')).toContainText('Chuỗi hợp lệ');
        await shot(page, '15-timeline-verified');

        await page.locator('select').first().selectOption({ label: 'Duyệt grant' });
        await expect(page.locator('article').filter({ hasText: 'Duyệt grant' }).first()).toBeVisible();
        await shot(page, '16-timeline-filter-grant-approved');

        await page.locator('select').first().selectOption({ label: 'Thu hồi grant' });
        await expect(page.locator('article').filter({ hasText: 'Thu hồi grant' }).first()).toBeVisible();
        await shot(page, '17-timeline-filter-grant-revoked');
    });
});
