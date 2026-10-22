# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: demo.spec.js >> owner duyệt grant bằng Vault key rồi thu hồi
- Location: tests/e2e/demo.spec.js:55:1

# Error details

```
Error: expect(received).toBeTruthy()

Received: undefined
```

# Page snapshot

```yaml
- generic [ref=f1e3]:
  - banner [ref=f1e4]:
    - generic [ref=f1e6]:
      - link "Trang chủ TrustGate" [ref=f1e7] [cursor=pointer]:
        - /url: /
        - generic [ref=f1e8]: TG
        - generic [ref=f1e9]: TrustGate
      - navigation "Chính đầu điều hướng" [ref=f1e10]:
        - link "Trang chủ" [ref=f1e11] [cursor=pointer]:
          - /url: /
        - link "Két tài liệu" [ref=f1e12] [cursor=pointer]:
          - /url: /vault
        - link "Playbook" [ref=f1e13] [cursor=pointer]:
          - /url: /playbooks
        - link "Agent Console" [ref=f1e14] [cursor=pointer]:
          - /url: /agent-console
        - link "Nhật ký" [ref=f1e15] [cursor=pointer]:
          - /url: /timeline
      - generic [ref=f1e16]:
        - generic [ref=f1e17]:
          - generic [ref=f1e18]: lan
          - generic [ref=f1e19]: owner
        - button "Đăng xuất" [ref=f1e20]
        - link "Demo admin xấu" [ref=f1e21] [cursor=pointer]:
          - /url: /admin/evil
  - main [ref=f1e22]:
    - generic [ref=f1e24]:
      - generic [ref=f1e25]:
        - generic [ref=f1e26]:
          - paragraph [ref=f1e27]: TrustGate
          - heading "Xin chào, lan" [level=1] [ref=f1e28]
          - paragraph [ref=f1e29]: Owner duyệt từng lần chia sẻ; khoá két chỉ nằm trong trình duyệt.
        - navigation [ref=f1e30]:
          - link "Nhật ký" [ref=f1e31] [cursor=pointer]:
            - /url: /timeline
          - link "Quorum" [ref=f1e32] [cursor=pointer]:
            - /url: /admin/quorum
          - link "Demo admin xấu" [ref=f1e33] [cursor=pointer]:
            - /url: /admin/evil
          - button "Đăng xuất" [ref=f1e35]
      - generic [ref=f1e36]:
        - article [ref=f1e37]:
          - generic [ref=f1e38]:
            - generic [ref=f1e39]:
              - heading "E2E từ chối grant" [level=2] [ref=f1e40]
              - paragraph [ref=f1e41]: TrustGate Agent · Thuê nhà
            - generic [ref=f1e42]: Bị từ chối
          - paragraph [ref=f1e43]: "Chủ nhà cần giấy tờ tuỳ thân và chứng minh thu nhập để xử lý hồ sơ thuê nhà. Chỉ dẫn: E2E từ chối grant"
          - generic [ref=f1e44]:
            - generic [ref=f1e45]: "Grant: denied"
            - link "Mở màn duyệt" [ref=f1e46] [cursor=pointer]:
              - /url: /grants/01a162af-5346-714f-8fb2-bdb0de1569a2
        - article [ref=f1e47]:
          - generic [ref=f1e48]:
            - generic [ref=f1e49]:
              - heading "Nộp hồ sơ thuê căn hộ demo" [level=2] [ref=f1e50]
              - paragraph [ref=f1e51]: TrustGate Agent · Thuê nhà
            - generic [ref=f1e52]: Đã dừng do thu hồi
          - paragraph [ref=f1e53]: Chủ nhà cần giấy tờ tuỳ thân và chứng minh thu nhập trước khi ký hợp đồng.
          - generic [ref=f1e54]:
            - generic [ref=f1e55]: "Grant: revoked · hết hạn 16:16:27 22/10/2026"
            - link "Mở màn duyệt" [ref=f1e56] [cursor=pointer]:
              - /url: /grants/01a162ae-a805-735d-927e-25927f78cebf
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
  61 |     await expect(page).not.toHaveURL(/\/login/);
  62 | 
  63 |     const tasksResponse = await page.request.get('/api/v1/tasks');
  64 |     const tasks = (await tasksResponse.json()).data;
  65 |     const grantId = tasks.find((task) => task.grant?.status === 'pending')?.grant?.id;
> 66 |     expect(grantId).toBeTruthy();
     |                     ^ Error: expect(received).toBeTruthy()
  67 | 
  68 |     await page.goto(`/grants/${grantId}`);
  69 | 
  70 |     await expect(page.getByRole('heading', { name: 'Duyệt chia sẻ dữ liệu' })).toBeVisible();
  71 |     await page.getByPlaceholder('Mật khẩu két').fill('password');
  72 |     await page.getByRole('button', { name: 'Mở két' }).click();
  73 |     await expect(page.getByText('Két đã mở.')).toBeVisible();
  74 |     await expect(page.getByText('CMND Lan')).toBeVisible();
  75 | 
  76 |     await page.selectOption('select', '15');
  77 |     await page.getByRole('button', { name: 'Duyệt', exact: true }).click();
  78 |     await expect(page.getByText('Grant đang hiệu lực')).toBeVisible({ timeout: 15_000 });
  79 | 
  80 |     page.once('dialog', (dialog) => dialog.accept());
  81 |     await page.getByRole('button', { name: 'Thu hồi' }).click();
  82 |     await expect(page.getByText('Đã thu hồi grant và xoá khoá.')).toBeVisible();
  83 | });
  84 | 
```