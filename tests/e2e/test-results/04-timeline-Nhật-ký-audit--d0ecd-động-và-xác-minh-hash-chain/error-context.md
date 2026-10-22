# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: 04-timeline.spec.js >> Nhật ký audit >> xem timeline, lọc hành động và xác minh hash-chain
- Location: tests/e2e/04-timeline.spec.js:5:5

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: locator('article').filter({ hasText: 'Duyệt grant' }).first()
Expected: visible
Timeout: 15000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" locator('article').filter({ hasText: 'Duyệt grant' }).first() with timeout 15000ms
  - waiting for locator('article').filter({ hasText: 'Duyệt grant' }).first()

```

```yaml
- banner:
  - link "Trang chủ TrustGate":
    - /url: /
    - text: TG TrustGate
  - text: ADMIN · admin1@example.com
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
  - heading "Nhật ký hoạt động" [level=1]
  - paragraph: Chuỗi sự kiện append-only. Owner thấy sự kiện liên quan; admin thấy toàn bộ.
  - button "Xác minh chuỗi"
  - link "← Trang chủ":
    - /url: /
  - text: Hành động
  - combobox "Hành động":
    - option "Tất cả hành động"
    - option "Tạo việc cho agent"
    - option "Agent tạo đề xuất"
    - option "Duyệt grant" [selected]
    - option "Từ chối grant"
    - option "Thu hồi grant"
    - option "Grant hết hạn"
    - option "Agent mở grant"
    - option "Agent bị chặn mở grant"
    - option "Tải tài liệu lên"
    - option "Xem tài liệu"
    - option "Xoá tài liệu"
    - option "Agent xin tài liệu ngoài phạm vi"
    - option "Tạo yêu cầu quorum"
    - option "Duyệt yêu cầu quorum"
    - option "Từ chối yêu cầu quorum"
    - option "Thực thi yêu cầu quorum"
    - option "Yêu cầu quorum bị từ chối"
    - option "Cấp vai trò"
    - option "Thu hồi vai trò"
    - option "Cập nhật playbook"
    - option "Cập nhật quyền agent"
    - option "Cập nhật ngưỡng quorum"
  - text: Đối tượng
  - combobox "Đối tượng":
    - option "Tất cả đối tượng" [selected]
    - option "Tài liệu"
    - option "Việc agent"
    - option "Grant"
    - option "Yêu cầu quorum"
    - option "Người dùng"
    - option "Playbook"
    - option "Agent"
    - option "Cấu hình"
  - status: Chuỗi hợp lệ (5 sự kiện).
  - text: Chưa có sự kiện nào phù hợp.
  - paragraph: Xác minh chuỗi kiểm tra toàn bộ nhật ký, kể cả sự kiện của người dùng khác.
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | import { login, shot } from './helpers.js';
  3  | 
  4  | test.describe('Nhật ký audit', () => {
  5  |     test('xem timeline, lọc hành động và xác minh hash-chain', async ({ page }) => {
  6  |         await login(page, 'admin1@example.com', '/admin/quorum');
  7  |         await page.goto('/timeline');
  8  | 
  9  |         await expect(page.getByRole('heading', { name: 'Nhật ký hoạt động' })).toBeVisible();
  10 |         await expect(page.locator('article').first()).toBeVisible();
  11 |         await shot(page, '14-timeline');
  12 | 
  13 |         await page.getByRole('button', { name: 'Xác minh chuỗi' }).click();
  14 |         await expect(page.getByRole('status')).toContainText('Chuỗi hợp lệ');
  15 |         await shot(page, '15-timeline-verified');
  16 | 
  17 |         await page.locator('select').first().selectOption({ label: 'Duyệt grant' });
> 18 |         await expect(page.locator('article').filter({ hasText: 'Duyệt grant' }).first()).toBeVisible();
     |                                                                                          ^ Error: expect(locator).toBeVisible() failed
  19 |         await shot(page, '16-timeline-filter-grant-approved');
  20 | 
  21 |         await page.locator('select').first().selectOption({ label: 'Thu hồi grant' });
  22 |         await expect(page.locator('article').filter({ hasText: 'Thu hồi grant' }).first()).toBeVisible();
  23 |         await shot(page, '17-timeline-filter-grant-revoked');
  24 |     });
  25 | });
  26 | 
```