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

Locator: getByRole('heading', { name: 'Xin chào, lan' })
Expected: visible
Timeout: 15000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByRole('heading', { name: 'Xin chào, lan' }) with timeout 15000ms
  - waiting for getByRole('heading', { name: 'Xin chào, lan' })

```

```yaml
- banner:
  - link "Trang chủ TrustGate":
    - /url: /
    - text: TG TrustGate
  - text: OWNER · lan@example.com
  - navigation "Chính đầu điều hướng":
    - link "Trang chủ":
      - /url: /
    - link "Két tài liệu":
      - /url: /vault
    - link "Playbook":
      - /url: /playbooks
    - link "Agent Console":
      - /url: /agent-console
    - link "Nhật ký":
      - /url: /timeline
  - button "Đăng xuất"
- main:
  - text: TrustGate · Consent Broker
  - heading "Két dữ liệu và Consent Broker cho AI Agent" [level=1]
  - paragraph: Agent chỉ nhận đúng phần được duyệt, cho đúng mục đích, trong thời hạn nhất định, và quyền này thu hồi được. Mọi thay đổi quản trị đều cần quorum admin duyệt.
  - link "Bắt đầu với Két tài liệu":
    - /url: /vault
  - link "Xem Playbook":
    - /url: /playbooks
  - heading "Bắt đầu từ" [level=2]
  - link "Két tài liệu":
    - /url: /vault
    - img
    - heading "Két tài liệu" [level=3]
    - paragraph: Tải lên, mã hoá và quản lý giấy tờ nhạy cảm trong két.
    - text: Bắt đầu từ đây
  - link "Chọn playbook":
    - /url: /playbooks
    - img
    - heading "Chọn playbook" [level=3]
    - paragraph: Xem lĩnh vực và giao việc cho agent thực hiện.
  - link "Agent Console":
    - /url: /agent-console
    - img
    - heading "Agent Console" [level=3]
    - paragraph: Theo dõi log agent và kiểm tra quy tắc phạm vi.
  - link "Nhật ký hoạt động":
    - /url: /timeline
    - img
    - heading "Nhật ký hoạt động" [level=3]
    - paragraph: Xem chuỗi sự kiện append-only và xác minh hash-chain.
  - link "Demo «Làm admin xấu»":
    - /url: /admin/evil
    - img
    - heading "Demo «Làm admin xấu»" [level=3]
    - paragraph: Kịch bản thử thách bảo mật (chỉ khi bật demo).
  - paragraph: "Prototype hackathon · 2 playbook: Thuê nhà, Khám bệnh · Chế độ mã hoá fallback (WebCrypto)"
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
  8  |         await expect(page.getByRole('heading', { name: 'Đăng nhập', exact: true })).toBeVisible();
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
> 27 |         await expect(page.getByRole('heading', { name: 'Xin chào, lan' })).toBeVisible();
     |                                                                            ^ Error: expect(locator).toBeVisible() failed
  28 |         await expect(page.getByText('Nộp hồ sơ thuê căn hộ demo')).toBeVisible();
  29 |         await expect(page.getByRole('link', { name: 'Mở màn duyệt' })).toBeVisible();
  30 |         await expect(
  31 |             page.getByRole('main').getByRole('link', { name: 'Nhật ký' }),
  32 |         ).toBeVisible();
  33 |         await shot(page, '03-owner-home');
  34 |     });
  35 | 
  36 |     test('admin đăng nhập vào màn quorum', async ({ page }) => {
  37 |         await login(page, 'admin1@example.com', '/admin/quorum');
  38 | 
  39 |         await expect(page.getByRole('heading', { name: 'TrustGate · Quorum quản trị' })).toBeVisible();
  40 |         await expect(page.getByText('Cần xác thực lại trước khi duyệt')).toBeVisible();
  41 |         await shot(page, '04-admin-quorum');
  42 |     });
  43 | });
  44 | 
```