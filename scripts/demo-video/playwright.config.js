import { defineConfig } from '@playwright/test';

const baseURL = process.env.DEMO_VIDEO_BASE_URL ?? 'http://127.0.0.1:8010';

export default defineConfig({
    testDir: '.',
    testMatch: '**/record.spec.mjs',
    outputDir: './.work/raw',
    globalSetup: './global-setup.mjs',
    timeout: 240_000,
    expect: { timeout: 25_000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list']],
    use: {
        baseURL,
        channel: 'chrome',
        headless: true,
        viewport: { width: 1920, height: 1080 },
        locale: 'vi-VN',
        actionTimeout: 30_000,
        navigationTimeout: 60_000,
    },
    webServer: {
        command: 'bash demo-server.sh',
        url: `${baseURL}/login`,
        reuseExistingServer: false,
        timeout: 240_000,
        stdout: 'pipe',
        stderr: 'pipe',
    },
});
