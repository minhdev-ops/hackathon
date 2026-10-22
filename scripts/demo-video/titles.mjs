/**
 * Render title card của từng phần thành PNG 1920x1080.
 *
 * Dùng đúng design token của app (resources/css/app.css) nên card nhìn
 * "cùng hệ" với giao diện đang quay.
 */
import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../..');
const cardDir = path.join(root, 'demo-video', 'work', 'cards');

const storyboard = JSON.parse(await fs.readFile(path.join(here, 'storyboard.json'), 'utf8'));

const TOKENS = {
    bg: 'oklch(99.2% 0 0)',
    surface: 'oklch(98.4% 0 0)',
    elevated: 'oklch(96.8% 0 0)',
    border: 'oklch(89.5% 0 0)',
    text: 'oklch(11.2% 0 0)',
    muted: 'oklch(46% 0 0)',
    accent: 'oklch(55% 0.16 220)',
    danger: 'oklch(62% 0.02 25)',
};

function page({ eyebrow, title, subtitle, footer }) {
    return `<!doctype html>
<html lang="vi"><head><meta charset="utf-8">
<style>
  * { box-sizing: border-box; }
  html, body { margin: 0; width: 1920px; height: 1080px; }
  body {
    background: ${TOKENS.bg};
    color: ${TOKENS.text};
    font-family: 'Instrument Sans', Lato, 'DejaVu Sans', ui-sans-serif, system-ui, sans-serif;
    display: flex; align-items: center; justify-content: center;
    position: relative; overflow: hidden;
  }
  .glow {
    position: absolute; inset: 0;
    background:
      radial-gradient(900px 620px at 18% 12%, color-mix(in oklch, ${TOKENS.accent} 16%, transparent), transparent 70%),
      radial-gradient(760px 560px at 88% 92%, color-mix(in oklch, ${TOKENS.accent} 9%, transparent), transparent 70%);
  }
  .rule {
    position: absolute; left: 0; right: 0; top: 50%; height: 1px;
    background: linear-gradient(90deg, transparent, ${TOKENS.border} 22%, ${TOKENS.border} 78%, transparent);
  }
  .wrap { position: relative; display: flex; flex-direction: column; align-items: center; gap: 26px; text-align: center; padding: 0 140px; }
  .badge {
    width: 92px; height: 92px; border-radius: 20px; background: ${TOKENS.accent}; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 34px; font-weight: 800; letter-spacing: -0.5px;
    box-shadow: 0 12px 30px color-mix(in oklch, ${TOKENS.accent} 34%, transparent);
  }
  .eyebrow {
    font-size: 22px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase;
    color: ${TOKENS.accent};
  }
  h1 { margin: 0; font-size: 84px; line-height: 1.08; font-weight: 800; letter-spacing: -2px; }
  .subtitle { margin: 0; font-size: 30px; color: ${TOKENS.muted}; font-weight: 500; }
  .footer {
    margin-top: 8px; font-size: 21px; color: ${TOKENS.muted};
    border: 1px solid ${TOKENS.border}; background: ${TOKENS.surface};
    border-radius: 14px; padding: 16px 26px; max-width: 1180px; line-height: 1.5;
  }
  .footer strong { color: ${TOKENS.text}; font-weight: 700; }
</style></head>
<body>
  <div class="glow"></div>
  <div class="rule"></div>
  <div class="wrap">
    <div class="badge">TG</div>
    ${eyebrow ? `<div class="eyebrow">${eyebrow}</div>` : ''}
    <h1>${title}</h1>
    ${subtitle ? `<p class="subtitle">${subtitle}</p>` : ''}
    ${footer ? `<div class="footer">${footer}</div>` : ''}
  </div>
</body></html>`;
}

function sectionCard(scene) {
    return page({
        eyebrow: 'TrustGate demo',
        title: scene.card,
        subtitle: scene.subtitle ?? '',
    });
}

function outroCard(scene) {
    return page({
        eyebrow: 'Prototype hackathon',
        title: 'TrustGate',
        subtitle: scene.subtitle ?? '',
        footer:
            'Vault tài liệu mã hoá ở trình duyệt · Consent broker theo playbook · Quorum quản trị nhiều chữ ký · Audit log hash-chain<br>'
            + '<strong>Trung thực:</strong> ENC_MODE=fallback (WebCrypto, chưa trustless) · AUTH_MODE=session (chưa bật TideCloak QEA) · '
            + 'K_g nằm ở server trong hạn grant.',
    });
}

const browser = await chromium.launch({ channel: 'chrome', headless: true });
const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
    deviceScaleFactor: 1,
    locale: 'vi-VN',
});
const pageHandle = await context.newPage();

await fs.mkdir(cardDir, { recursive: true });

for (const scene of storyboard.scenes) {
    if (!scene.card && scene.id !== 'outro') {
        continue;
    }

    const html = scene.id === 'outro' ? outroCard(scene) : sectionCard(scene);
    await pageHandle.setContent(html, { waitUntil: 'load' });
    await pageHandle.evaluate(() => document.fonts.ready);
    await pageHandle.waitForTimeout(150);

    const target = path.join(cardDir, `${scene.id}.png`);
    await pageHandle.screenshot({ path: target });
    console.log(`  ✓ card ${scene.id}.png`);
}

await context.close();
await browser.close();
