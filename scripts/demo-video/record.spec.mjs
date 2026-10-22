/**
 * Quay từng cảnh của video demo trên hệ thống thật.
 *
 * Mỗi cảnh = 1 test Playwright, 1 context riêng (nên 1 file .webm riêng),
 * storageState theo vai trò để chuyển vai trò tức thì.
 *
 * Độ dài mỗi cảnh lấy từ demo-video/work/durations.json (sinh bởi narrate.py),
 * đảm bảo âm thanh và hình ảnh luôn khớp nhau.
 */
import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../..');
const workDir = path.join(root, 'demo-video', 'work');
const videoDir = path.join(workDir, 'video');

const storyboard = JSON.parse(fs.readFileSync(path.join(here, 'storyboard.json'), 'utf8'));
const durations = JSON.parse(fs.readFileSync(path.join(workDir, 'durations.json'), 'utf8'));

const scenes = Object.fromEntries(storyboard.scenes.map((scene) => [scene.id, scene]));

/** State dùng chung giữa các cảnh (cùng worker, chạy tuần tự). */
const state = {};

function scene(id) {
    if (!scenes[id]) {
        throw new Error(`Không có cảnh "${id}" trong storyboard.json`);
    }

    return scenes[id];
}

/**
 * Mở context có ghi hình, chạy hành động của cảnh, giữ nguyên trạng thái cuối
 * cho tới khi đủ độ dài mong muốn, rồi lưu video thành <id>.webm.
 */
async function recordScene(browser, id, action) {
    const current = scene(id);
    const duration = durations.scenes[id];

    if (!duration) {
        throw new Error(`Thiếu durations.json cho cảnh "${id}" — chạy narrate.py trước.`);
    }

    const context = await browser.newContext({
        storageState: current.role ? path.join(here, '.auth', `${current.role}.json`) : undefined,
        recordVideo: { dir: videoDir, size: { width: 1920, height: 1080 } },
        viewport: { width: 1920, height: 1080 },
        locale: 'vi-VN',
    });

    const page = await context.newPage();
    const startedAt = Date.now();

    try {
        await action(page);

        const remaining = startedAt + duration.video * 1000 - Date.now();

        if (remaining > 0) {
            // Giữ nguyên trạng thái UI cuối để người xem kịp đọc kết quả.
            await page.waitForTimeout(remaining);
        }
    } finally {
        await context.close();
    }

    const video = page.video();

    if (!video) {
        throw new Error(`Cảnh "${id}" không có video (recordVideo không hoạt động).`);
    }

    const source = await video.path();
    const target = path.join(videoDir, `${id}.webm`);
    fs.copyFileSync(source, target);

    const size = fs.statSync(target).size;

    if (size < 20_000) {
        throw new Error(`Video cảnh "${id}" quá nhỏ (${size} bytes) — coi như quay hỏng.`);
    }

    console.log(`  ✓ ${id}.webm  ${(size / 1024).toFixed(0)} KB  (nhịp ${duration.video.toFixed(1)}s)`);
}

test.describe.configure({ mode: 'serial' });

test('01 · login — mở đầu', async ({ browser }) => {
    await recordScene(browser, 'login', async (page) => {
        await page.goto('/login', { waitUntil: 'domcontentloaded' });
        await page.locator('#email').fill('lan@example.com');
        await page.locator('#password').fill('password');

        await Promise.all([
            page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 45_000 }),
            page.getByRole('button', { name: 'Đăng nhập' }).click(),
        ]);

        await expect(page.getByRole('heading', { name: /Két dữ liệu và Consent Broker/ })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Bắt đầu với Két tài liệu' })).toBeVisible();
    });
});

test('02 · vault — server chỉ thấy bản mã', async ({ browser }) => {
    await recordScene(browser, 'vault', async (page) => {
        await page.goto('/vault');
        await expect(page.getByRole('grid')).toBeVisible();
        await expect(page.getByRole('columnheader', { name: 'Tên (mã hoá)' })).toBeVisible();
        await expect(page.locator('tbody tr')).toHaveCount(5);
        await expect(page.getByText('id_card').first()).toBeVisible();
    });
});

