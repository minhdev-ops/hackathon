# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: 03-admin-quorum.spec.js >> Quorum quản trị >> reauth, tạo CR và duyệt đủ 2 admin
- Location: tests/e2e/03-admin-quorum.spec.js:11:5

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText('Đã xác thực lại')
Expected: visible
Error: strict mode violation: getByText('Đã xác thực lại') resolved to 2 elements:
    1) <div class="mb-6 rounded-xl border px-4 py-3 text-sm border-emerald-200 bg-emerald-50 text-emerald-700">Đã xác thực lại. Bạn có 5 phút để duyệt.</div> aka getByText('Đã xác thực lại. Bạn có 5 phú')
    2) <p class="text-sm font-medium text-emerald-800">Đã xác thực lại</p> aka getByText('Đã xác thực lại', { exact: true })

Call log:
  - Expect "toBeVisible" getByText('Đã xác thực lại') with timeout 15000ms
  - waiting for getByText('Đã xác thực lại')

```

# Page snapshot

```yaml
- generic [ref=f4e3]:
  - banner [ref=f4e4]:
    - generic [ref=f4e5]:
      - generic [ref=f4e6]:
        - generic [ref=f4e7]:
          - generic [ref=f4e8]: TG
          - heading "TrustGate · Quorum quản trị" [level=1] [ref=f4e9]
        - paragraph [ref=f4e10]: Thay đổi quyền, playbook, allowlist và ngưỡng duyệt đều cần quorum admin.
      - generic [ref=f4e11]:
        - 'generic "Chưa xác minh V4/V5: dùng quorum mức app + bắt buộc xác thực lại trước khi duyệt." [ref=f4e12]': "Fallback: quorum mức app"
        - generic [ref=f4e13]: admin2
        - link "Nhật ký" [ref=f4e14] [cursor=pointer]:
          - /url: /timeline
        - button "Đăng xuất" [ref=f4e15]
  - main [ref=f4e16]:
    - generic [ref=f4e17]: Đã xác thực lại. Bạn có 5 phút để duyệt.
    - generic [ref=f4e18]:
      - generic [ref=f4e19]:
        - generic [ref=f4e20]:
          - generic [ref=f4e21]:
            - paragraph [ref=f4e22]: Ngưỡng
            - paragraph [ref=f4e23]: "2"
            - paragraph [ref=f4e24]: admin / CR
          - generic [ref=f4e25]:
            - paragraph [ref=f4e26]: Chờ duyệt
            - paragraph [ref=f4e27]: "1"
            - paragraph [ref=f4e28]: Change Request
          - generic [ref=f4e29]:
            - paragraph [ref=f4e30]: Đã chạy
            - paragraph [ref=f4e31]: "0"
            - paragraph [ref=f4e32]: payload
        - generic [ref=f4e34]:
          - paragraph [ref=f4e35]: Đã xác thực lại
          - generic [ref=f4e36]: còn 4:59
        - generic [ref=f4e37]:
          - heading "Tạo Change Request" [level=2] [ref=f4e38]
          - paragraph [ref=f4e39]: CR được lưu ở trạng thái pending và cần 2 admin duyệt.
          - generic [ref=f4e40]:
            - generic [ref=f4e41]:
              - generic [ref=f4e42]: Loại thay đổi
              - combobox "Loại thay đổi" [ref=f4e43]:
                - option "Cấp vai trò" [selected]
                - option "Thu hồi vai trò"
                - option "Cập nhật playbook"
                - option "Cập nhật allowlist agent"
                - option "Đổi ngưỡng quorum"
            - generic [ref=f4e44]:
              - generic [ref=f4e45]:
                - generic [ref=f4e46]: Tài khoản
                - combobox "Tài khoản" [ref=f4e47]:
                  - option "admin1 · admin1@example.com (admin)" [selected]
                  - option "admin2 · admin2@example.com (admin)"
                  - option "admin3 · admin3@example.com (admin)"
                  - option "lan · lan@example.com (owner)"
                  - option "trustgate-agent · agent@example.com (agent)"
              - generic [ref=f4e48]:
                - generic [ref=f4e49]: Vai trò
                - combobox "Vai trò" [ref=f4e50]:
                  - option "admin" [selected]
                  - option "agent"
                  - option "owner"
            - button "Gửi Change Request" [ref=f4e51]
        - generic [ref=f4e52]:
          - heading "Demo FR-QRM-01" [level=2] [ref=f4e53]
          - paragraph [ref=f4e54]: Gọi thẳng API đổi ngưỡng quorum phải bị chặn bằng 409 QUORUM_REQUIRED.
          - button "Thử đổi ngưỡng trực tiếp" [ref=f4e55]
      - generic [ref=f4e56]:
        - generic [ref=f4e57]:
          - heading "Change Request" [level=2] [ref=f4e58]
          - generic [ref=f4e59]:
            - button "Tất cả" [ref=f4e60]
            - button "Chờ duyệt" [ref=f4e61]
            - button "Đã thực thi" [ref=f4e62]
            - button "Bị từ chối" [ref=f4e63]
        - article [ref=f4e65]:
          - generic [ref=f4e66]:
            - generic [ref=f4e67]:
              - generic [ref=f4e68]:
                - heading "Cấp vai trò" [level=3] [ref=f4e69]
                - generic [ref=f4e70]: Chờ duyệt
              - paragraph [ref=f4e71]: admin1 · admin1@example.com · 15:16:31 22/10/2026
            - generic [ref=f4e72]: 01a162ae
          - generic [ref=f4e73]: "{ \"role\": \"admin\", \"email\": \"admin3@example.com\" }"
          - generic [ref=f4e77]:
            - text: "Phiếu duyệt:"
            - generic [ref=f4e78]: 1/2
            - generic [ref=f4e79]: · cần thêm 1
          - generic [ref=f4e83]:
            - generic [ref=f4e84]: admin1
            - generic [ref=f4e85]: Đồng ý
          - generic [ref=f4e86]:
            - button "Duyệt" [ref=f4e87]
            - button "Từ chối" [ref=f4e88]
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | import { login, logout, shot } from './helpers.js';
  3  | 
  4  | async function reauth(page) {
  5  |     await page.getByPlaceholder('Mật khẩu admin').fill('password');
  6  |     await page.getByRole('button', { name: 'Xác thực' }).click();
> 7  |     await expect(page.getByText('Đã xác thực lại')).toBeVisible();
     |                                                     ^ Error: expect(locator).toBeVisible() failed
  8  | }
  9  | 
  10 | test.describe('Quorum quản trị', () => {
  11 |     test('reauth, tạo CR và duyệt đủ 2 admin', async ({ page }) => {
  12 |         await login(page, 'admin1@example.com', '/admin/quorum');
  13 |         await shot(page, '09-quorum-before-reauth');
  14 | 
  15 |         await reauth(page);
  16 |         await shot(page, '10-quorum-reauth');
  17 | 
  18 |         await page.locator('#cr-email').selectOption('admin3@example.com');
  19 |         await page.getByRole('button', { name: 'Gửi Change Request' }).click();
  20 |         await expect(
  21 |             page.getByText('Đã tạo Change Request ở trạng thái chờ duyệt.'),
  22 |         ).toBeVisible();
  23 | 
  24 |         const card = page.locator('article').filter({ hasText: 'Cấp vai trò' }).first();
  25 |         await expect(card).toContainText('Chờ duyệt');
  26 |         await card.getByRole('button', { name: 'Duyệt', exact: true }).click();
  27 |         await expect(
  28 |             page.getByText('Đã ghi nhận phiếu duyệt, đang chờ admin khác.'),
  29 |         ).toBeVisible();
  30 |         await shot(page, '11-quorum-one-vote');
  31 | 
  32 |         await logout(page);
  33 |         await login(page, 'admin2@example.com', '/admin/quorum');
  34 |         await reauth(page);
  35 | 
  36 |         const pendingCard = page.locator('article').filter({ hasText: 'Cấp vai trò' }).first();
  37 |         await pendingCard.getByRole('button', { name: 'Duyệt', exact: true }).click();
  38 |         await expect(
  39 |             page.getByText('Quorum đủ ngưỡng — payload đã được thực thi.'),
  40 |         ).toBeVisible();
  41 |         await expect(page.locator('article').filter({ hasText: 'Cấp vai trò' }).first()).toContainText(
  42 |             'Đã thực thi',
  43 |         );
  44 |         await shot(page, '12-quorum-executed');
  45 | 
  46 |         await page.getByRole('button', { name: 'Thử đổi ngưỡng trực tiếp' }).click();
  47 |         await expect(page.getByText(/409 QUORUM_REQUIRED/)).toBeVisible();
  48 |         await shot(page, '13-quorum-direct-blocked');
  49 |     });
  50 | });
  51 | 
```