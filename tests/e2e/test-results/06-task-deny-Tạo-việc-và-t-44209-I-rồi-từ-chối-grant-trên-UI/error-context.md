# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: 06-task-deny.spec.js >> Tạo việc và từ chối grant >> owner tạo task qua API rồi từ chối grant trên UI
- Location: tests/e2e/06-task-deny.spec.js:5:5

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: locator('article').filter({ hasText: 'E2E từ chối grant' })
Expected: visible
Timeout: 15000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" locator('article').filter({ hasText: 'E2E từ chối grant' }) with timeout 15000ms
  - waiting for locator('article').filter({ hasText: 'E2E từ chối grant' })

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
  4  | test.describe('Tạo việc và từ chối grant', () => {
  5  |     test('owner tạo task qua API rồi từ chối grant trên UI', async ({ page }) => {
  6  |         await login(page, 'lan@example.com', '/');
  7  | 
  8  |         const createResponse = await page.request.post('/api/v1/tasks', {
  9  |             data: {
  10 |                 playbook_slug: 'rental',
  11 |                 instruction: 'E2E từ chối grant',
  12 |             },
  13 |         });
  14 |         expect(createResponse.status()).toBe(201);
  15 | 
  16 |         const created = await createResponse.json();
  17 |         const taskId = created.data.id;
  18 |         expect(taskId).toBeTruthy();
  19 | 
  20 |         let grantId = null;
  21 |         await expect
  22 |             .poll(
  23 |                 async () => {
  24 |                     const response = await page.request.get(`/api/v1/tasks/${taskId}`);
  25 |                     const body = await response.json();
  26 |                     grantId = body.data?.grant?.id ?? null;
  27 | 
  28 |                     return grantId;
  29 |                 },
  30 |                 { timeout: 30_000 },
  31 |             )
  32 |             .not.toBeNull();
  33 | 
  34 |         await page.reload();
  35 |         const taskCard = page.locator('article').filter({ hasText: 'E2E từ chối grant' });
> 36 |         await expect(taskCard).toBeVisible();
     |                                ^ Error: expect(locator).toBeVisible() failed
  37 |         await taskCard.getByRole('link', { name: 'Mở màn duyệt' }).click();
  38 | 
  39 |         await expect(page.getByRole('heading', { name: 'Duyệt chia sẻ dữ liệu' })).toBeVisible();
  40 |         await expect(page).toHaveURL(new RegExp(`/grants/${grantId}$`));
  41 |         await shot(page, '27-consent-pending-deny');
  42 | 
  43 |         page.once('dialog', (dialog) => dialog.accept());
  44 |         await page.getByRole('button', { name: 'Từ chối', exact: true }).click();
  45 |         await expect(page.getByText('Đã từ chối grant.')).toBeVisible();
  46 |         await shot(page, '28-grant-denied');
  47 |     });
  48 | });
  49 | 
```