test('03 · scope — playbook và đề xuất của agent', async ({ browser }) => {
    await recordScene(browser, 'scope', async (page) => {
        await page.goto('/playbooks');
        await expect(page.getByRole('button', { name: 'Giao việc cho agent' }).first()).toBeVisible();
        await page.getByRole('button', { name: 'Giao việc cho agent' }).first().click();

        await expect(page).toHaveURL(/\/tasks\/new/);
        await page.locator('#task-instruction').fill('Nộp hồ sơ thuê căn hộ A');
        await page.getByRole('button', { name: 'Tạo task' }).click();

        await expect(page).toHaveURL(/\/tasks\/\d+$/);
        state.taskId = Number(page.url().match(/\/tasks\/(\d+)/)[1]);

        // Đề xuất do GenerateProposalJob sinh ra (chạy nền qua queue).
        await expect
            .poll(
                async () => {
                    const response = await page.request.get(`/api/v1/tasks/${state.taskId}`);
                    const payload = await response.json();

                    return payload.data?.grant?.id ?? null;
                },
                { timeout: 60_000, intervals: [500] },
            )
            .not.toBeNull();

        state.grantId = await page.request
            .get(`/api/v1/tasks/${state.taskId}`)
            .then((response) => response.json())
            .then((payload) => payload.data.grant.id);

        await expect(page.getByText('Đề xuất của agent')).toBeVisible();
        await expect(page.getByText('Chủ nhà cần giấy tờ')).toBeVisible({ timeout: 30_000 });
    });
});

test('04 · consent — duyệt từng dòng, chọn TTL', async ({ browser }) => {
    await recordScene(browser, 'consent', async (page) => {
        await page.goto(`/grants/${state.grantId}`);
        await expect(page.getByRole('heading', { name: 'Duyệt chia sẻ dữ liệu' })).toBeVisible();

        await page.getByPlaceholder('Mật khẩu két').fill('password');
        await page.getByRole('button', { name: 'Mở két' }).click();

        await expect(page.getByText('Két đã mở.')).toBeVisible();
        await expect(page.getByText('CMND Lan')).toBeVisible();

        // Bỏ tài liệu tuỳ chọn (Lịch sử thuê nhà Lan) — tài liệu bắt buộc bị khoá.
        const optionalRow = page.locator('label').filter({ hasText: 'Lịch sử thuê nhà Lan' });
        await expect(optionalRow).toBeVisible();
        await optionalRow.locator('input[type="checkbox"]').uncheck();
        await expect(optionalRow.locator('input[type="checkbox"]')).not.toBeChecked();

        await page.locator('select').selectOption('15');
        await page.getByRole('button', { name: 'Duyệt', exact: true }).click();

        await expect(page.getByText('Grant đang hiệu lực')).toBeVisible({ timeout: 30_000 });
    });
});

test('05 · agent-run — agent chạy và mọi bước đều được ghi', async ({ browser }) => {
    await recordScene(browser, 'agent-run', async (page) => {
        await expect
            .poll(
                async () => {
                    const response = await page.request.get(`/api/v1/tasks/${state.taskId}`);
                    const payload = await response.json();

                    return payload.data?.status ?? null;
                },
                { timeout: 60_000, intervals: [700] },
            )
            .toBe('done');

        await page.goto(`/tasks/${state.taskId}`);
        await expect(page.getByText('done').first()).toBeVisible();

        await page.goto('/agent-console');
        await expect(page.getByRole('heading', { name: 'Hoạt động agent' })).toBeVisible();
        await expect(page.getByText('Chưa có sự kiện agent nào.')).toHaveCount(0, { timeout: 30_000 });
    });
});

test('06 · agent-role — agent chỉ là API client', async ({ browser }) => {
    await recordScene(browser, 'agent-role', async (page) => {
        await page.goto('/');
        await expect(page.getByText('AGENT').first()).toBeVisible();
        await expect(page.getByRole('heading', { name: /Két dữ liệu và Consent Broker/ })).toBeVisible();
    });
});

test('07 · db-dump — admin đổ database chỉ thấy bản mã', async ({ browser }) => {
    await recordScene(browser, 'db-dump', async (page) => {
        await page.goto('/admin/evil');
        await page.getByRole('button', { name: '1. Đổ database' }).click();

        await expect(page.getByRole('heading', { name: /Kết quả: Đổ database/ })).toBeVisible();
        await expect(page.getByText('documents')).toBeVisible();
        await expect(page.getByText('title_ct').first()).toBeVisible({ timeout: 20_000 });
    });
});

test('08 · elevate — tự cấp quyền bị đẩy vào Change Request', async ({ browser }) => {
    await recordScene(browser, 'elevate', async (page) => {
        await page.goto('/admin/evil');
        await page.getByRole('button', { name: '2. Tự cấp quyền' }).click();

        await expect(page.getByRole('heading', { name: /Kết quả: Tự cấp quyền/ })).toBeVisible();
        await expect(page.getByText('0/2')).toBeVisible();
        await expect(page.getByText('quyền chưa đổi')).toBeVisible();
    });
});

