import { test, expect } from '@playwright/test';
import { login, shot } from './helpers.js';

test.describe('Xác thực và phân quyền', () => {
    test('hiển thị trang đăng nhập và tài khoản demo', async ({ page }) => {
        await page.goto('/login');

        await expect(page.getByRole('heading', { name: 'Đăng nhập admin' })).toBeVisible();
        await expect(page.getByText('admin1@example.com · admin')).toBeVisible();
        await expect(page.getByText('lan@example.com · owner')).toBeVisible();
        await shot(page, '01-login');
    });

    test('từ chối mật khẩu sai', async ({ page }) => {
        await page.goto('/login');
        await page.locator('#email').fill('admin1@example.com');
        await page.locator('#password').fill('sai-mat-khau');
        await page.getByRole('button', { name: 'Đăng nhập' }).click();

        await expect(page.getByText('Email hoặc mật khẩu không đúng.')).toBeVisible();
        await shot(page, '02-login-error');
    });

    test('owner đăng nhập vào trang chủ và thấy việc demo', async ({ page }) => {
        await login(page, 'lan@example.com', '/');

        await expect(page.getByRole('heading', { name: 'Xin chào, lan' })).toBeVisible();
        await expect(page.getByText('Nộp hồ sơ thuê căn hộ demo')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Mở màn duyệt' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Nhật ký' })).toBeVisible();
        await shot(page, '03-owner-home');
    });

    test('admin đăng nhập vào màn quorum', async ({ page }) => {
        await login(page, 'admin1@example.com', '/admin/quorum');

        await expect(page.getByRole('heading', { name: 'TrustGate · Quorum quản trị' })).toBeVisible();
        await expect(page.getByText('Cần xác thực lại trước khi duyệt')).toBeVisible();
        await shot(page, '04-admin-quorum');
    });
});
