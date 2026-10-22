# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: 02-consent-grant.spec.js >> Owner duyệt grant >> mở két, duyệt grant, xem grant hiệu lực và thu hồi
- Location: tests/e2e/02-consent-grant.spec.js:5:5

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText('Đã thu hồi')
Expected: visible
Error: strict mode violation: getByText('Đã thu hồi') resolved to 2 elements:
    1) <div class="mb-4 rounded-lg border border-[#86efac] bg-[#f0fdf4] p-3 text-sm text-[#15803d]">Đã thu hồi grant và xoá khoá.</div> aka getByText('Đã thu hồi grant và xoá khoá')
    2) <span class="rounded-full bg-[#f3f3f2] px-3 py-1 text-xs">Đã thu hồi</span> aka getByText('Đã thu hồi', { exact: true })

Call log:
  - Expect "toBeVisible" getByText('Đã thu hồi') with timeout 15000ms
  - waiting for getByText('Đã thu hồi')

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
          - heading "Duyệt chia sẻ dữ liệu" [level=1] [ref=f1e27]
          - paragraph [ref=f1e28]: TrustGate Agent · Nộp hồ sơ thuê nhà
        - link "← Trang chủ" [ref=f1e29] [cursor=pointer]:
          - /url: /
      - generic [ref=f1e30]: Đã thu hồi grant và xoá khoá.
      - generic [ref=f1e31]:
        - generic [ref=f1e32]:
          - generic [ref=f1e33]:
            - heading "Tài liệu được yêu cầu" [level=2] [ref=f1e34]
            - generic [ref=f1e35]: Đã thu hồi
          - paragraph [ref=f1e36]: "Gửi cho: Chủ nhà · Thuê nhà"
          - paragraph [ref=f1e37]: "Lý do: Chủ nhà cần giấy tờ tuỳ thân và chứng minh thu nhập trước khi ký hợp đồng."
          - generic [ref=f1e38]:
            - generic [ref=f1e39] [cursor=pointer]:
              - checkbox "CMND Lan bắt buộc Căn cước / CMND · 0.1 KB" [checked] [disabled] [ref=f1e40]
              - generic [ref=f1e41]:
                - generic [ref=f1e42]:
                  - generic [ref=f1e43]: CMND Lan
                  - generic [ref=f1e44]: bắt buộc
                - generic [ref=f1e45]: Căn cước / CMND · 0.1 KB
            - generic [ref=f1e46] [cursor=pointer]:
              - checkbox "Xác nhận thu nhập Lan bắt buộc Chứng minh thu nhập · 0.1 KB" [checked] [disabled] [ref=f1e47]
              - generic [ref=f1e48]:
                - generic [ref=f1e49]:
                  - generic [ref=f1e50]: Xác nhận thu nhập Lan
                  - generic [ref=f1e51]: bắt buộc
                - generic [ref=f1e52]: Chứng minh thu nhập · 0.1 KB
            - generic [ref=f1e53] [cursor=pointer]:
              - checkbox "Lịch sử thuê nhà Lan Lịch sử thuê nhà · 0.1 KB" [checked] [disabled] [ref=f1e54]
              - generic [ref=f1e55]:
                - generic [ref=f1e56]: Lịch sử thuê nhà Lan
                - generic [ref=f1e58]: Lịch sử thuê nhà · 0.1 KB
        - complementary [ref=f1e59]:
          - generic [ref=f1e60]:
            - heading "Mở két" [level=2] [ref=f1e61]
            - paragraph [ref=f1e62]:
              - text: Khoá chỉ nằm trong bộ nhớ trình duyệt. Demo dùng mật khẩu
              - code [ref=f1e63]: password
              - text: .
            - paragraph [ref=f1e64]: Két đã mở.
          - generic [ref=f1e65]:
            - paragraph [ref=f1e66]: Điều sẽ xảy ra
            - paragraph [ref=f1e67]: Trình duyệt giải mã tài liệu, tạo K_g mới, mã hoá bundle và chỉ gửi bản mã.
            - paragraph [ref=f1e68]: Server không nhận plaintext hay K_g trong log.
            - paragraph [ref=f1e69]: Thu hồi sẽ xoá K_g; agent đang chạy dừng ngay.
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
  8  |         await page.getByRole('link', { name: 'Mở màn duyệt' }).first().click();
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
> 28 |         await expect(page.getByText('Đã thu hồi')).toBeVisible();
     |                                                    ^ Error: expect(locator).toBeVisible() failed
  29 |         await shot(page, '08-grant-revoked');
  30 |     });
  31 | });
  32 | 
```