/**
 * Đăng nhập trước một lần cho mỗi vai trò rồi lưu storageState.
 * Nhờ vậy mỗi cảnh quay chuyển vai trò tức thì, không phải gõ mật khẩu lại.
 */
import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const baseURL = process.env.DEMO_VIDEO_BASE_URL ?? 'http://127.0.0.1:8010';
const here = path.dirname(fileURLToPath(import.meta.url));
const authDir = path.join(here, '.auth');

export const ROLES = {
    owner: 'lan@example.com',
    admin1: 'admin1@example.com',
    admin2: 'admin2@example.com',
    agent: 'agent@example.com',
};

export default async function globalSetup() {
    await fs.mkdir(authDir, { recursive: true });

    const browser = await chromium.launch({ channel: 'chrome', headless: true });

    for (const [role, email] of Object.entries(ROLES)) {
        const context = await browser.newContext({
            baseURL,
            viewport: { width: 1920, height: 1080 },
            locale: 'vi-VN',
        });
        const page = await context.newPage();

        await page.goto('/login', { waitUntil: 'domcontentloaded' });
        await page.locator('#email').fill(email);
        await page.locator('#password').fill('password');

        await Promise.all([
            page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 45_000 }),
            page.getByRole('button', { name: 'Đăng nhập' }).click(),
        ]);

        const badge = await page.locator('header').innerText();
        await context.storageState({ path: path.join(authDir, `${role}.json`) });
        await context.close();

        console.log(`  · ${role} (${email}) đăng nhập OK — header: ${badge.replace(/\s+/g, ' ').trim()}`);
    }

    await browser.close();
}
