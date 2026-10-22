# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: 05-admin-evil.spec.js >> Demo admin xấu >> dump DB, tự cấp quyền, out-of-scope, slow-run và reset
- Location: tests/e2e/05-admin-evil.spec.js:5:5

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText(/OUT_OF_SCOPE/)
Expected: visible
Timeout: 15000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByText(/OUT_OF_SCOPE/) with timeout 15000ms
  - waiting for getByText(/OUT_OF_SCOPE/)

```

```yaml
- banner:
  - link "Trang chủ TrustGate":
    - /url: /
    - text: TG TrustGate
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
  - text: admin1 admin
  - button "Đăng xuất"
  - link "Demo admin xấu":
    - /url: /admin/evil
- main:
  - text: "!"
  - heading "Làm admin xấu" [level=1]
  - paragraph:
    - text: Chỉ bật khi
    - code: APP_DEMO=true
    - text: ". Mục đích demo: chứng minh dữ liệu chỉ tồn tại ở dạng bản mã và admin xấu không thể leo thang quyền một mình."
  - button "1. Đổ database"
  - button "2. Tự cấp quyền"
  - button "3. Agent xin ngoài phạm vi"
  - button "4. Thu hồi giữa chừng"
  - button "5. Đặt lại dữ liệu demo"
  - 'heading "Kết quả: Đổ database (FR-DEMO-01)" [level=2]'
  - paragraph: ciphertext / iv / title_ct / sha256 đều là bản mã base64, không có plaintext.
  - heading "documents" [level=3]
  - list:
    - listitem: id_card title_ct=e+DBNPp4Ixz0Uq/dNp2KhYJt… iv=tVrwUA3BT3h0C8M0 cipher_b64=35V/b2b9l38GahYRHfpC6r1Q…
    - listitem: income_proof title_ct=FXkLnC1KEooIRkSJqHnpJ1/0… iv=H6nrO5jYICnuqs8I cipher_b64=vMQjuOxxKWDe3hqLRoyNbJPU…
    - listitem: rental_history title_ct=VnGvlo2DiJDhfCLnF43PnZXZ… iv=uXBAWMBEL+xVB1TT cipher_b64=mqGcLwdgEgFSqkPCVl3TGKwO…
    - listitem: insurance_card title_ct=9PrEIhAPis9c4b5N0LBiMjvw… iv=YB4e7xsTEUry6BrS cipher_b64=4wAAqR7dSXBaqB107P4yrhj2…
    - listitem: medical_summary title_ct=ZSosncmY6DTy21xToDMbubSm… iv=7j6aez6SywxIVmWc cipher_b64=MHj4LGjJ/TP8r852vYXVychc…
  - heading "grants" [level=3]
  - list:
    - listitem: revoked expires_at=2026-10-22 09:16:27 bundle_iv=ZZWRvrcqTh+vSYUW
  - text: "{ \"documents\": [ { \"id\": \"01a162ae-a7ea-73ff-a44a-1ca45815e60d\", \"owner_id\": 1, \"doc_type\": \"id_card\", \"title_ct\": \"e+DBNPp4Ixz0Uq/dNp2KhYJtIPpsn5vcIn5dX4Onj9TsCSDP\", \"ciphertext_path\": \"documents/demo/1/id_card.bin\", \"iv\": \"tVrwUA3BT3h0C8M0\", \"sha256\": \"83170ac0d4d6f4467495ffd3280119b1f01be6b70576fc53152a4da5c6229a97\", \"size_bytes\": 99, \"enc_mode\": \"fallback\", \"deleted_at\": null, \"created_at\": \"2026-10-22 08:16:15\", \"updated_at\": \"2026-10-22 08:16:15\", \"ciphertext_b64\": \"35V/b2b9l38GahYRHfpC6r1Q4w42TnzkX8eedwH6IGbYjpl/K9SEX/wtudOJB1vgnEOC6+6vItErJfy7ZSCEGH9KWaDQiUdY6bTrZVgCz4nRnPYF7+99aLJ+RIEpBctVnrYbthsp5woNFos7cRFsXECD7A==\" }, { \"id\": \"01a162ae-a7ee-71bc-9449-f207bbac35d1\", \"owner_id\": 1, \"doc_type\": \"income_proof\", \"title_ct\": \"FXkLnC1KEooIRkSJqHnpJ1/0THalNh95HOYgMs7oSOTfB5KYo38tC6sk5fvhhn4C6Ea4IeRT\", \"ciphertext_path\": \"documents/demo/1/income_proof.bin\", \"iv\": \"H6nrO5jYICnuqs8I\", \"sha256\": \"caa3bcbf1e05018cc4d5bcd610afc79adfaacbbc59fa6b42fe1bb5eb667d4d41\", \"size_bytes\": 122, \"enc_mode\": \"fallback\", \"deleted_at\": null, \"created_at\": \"2026-10-22 08:16:15\", \"updated_at\": \"2026-10-22 08:16:15\", \"ciphertext_b64\": \"vMQjuOxxKWDe3hqLRoyNbJPUuHK0cfkRuVTRSexp5+Heg+cwo44YHgGUJH0/KXrnwB49yQXiSAtMs/tgoG7Y5m9gn8dc1KIZM5VX5TxkICG64KKo/PuWbnVPQ1X34tG0cIkkyhUD+YjB32tnhWdQmEVSPl4px1Sg5sbljELtr7QpOcdYh6JO/BMP\" },"
  - 'heading "Kết quả: Tự cấp quyền (FR-DEMO-02)" [level=2]'
  - text: "CR ID: 01a162af-08fb-70e3-b6b9-656d3113e118 Loại: role_grant Trạng thái: pending Quorum: 0/2 CR đã tạo. Chờ quorum duyệt, quyền chưa đổi."
  - 'heading "Kết quả: Agent xin ngoài phạm vi (FR-DEMO-03)" [level=2]'
  - text: ":"
  - paragraph:
    - text: "Lưu ý: các route /demo/* trả 404 khi"
    - code: APP_DEMO
    - text: không phải true. Cần đăng nhập với tư cách admin để gọi.
  - link "← Trở về trang chủ":
    - /url: /
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | import { login, shot } from './helpers.js';
  3  | 
  4  | test.describe('Demo admin xấu', () => {
  5  |     test('dump DB, tự cấp quyền, out-of-scope, slow-run và reset', async ({ page, browser }) => {
  6  |         await login(page, 'admin1@example.com', '/admin/quorum');
  7  |         await page.goto('/admin/evil');
  8  | 
  9  |         await expect(page.getByRole('heading', { name: 'Làm admin xấu' })).toBeVisible();
  10 |         await shot(page, '18-admin-evil');
  11 | 
  12 |         await page.getByRole('button', { name: '1. Đổ database' }).click();
  13 |         await expect(
  14 |             page.getByRole('heading', { name: 'Kết quả: Đổ database (FR-DEMO-01)' }),
  15 |         ).toBeVisible();
  16 |         await expect(page.getByRole('heading', { name: 'documents' })).toBeVisible();
  17 |         await expect(page.getByText(/ciphertext \/ iv \/ title_ct/)).toBeVisible();
  18 |         await shot(page, '19-evil-dump-db');
  19 | 
  20 |         await page.getByRole('button', { name: '2. Tự cấp quyền' }).click();
  21 |         await expect(
  22 |             page.getByRole('heading', { name: 'Kết quả: Tự cấp quyền (FR-DEMO-02)' }),
  23 |         ).toBeVisible();
  24 |         await expect(page.getByText('CR đã tạo. Chờ quorum duyệt, quyền chưa đổi.')).toBeVisible();
  25 |         await shot(page, '20-evil-self-elevate');
  26 | 
  27 |         await page.getByRole('button', { name: '3. Agent xin ngoài phạm vi' }).click();
> 28 |         await expect(page.getByText(/OUT_OF_SCOPE/)).toBeVisible();
     |                                                      ^ Error: expect(locator).toBeVisible() failed
  29 |         await shot(page, '21-evil-out-of-scope');
  30 | 
  31 |         await page.getByRole('button', { name: '4. Thu hồi giữa chừng' }).click();
  32 |         await expect(
  33 |             page.getByRole('heading', { name: 'Kết quả: Thu hồi giữa chừng (FR-DEMO-04)' }),
  34 |         ).toBeVisible();
  35 |         await expect(page.getByText('Task ID:')).toBeVisible({ timeout: 30_000 });
  36 | 
  37 |         const grantRow = await page.getByText('Grant ID:').locator('..').innerText();
  38 |         const grantId = grantRow.split(':').slice(1).join(':').trim();
  39 |         expect(grantId).toMatch(/^[0-9a-f-]{36}$/i);
  40 |         await shot(page, '22-evil-slow-run');
  41 | 
  42 |         const ownerContext = await browser.newContext();
  43 |         const ownerPage = await ownerContext.newPage();
  44 |         await login(ownerPage, 'lan@example.com', '/');
  45 |         await ownerPage.goto(`/grants/${grantId}`);
  46 |         await expect(ownerPage.getByRole('heading', { name: 'Duyệt chia sẻ dữ liệu' })).toBeVisible();
  47 |         await expect(ownerPage.getByText('Grant đang hiệu lực')).toBeVisible();
  48 |         await shot(ownerPage, '23-slow-run-owner-grant');
  49 | 
  50 |         ownerPage.once('dialog', (dialog) => dialog.accept());
  51 |         await ownerPage.getByRole('button', { name: 'Thu hồi', exact: true }).click();
  52 |         await expect(ownerPage.getByText('Đã thu hồi grant và xoá khoá.')).toBeVisible();
  53 |         await shot(ownerPage, '24-slow-run-owner-revoked');
  54 |         await ownerContext.close();
  55 | 
  56 |         await expect(page.getByText('failed_revoked')).toBeVisible({ timeout: 30_000 });
  57 |         await expect(page.getByText(/slow_run\.(stopped|aborted)/)).toBeVisible({ timeout: 30_000 });
  58 |         await shot(page, '25-evil-slow-run-revoked');
  59 | 
  60 |         await page.getByRole('button', { name: '5. Đặt lại dữ liệu demo' }).click();
  61 |         await expect(
  62 |             page.getByRole('heading', { name: 'Kết quả: Đặt lại dữ liệu demo (FR-DEMO-05)' }),
  63 |         ).toBeVisible();
  64 |         await expect(page.getByText(/Đã đặt lại dữ liệu demo/)).toBeVisible();
  65 |         await shot(page, '26-evil-reset');
  66 |     });
  67 | });
  68 | 
```