test('09 · out-of-scope — agent xin tài liệu ngoài phạm vi bị chặn', async ({ browser }) => {
    await recordScene(browser, 'out-of-scope', async (page) => {
        await page.goto('/admin/evil');
        await page.getByRole('button', { name: '3. Agent xin ngoài phạm vi' }).click();

        await expect(page.getByRole('heading', { name: /Kết quả: Agent xin ngoài phạm vi/ })).toBeVisible();
        await expect(page.getByText(/OUT_OF_SCOPE/)).toBeVisible();
    });
});

test('10 · quorum-create — admin 1 tạo và ký CR', async ({ browser }) => {
    await recordScene(browser, 'quorum-create', async (page) => {
        await page.goto('/admin/quorum');
        await expect(page.getByRole('heading', { name: 'TrustGate · Quorum quản trị' })).toBeVisible();

        // Đổi trực tiếp bị chặn.
        await page.getByRole('button', { name: 'Thử đổi ngưỡng trực tiếp' }).click();
        await expect(page.getByText(/409 QUORUM_REQUIRED/)).toBeVisible();

        // Re-auth.
        await page.getByPlaceholder('Mật khẩu admin').fill('password');
        await page.getByRole('button', { name: 'Xác thực' }).click();
        await expect(page.getByText('Đã xác thực lại')).toBeVisible();

        // Tạo CR đổi ngưỡng quorum.
        await page.locator('#cr-type').selectOption('quorum_threshold_update');
        await page.locator('#cr-threshold').fill('3');
        await page.getByRole('button', { name: 'Gửi Change Request' }).click();

        const card = page.locator('article').filter({ hasText: 'Đổi ngưỡng quorum' }).first();
        await expect(card).toBeVisible({ timeout: 20_000 });

        // Admin 1 ký phiếu đầu tiên.
        await card.getByRole('button', { name: 'Duyệt', exact: true }).click();
        await expect(card.getByText('1/3')).toBeVisible({ timeout: 20_000 });
    });
});

test('11 · quorum-approve — admin 2 ký phiếu thứ hai, CR được thực thi', async ({ browser }) => {
    await recordScene(browser, 'quorum-approve', async (page) => {
        await page.goto('/admin/quorum');

        await page.getByPlaceholder('Mật khẩu admin').fill('password');
        await page.getByRole('button', { name: 'Xác thực' }).click();
        await expect(page.getByText('Đã xác thực lại')).toBeVisible();

        const card = page.locator('article').filter({ hasText: 'Đổi ngưỡng quorum' }).first();
        await expect(card).toBeVisible();
        await card.getByRole('button', { name: 'Duyệt', exact: true }).click();

        await expect(page.getByText('Đã thực thi').first()).toBeVisible({ timeout: 20_000 });
    });
});

test('12 · revoke-run — agent chạy chậm để thu hồi giữa chừng', async ({ browser }) => {
    await recordScene(browser, 'revoke-run', async (page) => {
        await page.goto('/admin/evil');

        const [response] = await Promise.all([
            page.waitForResponse(
                (r) => r.url().includes('/demo/evil/slow-run') && r.request().method() === 'POST',
                { timeout: 30_000 },
            ),
            page.getByRole('button', { name: '4. Thu hồi giữa chừng' }).click(),
        ]);

        const payload = await response.json();
        state.slowTaskId = payload.data.task_id;
        state.slowGrantId = payload.data.grant_id;

        await expect(page.getByRole('heading', { name: /Kết quả: Thu hồi giữa chừng/ })).toBeVisible();
        await expect(page.getByText(/slow_run/).first()).toBeVisible({ timeout: 30_000 });
    });
});

test('13 · revoke — owner thu hồi grant, agent hỏng ngay', async ({ browser }) => {
    await recordScene(browser, 'revoke', async (page) => {
        await page.goto(`/grants/${state.slowGrantId}`);

        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: 'Thu hồi', exact: true }).click();

        await expect(page.getByText('Đã thu hồi grant và xoá khoá.')).toBeVisible({ timeout: 30_000 });

        await page.goto(`/tasks/${state.slowTaskId}`);
        await expect(page.getByText('failed revoked')).toBeVisible({ timeout: 30_000 });
    });
});

test('14 · audit — xác minh hash-chain', async ({ browser }) => {
    await recordScene(browser, 'audit', async (page) => {
        await page.goto('/timeline');
        await expect(page.getByRole('heading', { name: 'Nhật ký hoạt động' })).toBeVisible();
        await expect(page.locator('article').first()).toBeVisible();

        await page.getByRole('button', { name: 'Xác minh chuỗi' }).click();
        await expect(page.getByRole('status')).toContainText('Chuỗi hợp lệ', { timeout: 30_000 });
    });
});
