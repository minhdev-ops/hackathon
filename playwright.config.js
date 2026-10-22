import { defineConfig } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8001';

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './tests/e2e/test-results',
    timeout: 90_000,
    expect: {
        timeout: 15_000,
    },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [
        ['list'],
        ['html', { outputFolder: 'tests/e2e/report', open: 'never' }],
    ],
    use: {
        baseURL,
        channel: 'chrome',
        headless: true,
        viewport: { width: 1440, height: 1000 },
        locale: 'vi-VN',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        actionTimeout: 20_000,
        navigationTimeout: 45_000,
        video: 'on',
    },
    webServer: {
        command: 'bash scripts/e2e-server.sh',
        url: `${baseURL}/login`,
        reuseExistingServer: false,
        timeout: 180_000,
        stdout: 'pipe',
        stderr: 'pipe',
    },
});
