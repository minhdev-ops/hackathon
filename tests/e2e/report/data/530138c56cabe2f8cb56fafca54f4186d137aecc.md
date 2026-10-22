# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: 01-auth.spec.js >> Xác thực và phân quyền >> owner đăng nhập vào trang chủ và thấy việc demo
- Location: tests/e2e/01-auth.spec.js:24:5

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('link', { name: 'Nhật ký' })
Expected: visible
Error: strict mode violation: getByRole('link', { name: 'Nhật ký' }) resolved to 2 elements:
    1) <a href="/timeline" data-discover="true" class="rounded-md px-3 py-2 text-sm font-medium transition text-text hover:bg-elevated">Nhật ký</a> aka getByRole('navigation', { name: 'Chính đầu điều hướng' }).getByRole('link', { name: 'Nhật ký' })
    2) <a href="/timeline" data-discover="true" class="rounded border border-[#d6d6d2] px-3 py-2 hover:bg-[#f3f3f2]">Nhật ký</a> aka getByRole('main').getByRole('link', { name: 'Nhật ký' })

Call log:
  - Expect "toBeVisible" getByRole('link', { name: 'Nhật ký' }) with timeout 15000ms
  - waiting for getByRole('link', { name: 'Nhật ký' })

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
      - article [ref=f1e37]:
        - generic [ref=f1e38]:
          - generic [ref=f1e39]:
            - heading "Nộp hồ sơ thuê căn hộ demo" [level=2] [ref=f1e40]
            - paragraph [ref=f1e41]: TrustGate Agent · Thuê nhà
          - generic [ref=f1e42]: Chờ owner duyệt
        - paragraph [ref=f1e43]: Chủ nhà cần giấy tờ tuỳ thân và chứng minh thu nhập trước khi ký hợp đồng.
        - generic [ref=f1e44]:
          - generic [ref=f1e45]: "Grant: pending"
          - link "Mở màn duyệt" [ref=f1e46] [cursor=pointer]:
            - /url: /grants/01a162ae-a805-735d-927e-25927f78cebf
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | import { login, shot } from './helpers.js';
  3  | 
  4  | test.describe('Xác thực và phân quyền', () => {
  5  |     test('hiển thị trang đăng nhập và tài khoản demo', async ({ page }) => {
  6  |         await page.goto('/login');
  7  | 
  8  |         await expect(page.getByRole('heading', { name: 'Đăng nhập admin' })).toBeVisible();
  9  |         await expect(page.getByText('admin1@example.com · admin')).toBeVisible();
  10 |         await expect(page.getByText('lan@example.com · owner')).toBeVisible();
  11 |         await shot(page, '01-login');
  12 |     });
  13 | 
  14 |     test('từ chối mật khẩu sai', async ({ page }) => {
  15 |         await page.goto('/login');
  16 |         await page.locator('#email').fill('admin1@example.com');
  17 |         await page.locator('#password').fill('sai-mat-khau');
  18 |         await page.getByRole('button', { name: 'Đăng nhập' }).click();
  19 | 
  20 |         await expect(page.getByText('Email hoặc mật khẩu không đúng.')).toBeVisible();
  21 |         await shot(page, '02-login-error');
  22 |     });
  23 | 
  24 |     test('owner đăng nhập vào trang chủ và thấy việc demo', async ({ page }) => {
  25 |         await login(page, 'lan@example.com', '/');
  26 | 
  27 |         await expect(page.getByRole('heading', { name: 'Xin chào, lan' })).toBeVisible();
  28 |         await expect(page.getByText('Nộp hồ sơ thuê căn hộ demo')).toBeVisible();
  29 |         await expect(page.getByRole('link', { name: 'Mở màn duyệt' })).toBeVisible();
> 30 |         await expect(page.getByRole('link', { name: 'Nhật ký' })).toBeVisible();
     |                                                                   ^ Error: expect(locator).toBeVisible() failed
  31 |         await shot(page, '03-owner-home');
  32 |     });
  33 | 
  34 |     test('admin đăng nhập vào màn quorum', async ({ page }) => {
  35 |         await login(page, 'admin1@example.com', '/admin/quorum');
  36 | 
  37 |         await expect(page.getByRole('heading', { name: 'TrustGate · Quorum quản trị' })).toBeVisible();
  38 |         await expect(page.getByText('Cần xác thực lại trước khi duyệt')).toBeVisible();
  39 |         await shot(page, '04-admin-quorum');
  40 |     });
  41 | });
  42 | 
```