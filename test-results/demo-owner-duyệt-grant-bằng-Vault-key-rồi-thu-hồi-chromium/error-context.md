# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: demo.spec.js >> owner duyệt grant bằng Vault key rồi thu hồi
- Location: e2e/demo.spec.js:55:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('heading', { name: /Xin chào, lan/i })
Expected: visible
Timeout: 10000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByRole('heading', { name: /Xin chào, lan/i }) with timeout 10000ms
  - waiting for getByRole('heading', { name: /Xin chào, lan/i })

```

```yaml
- banner:
  - heading "TrustGate" [level=1]
  - paragraph: Két dữ liệu và Consent Broker cho AI Agent. Agent chỉ nhận đúng phần được duyệt, cho đúng mục đích, trong thời hạn nhất định, và quyền này thu hồi được.
- link "Két tài liệu Tải lên, mã hoá và quản lý giấy tờ nhạy cảm trong két. →":
  - /url: /vault
  - heading "Két tài liệu" [level=3]
  - paragraph: Tải lên, mã hoá và quản lý giấy tờ nhạy cảm trong két.
  - text: →
- link "Chọn playbook Xem lĩnh vực và giao việc cho agent thực hiện. →":
  - /url: /playbooks
  - heading "Chọn playbook" [level=3]
  - paragraph: Xem lĩnh vực và giao việc cho agent thực hiện.
  - text: →
- link "Agent Console Theo dõi log agent và kiểm tra quy tắc phạm vi. →":
  - /url: /agent-console
  - heading "Agent Console" [level=3]
  - paragraph: Theo dõi log agent và kiểm tra quy tắc phạm vi.
  - text: →
- link "Nhật ký hoạt động Xem chuỗi sự kiện append-only và xác minh hash-chain. →":
  - /url: /timeline
  - heading "Nhật ký hoạt động" [level=3]
  - paragraph: Xem chuỗi sự kiện append-only và xác minh hash-chain.
  - text: →
- link "Demo «Làm admin xấu» Kịch bản thử thách bảo mật (chỉ khi bật demo). →":
  - /url: /admin/evil
  - heading "Demo «Làm admin xấu»" [level=3]
  - paragraph: Kịch bản thử thách bảo mật (chỉ khi bật demo).
  - text: →
- contentinfo:
  - paragraph: "Prototype hackathon · 2 playbook: Thuê nhà, Khám bệnh · Chế độ mã hoá fallback (WebCrypto)"
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | 
  3  | /**
  4  |  * E2E tối thiểu (issue 11) — chế độ AUTH_MODE=session cho E2E,
  5  |  * dùng DB `trustgate_e2e` do tests/e2e/serve.sh seed.
  6  |  */
  7  | 
  8  | test('health endpoint trả lời', async ({ request }) => {
  9  |     const response = await request.get('/up');
  10 |     expect(response.ok()).toBeTruthy();
  11 | });
  12 | 
  13 | test('màn đăng nhập hiện và owner đăng nhập được bằng session', async ({ page }) => {
  14 |     await page.goto('/login');
  15 | 
  16 |     await expect(page.getByRole('heading', { name: 'TrustGate' })).toBeVisible();
  17 |     await expect(page.locator('input[name="email"]')).toBeVisible();
  18 | 
  19 |     await page.fill('input[name="email"]', 'lan@example.com');
  20 |     await page.fill('input[name="password"]', 'password');
  21 |     await page.click('button[type="submit"]');
  22 | 
  23 |     // Sau đăng nhập không còn ở /login.
  24 |     await expect(page).not.toHaveURL(/\/login/);
  25 | 
  26 |     // /api/v1/me dùng chung hợp đồng ở cả session lẫn tide mode.
  27 |     const me = await page.request.get('/api/v1/me');
  28 |     expect(me.ok()).toBeTruthy();
  29 |     const { data } = await me.json();
  30 |     expect(data.email).toBe('lan@example.com');
  31 |     expect(data.role).toBe('owner');
  32 |     expect(data.vault_kdf_salt).toBeTruthy();
  33 | });
  34 | 
  35 | test('không để token trong localStorage', async ({ page }) => {
  36 |     await page.goto('/login');
  37 |     const storage = await page.evaluate(() =>
  38 |         Object.entries(window.localStorage).map(([k, v]) => `${k}=${v}`).join('|'),
  39 |     );
  40 |     expect(storage).not.toContain('access_token');
  41 |     expect(storage).not.toContain('jwt');
  42 | });
  43 | 
  44 | test('owner không vào được màn admin quorum', async ({ page }) => {
  45 |     await page.goto('/login');
  46 |     await page.fill('input[name="email"]', 'lan@example.com');
  47 |     await page.fill('input[name="password"]', 'password');
  48 |     await page.click('button[type="submit"]');
  49 |     await expect(page).not.toHaveURL(/\/login/);
  50 | 
  51 |     const response = await page.goto('/admin/quorum');
  52 |     expect(response.status()).toBe(403);
  53 | });
  54 | 
  55 | test('owner duyệt grant bằng Vault key rồi thu hồi', async ({ page }) => {
  56 |     await page.goto('/login');
  57 |     await page.fill('input[name="email"]', 'lan@example.com');
  58 |     await page.fill('input[name="password"]', 'password');
  59 |     await page.click('button[type="submit"]');
  60 | 
> 61 |     await expect(page.getByRole('heading', { name: /Xin chào, lan/i })).toBeVisible();
     |                                                                         ^ Error: expect(locator).toBeVisible() failed
  62 |     await page.getByRole('link', { name: 'Mở màn duyệt' }).click();
  63 | 
  64 |     await expect(page.getByRole('heading', { name: 'Duyệt chia sẻ dữ liệu' })).toBeVisible();
  65 |     await page.getByPlaceholder('Mật khẩu két').fill('password');
  66 |     await page.getByRole('button', { name: 'Mở két' }).click();
  67 |     await expect(page.getByText('Két đã mở.')).toBeVisible();
  68 |     await expect(page.getByText('CMND Lan')).toBeVisible();
  69 | 
  70 |     await page.selectOption('select', '15');
  71 |     await page.getByRole('button', { name: 'Duyệt', exact: true }).click();
  72 |     await expect(page.getByText('Grant đang hiệu lực')).toBeVisible({ timeout: 15_000 });
  73 | 
  74 |     page.once('dialog', (dialog) => dialog.accept());
  75 |     await page.getByRole('button', { name: 'Thu hồi' }).click();
  76 |     await expect(page.getByText('Đã thu hồi grant và xoá khoá.')).toBeVisible();
  77 | });
  78 | 
```