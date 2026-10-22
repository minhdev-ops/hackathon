import { test, expect } from '@playwright/test';
import { login, shot } from './helpers';

const DEMO_USER = 'lan@example.com';
const DEMO_PASS = 'password';
const ADMIN_USER = 'admin1@example.com';
const ADMIN_PASS = 'password';
const ADMIN2_USER = 'admin2@example.com';
const ADMIN2_PASS = 'password';

test.describe.configure({ retries: 0 });

test.describe('🎬 TrustGate Demo Video - Full Feature Walkthrough', () => {
  
  test('01 - Login & Dashboard Overview', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveURL(/.*login/);
    await shot(page, '01-login-page');
    
    await login(page, DEMO_USER, '/');
    await expect(page.getByRole('heading', { name: /xin chào/i })).toBeVisible();
    await shot(page, '02-dashboard-owner');
    
    // Show navigation
    await expect(page.getByRole('link', { name: /kết tài liệu/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /lĩnh vực/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /giao việc/i })).toBeVisible();
    await shot(page, '03-dashboard-nav');
  });

  test('02 - Vault: Upload Encrypted Documents', async ({ page }) => {
    await login(page, DEMO_USER, '/vault');
    await expect(page.getByRole('heading', { name: /kết tài liệu/i })).toBeVisible();
    await shot(page, '04-vault-empty');
    
    // Upload 3 document types
    const docTypes = [
      { type: 'id_card', label: 'CMND/CCCD' },
      { type: 'income_proof', label: 'Chứng minh thu nhập' },
      { type: 'rental_history', label: 'Lịch sử thuê nhà' }
    ];
    
    for (const doc of docTypes) {
      await page.getByRole('button', { name: /tải lên/i }).click();
      await expect(page.getByRole('heading', { name: /tải lên tài liệu/i })).toBeVisible();
      await shot(page, `05-upload-${doc.type}-dialog`);
      
      // Select document type
      await page.getByLabel(/loại tài liệu/i).selectOption(doc.type);
      await page.getByLabel(/tiêu đề/i).fill(`Demo ${doc.label}`);
      await shot(page, `06-upload-${doc.type}-filled`);
      
      // Note: In real app, file is encrypted client-side before upload
      // For demo, we simulate successful upload
      await page.getByRole('button', { name: /tải lên/i }).click();
      await expect(page.getByText(/tải lên thành công/i)).toBeVisible({ timeout: 10000 });
      await shot(page, `07-upload-${doc.type}-success`);
    }
    
    // List documents
    await expect(page.getByRole('row', { name: /CMND/i })).toBeVisible();
    await expect(page.getByRole('row', { name: /Chứng minh thu nhập/i })).toBeVisible();
    await expect(page.getByRole('row', { name: /Lịch sử thuê nhà/i })).toBeVisible();
    await shot(page, '08-vault-list');
  });

  test('03 - Playbooks: View Scope & TTL', async ({ page }) => {
    await login(page, DEMO_USER, '/playbooks');
    await expect(page.getByRole('heading', { name: /lĩnh vực/i })).toBeVisible();
    await shot(page, '09-playbooks-list');
    
    // Click into kyc_verification
    await page.getByRole('link', { name: /xác thực ekyc/i }).click();
    await expect(page.getByRole('heading', { name: /xác thực ekyc/i })).toBeVisible();
    await shot(page, '10-playbook-kyc-detail');
    
    // Show scope: required, optional, forbidden doc types
    await expect(page.getByText(/loại bắt buộc/i)).toBeVisible();
    await expect(page.getByText(/loại tùy chọn/i)).toBeVisible();
    await expect(page.getByText(/thời hạn/i)).toBeVisible();
    await shot(page, '11-playbook-scope-ttl');
  });

  test('04 - Tasks: Create Agent Task & Auto Proposal', async ({ page }) => {
    await login(page, DEMO_USER, '/tasks/new');
    await expect(page.getByRole('heading', { name: /tạo việc cho agent/i })).toBeVisible();
    await shot(page, '12-task-create-form');
    
    await page.getByLabel(/lĩnh vực/i).selectOption('kyc_verification');
    await page.getByLabel(/chỉ dẫn/i).fill('Xác thực danh tính khách hàng cho hồ sơ thuê căn hộ');
    await shot(page, '13-task-form-filled');
    
    await page.getByRole('button', { name: /tạo việc/i }).click();
    await expect(page.getByRole('heading', { name: /chi tiết việc/i })).toBeVisible();
    await shot(page, '14-task-pending');
    
    // Wait for mock LLM to generate proposal
    await expect(page.getByText(/đang chạy/i)).toBeVisible({ timeout: 5000 });
    await shot(page, '15-task-running');
    
    await expect(page.getByText(/đã có đề xuất/i)).toBeVisible({ timeout: 30000 });
    await shot(page, '16-task-proposal-ready');
    
    // Show proposal details
    await expect(page.getByText(/tài liệu được yêu cầu/i)).toBeVisible();
    await expect(page.getByText(/id_card/i)).toBeVisible();
    await expect(page.getByText(/income_proof/i)).toBeVisible();
    await shot(page, '17-proposal-details');
  });

  test('05 - Grant: Owner Approves with Vault Key', async ({ page }) => {
    // Navigate to grant approval from task
    await page.getByRole('link', { name: /mở màn duyệt/i }).click();
    await expect(page.getByRole('heading', { name: /duyệt chia sẻ/i })).toBeVisible();
    await shot(page, '18-grant-approve-screen');
    
    // Show TTL options
    await expect(page.getByText(/5 phút/i)).toBeVisible();
    await expect(page.getByText(/15 phút/i)).toBeVisible();
    await expect(page.getByText(/60 phút/i)).toBeVisible();
    await expect(page.getByText(/24 giờ/i)).toBeVisible();
    await shot(page, '19-grant-ttl-options');
    
    // Select 60 minutes
    await page.getByLabel(/60 phút/i).check();
    await shot(page, '20-grant-ttl-selected');
    
    // Approve - uses Vault key (client-side encryption)
    await page.getByRole('button', { name: /duyệt/i }).click();
    await expect(page.getByText(/grant đã được kích hoạt/i)).toBeVisible({ timeout: 10000 });
    await shot(page, '21-grant-approved');
    
    // Show grant active status
    await expect(page.getByText(/hiệu lực/i)).toBeVisible();
    await shot(page, '22-grant-active');
  });

  test('06 - Agent: Opens Grant & Receives Ciphertext', async ({ page }) => {
    // Simulate agent opening grant via API
    // In real flow, agent calls POST /agent/grants/{id}/open
    await page.goto('/grants/1'); // placeholder - actual grant ID from previous test
    await shot(page, '23-agent-grant-view');
    
    // Show agent can download ciphertext
    await expect(page.getByRole('button', { name: /tải bản mã/i })).toBeVisible();
    await shot(page, '24-agent-download-ciphertext');
  });

  test('07 - Admin: Quorum Governance', async ({ page }) => {
    await login(page, ADMIN_USER, '/admin/quorum');
    await expect(page.getByRole('heading', { name: /quorum quản trị/i })).toBeVisible();
    await shot(page, '25-admin-quorum-dashboard');
    
    // Show threshold and QEA status
    await expect(page.getByText(/ngưỡng phê duyệt/i)).toBeVisible();
    await expect(page.getByText(/qea/i)).toBeVisible();
    await shot(page, '26-quorum-meta');
    
    // Create Change Request: Grant role to agent
    await page.getByRole('button', { name: /tạo change request/i }).click();
    await expect(page.getByRole('heading', { name: /tạo change request/i })).toBeVisible();
    await shot(page, '27-cr-create-form');
    
    await page.getByLabel(/loại/i).selectOption('role_grant');
    await page.getByLabel(/email/i).fill('agent@example.com');
    await page.getByLabel(/vai trò/i).selectOption('agent');
    await shot(page, '28-cr-role-grant-filled');
    
    await page.getByRole('button', { name: /gửi/i }).click();
    await expect(page.getByText(/change request đã được tạo/i)).toBeVisible();
    await shot(page, '29-cr-pending');
    
    // Admin 1 approves (with reauth)
    await page.getByRole('button', { name: /phê duyệt/i }).click();
    await expect(page.getByRole('heading', { name: /xác thực lại/i })).toBeVisible();
    await page.getByLabel(/mật khẩu/i).fill(ADMIN_PASS);
    await page.getByRole('button', { name: /xác nhận/i }).click();
    await expect(page.getByText(/đã phê duyệt, chờ admin khác/i)).toBeVisible();
    await shot(page, '30-cr-approved-1');
    
    // Admin 2 approves (new browser context)
    // Note: In real demo, open incognito window
    await shot(page, '31-cr-waiting-2nd-approval');
  });

  test('08 - Audit: Timeline & Hash-Chain Verification', async ({ page }) => {
    await login(page, DEMO_USER, '/timeline');
    await expect(page.getByRole('heading', { name: /nhật ký audit/i })).toBeVisible();
    await shot(page, '32-audit-timeline');
    
    // Filter by event type
    await page.getByRole('combobox').first().selectOption({ label: /duyệt grant/i });
    await expect(page.locator('article').filter({ hasText: /duyệt grant/i }).first()).toBeVisible();
    await shot(page, '33-audit-filter-grant');
    
    // Verify hash chain
    await page.getByRole('button', { name: /xác minh chuỗi hash/i }).click();
    await expect(page.getByText(/chuỗi hash hợp lệ/i)).toBeVisible();
    await shot(page, '34-audit-verify-success');
  });

  test('09 - Demo: Evil Admin (APP_DEMO=true)', async ({ page }) => {
    await login(page, ADMIN_USER, '/admin/evil');
    await expect(page.getByRole('heading', { name: /demo.*làm admin xấu/i })).toBeVisible();
    await shot(page, '35-demo-evil-dashboard');
    
    // 1. Dump DB
    await page.getByRole('button', { name: /đổ database/i }).click();
    await expect(page.getByText(/dump database/i)).toBeVisible({ timeout: 10000 });
    await shot(page, '36-evil-dump-db');
    
    // 2. Self-elevate
    await page.getByRole('button', { name: /tự cấp quyền owner/i }).click();
    await expect(page.getByText(/đã tự cấp quyền owner/i)).toBeVisible();
    await shot(page, '37-evil-self-elevate');
    
    // 3. Out of scope
    await page.getByRole('button', { name: /xin ngoài phạm vi/i }).click();
    await expect(page.getByText(/403.*out.of.scope/i)).toBeVisible();
    await shot(page, '38-evil-out-of-scope');
    
    // 4. Slow run
    await page.getByRole('button', { name: /chạy chậm/i }).click();
    await expect(page.getByText(/slow run đã bắt đầu/i)).toBeVisible();
    await shot(page, '39-evil-slow-run');
    
    // 5. Reset demo
    await page.getByRole('button', { name: /đặt lại dữ liệu/i }).click();
    await expect(page.getByText(/đã đặt lại dữ liệu demo/i)).toBeVisible();
    await shot(page, '40-evil-reset');
  });

  test('10 - Security: Agent Out-of-Scope Request Blocked', async ({ page }) => {
    // This is tested via API in api-smoke.spec.js
    // Show the audit log entry
    await login(page, DEMO_USER, '/timeline');
    await page.getByRole('combobox').first().selectOption({ label: /agent xin tài liệu/i });
    await expect(page.locator('article').filter({ hasText: /ngoài phạm vi/i }).first()).toBeVisible();
    await shot(page, '41-audit-agent-denied');
  });
});