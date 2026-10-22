import { test, expect } from '@playwright/test';

/**
 * E2E tối thiểu (issue 11) — chế độ AUTH_MODE=session cho E2E,
 * dùng DB `trustgate_e2e` do tests/e2e/serve.sh seed.
 */

test('health endpoint trả lời', async ({ request }) => {
    const response = await request.get('/up');
    expect(response.ok()).toBeTruthy();
});

test('màn đăng nhập hiện và owner đăng nhập được bằng session', async ({ page }) => {
    await page.goto('/login');

    await expect(page.getByRole('heading', { name: 'TrustGate' })).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();

    await page.fill('input[name="email"]', 'lan@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');

    // Sau đăng nhập không còn ở /login.
    await expect(page).not.toHaveURL(/\/login/);

    // /api/v1/me dùng chung hợp đồng ở cả session lẫn tide mode.
    const me = await page.request.get('/api/v1/me');
    expect(me.ok()).toBeTruthy();
    const { data } = await me.json();
    expect(data.email).toBe('lan@example.com');
    expect(data.role).toBe('owner');
    expect(data.vault_kdf_salt).toBeTruthy();
});

test('không để token trong localStorage', async ({ page }) => {
    await page.goto('/login');
    const storage = await page.evaluate(() =>
        Object.entries(window.localStorage).map(([k, v]) => `${k}=${v}`).join('|'),
    );
    expect(storage).not.toContain('access_token');
    expect(storage).not.toContain('jwt');
});

test('owner không vào được màn admin quorum', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'lan@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await expect(page).not.toHaveURL(/\/login/);

    const response = await page.goto('/admin/quorum');
    expect(response.status()).toBe(403);
});

test('owner duyệt grant bằng Vault key rồi thu hồi', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'lan@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');

    await expect(page).not.toHaveURL(/\/login/);

    const tasksResponse = await page.request.get('/api/v1/tasks');
    const tasks = (await tasksResponse.json()).data;
    const grantId = tasks.find((task) => task.grant?.status === 'pending')?.grant?.id;
    expect(grantId).toBeTruthy();

    await page.goto(`/grants/${grantId}`);

    await expect(page.getByRole('heading', { name: 'Duyệt chia sẻ dữ liệu' })).toBeVisible();
    await page.getByPlaceholder('Mật khẩu két').fill('password');
    await page.getByRole('button', { name: 'Mở két' }).click();
    await expect(page.getByText('Két đã mở.')).toBeVisible();
    await expect(page.getByText('CMND Lan')).toBeVisible();

    await page.selectOption('select', '15');
    await page.getByRole('button', { name: 'Duyệt', exact: true }).click();
    await expect(page.getByText('Grant đang hiệu lực')).toBeVisible({ timeout: 15_000 });

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Thu hồi' }).click();
    await expect(page.getByText('Đã thu hồi grant và xoá khoá.')).toBeVisible();
});
