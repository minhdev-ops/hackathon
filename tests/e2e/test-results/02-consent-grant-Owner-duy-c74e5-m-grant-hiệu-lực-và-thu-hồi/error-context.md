# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: 02-consent-grant.spec.js >> Owner duyệt grant >> mở két, duyệt grant, xem grant hiệu lực và thu hồi
- Location: tests/e2e/02-consent-grant.spec.js:5:5

# Error details

```
TimeoutError: locator.click: Timeout 20000ms exceeded.
Call log:
  - waiting for getByRole('link', { name: 'Mở màn duyệt' }).first()

```

# Page snapshot

```yaml
- generic [ref=f1e3]:
  - banner [ref=f1e4]:
    - generic [ref=f1e6]:
      - generic [ref=f1e7]:
        - link "Trang chủ TrustGate" [ref=f1e8] [cursor=pointer]:
          - /url: /
          - generic [ref=f1e9]: TG
          - generic [ref=f1e10]: TrustGate
        - generic [ref=f1e12]: OWNER
        - generic [ref=f1e13]: · lan@example.com
      - navigation "Chính đầu điều hướng" [ref=f1e14]:
        - link "Trang chủ" [ref=f1e15] [cursor=pointer]:
          - /url: /
        - link "Két tài liệu" [ref=f1e16] [cursor=pointer]:
          - /url: /vault
        - link "Playbook" [ref=f1e17] [cursor=pointer]:
          - /url: /playbooks
        - link "Agent Console" [ref=f1e18] [cursor=pointer]:
          - /url: /agent-console
        - link "Nhật ký" [ref=f1e19] [cursor=pointer]:
          - /url: /timeline
      - button "Đăng xuất" [ref=f1e21]
  - main [ref=f1e22]:
    - generic [ref=f1e23]:
      - generic [ref=f1e24]:
        - generic [ref=f1e25]: TrustGate · Consent Broker
        - heading "Két dữ liệu và Consent Broker cho AI Agent" [level=1] [ref=f1e26]
        - paragraph [ref=f1e27]: Agent chỉ nhận đúng phần được duyệt, cho đúng mục đích, trong thời hạn nhất định, và quyền này thu hồi được. Mọi thay đổi quản trị đều cần quorum admin duyệt.
        - generic [ref=f1e28]:
          - link "Bắt đầu với Két tài liệu" [ref=f1e29] [cursor=pointer]:
            - /url: /vault
          - link "Xem Playbook" [ref=f1e30] [cursor=pointer]:
            - /url: /playbooks
      - generic [ref=f1e31]:
        - heading "Bắt đầu từ" [level=2] [ref=f1e32]
        - generic [ref=f1e33]:
          - link "Két tài liệu" [ref=f1e34] [cursor=pointer]:
            - /url: /vault
            - heading "Két tài liệu" [level=3] [ref=f1e38]
            - paragraph [ref=f1e39]: Tải lên, mã hoá và quản lý giấy tờ nhạy cảm trong két.
            - generic [ref=f1e40]: Bắt đầu từ đây
          - link "Chọn playbook" [ref=f1e43] [cursor=pointer]:
            - /url: /playbooks
            - heading "Chọn playbook" [level=3] [ref=f1e47]
            - paragraph [ref=f1e48]: Xem lĩnh vực và giao việc cho agent thực hiện.
          - link "Agent Console" [ref=f1e49] [cursor=pointer]:
            - /url: /agent-console
            - heading "Agent Console" [level=3] [ref=f1e53]
            - paragraph [ref=f1e54]: Theo dõi log agent và kiểm tra quy tắc phạm vi.
          - link "Nhật ký hoạt động" [ref=f1e55] [cursor=pointer]:
            - /url: /timeline
            - heading "Nhật ký hoạt động" [level=3] [ref=f1e59]
            - paragraph [ref=f1e60]: Xem chuỗi sự kiện append-only và xác minh hash-chain.
          - link "Demo «Làm admin xấu»" [ref=f1e61] [cursor=pointer]:
            - /url: /admin/evil
            - heading "Demo «Làm admin xấu»" [level=3] [ref=f1e65]
            - paragraph [ref=f1e66]: Kịch bản thử thách bảo mật (chỉ khi bật demo).
      - paragraph [ref=f1e68]: "Prototype hackathon · 2 playbook: Thuê nhà, Khám bệnh · Chế độ mã hoá fallback (WebCrypto)"
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | import { login, shot } from './helpers.js';
  3  | 
  4  | test.describe('Owner duyệt grant', () => {
  5  |     test('mở két, duyệt grant, xem grant hiệu lực và thu hồi', async ({ page }) => {
  6  |         await login(page, 'lan@example.com', '/');
  7  | 
> 8  |         await page.getByRole('link', { name: 'Mở màn duyệt' }).first().click();
     |                                                                        ^ TimeoutError: locator.click: Timeout 20000ms exceeded.
  9  |         await expect(page.getByRole('heading', { name: 'Duyệt chia sẻ dữ liệu' })).toBeVisible();
  10 |         await expect(page.getByText('TrustGate Agent')).toBeVisible();
  11 |         await expect(page.getByText('Tài liệu được yêu cầu')).toBeVisible();
  12 |         await expect(page.getByText('Chờ duyệt')).toBeVisible();
  13 |         await shot(page, '05-consent-pending');
  14 | 
  15 |         await page.getByPlaceholder('Mật khẩu két').fill('password');
  16 |         await page.getByRole('button', { name: 'Mở két' }).click();
  17 |         await expect(page.getByText('Két đã mở.')).toBeVisible();
  18 |         await shot(page, '06-vault-unlocked');
  19 | 
  20 |         await page.getByRole('button', { name: 'Duyệt', exact: true }).click();
  21 |         await expect(page.getByText(/Đã duyệt\./)).toBeVisible({ timeout: 60_000 });
  22 |         await expect(page.getByText('Grant đang hiệu lực')).toBeVisible();
  23 |         await shot(page, '07-grant-active');
  24 | 
  25 |         page.once('dialog', (dialog) => dialog.accept());
  26 |         await page.getByRole('button', { name: 'Thu hồi', exact: true }).click();
  27 |         await expect(page.getByText('Đã thu hồi grant và xoá khoá.')).toBeVisible();
  28 |         await expect(page.getByText('Đã thu hồi', { exact: true })).toBeVisible();
  29 |         await shot(page, '08-grant-revoked');
  30 |     });
  31 | });
  32 | 
```