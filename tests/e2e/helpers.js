import { mkdir } from 'node:fs/promises';
import { test, expect } from '@playwright/test';

const SCREENS = 'tests/e2e/screens';

export async function shot(page, name) {
    await mkdir(SCREENS, { recursive: true });
    const path = `${SCREENS}/${name}.png`;

    await page.screenshot({ path, fullPage: true });
    await test.info().attach(name, { path, contentType: 'image/png' });
}

export async function login(page, email, expectedPath) {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill('password');

    await Promise.all([
        page.waitForURL((url) => url.pathname === expectedPath),
        page.getByRole('button', { name: 'Đăng nhập' }).click(),
    ]);

    await expect(page.locator('body')).toContainText('TrustGate');
}

export async function logout(page) {
    await Promise.all([
        page.waitForURL((url) => url.pathname === '/login'),
        page.getByRole('button', { name: 'Đăng xuất' }).first().click(),
    ]);
}

export async function apiLogin(page, email) {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Đăng nhập' }).click();
}
