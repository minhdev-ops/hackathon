# 11 — Hạ tầng, test, README, E2E

**SRS:** NFR-SEC-01..04, NFR-PERF-01, NFR-REL-01, NFR-TEST-01, NFR-UX-01, §9 · **WP:** WP1 + WP15 ·
**Ưu tiên:** P0 (unblock test) · **Phụ thuộc:** không (một số phần sau issue khác)

## Mục tiêu

Làm cho dự án chạy và nghiệm thu được từ máy sạch: test backend chạy, frontend build, queue/storage/Redis
hoạt động, README đúng, có E2E cho kịch bản demo 2 phút, và các kiểm tra NFR tối thiểu.

## Hiện trạng / blocker

- `php artisan test` đang fail **36 test** với:
  `could not find driver (Connection: sqlite, Database: :memory:...)`.
  `php -m` chỉ có `PDO`, `pdo_mysql`, **không có `pdo_sqlite`**; `phpunit.xml` lại đặt
  `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`.
- `.env` đang dùng MySQL; chưa xác nhận MySQL server chạy.
- `GRANT_KEY_STORE=redis` mặc định; `php -m` không thấy extension `redis`; `REDIS_CLIENT=phpredis`.
  Test đã đặt `GRANT_KEY_STORE=array` (ổn), nhưng dev/demo cần Redis hoặc đổi sang `file`.
- `QUEUE_CONNECTION=database`; chưa có hướng dẫn chạy `queue:work`.
- Chưa có `config/cors.php`, security headers/CSP, Vite proxy.
- `README.md` vẫn là README Laravel mặc định + một mục QRM; thiếu hướng dẫn TrustGate đầy đủ.
- Chưa có Vitest, Playwright, CI.
- `npm run build` chưa được xác nhận sau khi thêm React Router 7/AdminEvil.

## Phạm vi

### 1. Unblock test (P0)

- [ ] Cài `pdo_sqlite` cho PHP 8.2 (ví dụ `sudo apt install php8.2-sqlite3`) **hoặc** đổi
      `phpunit.xml` sang MySQL test DB và tạo DB riêng. Ghi lệnh đã dùng vào README.
- [ ] Chạy `php artisan test`; ghi lại số pass/fail; sửa test/env nếu cần (không sửa test để che lỗi).
- [ ] Đảm bảo `php artisan migrate:fresh --seed` chạy trên DB dev.
- [ ] Đặt `GRANT_KEY_STORE=file` (hoặc `array`) cho dev không Redis; ghi rõ production dùng Redis.
- [ ] Thêm healthcheck/kiểm tra nhanh: `php artisan about`, `php artisan route:list`, `npm run build`.

### 2. Test tooling

- [ ] Backend: giữ PHPUnit (repo hiện dùng PHPUnit, không phải Pest). Thêm test cho các issue 01/03/05/
      06/07/08/09/10.
- [ ] Frontend: thêm `vitest`, `jsdom`, `@testing-library/react` (nếu cần), config trong
      `vite.config.js` hoặc `vitest.config.js`; script `"test:unit": "vitest run"`.
- [ ] E2E: thêm `@playwright/test`, `playwright.config.js`, `e2e/demo.spec.js`; script `"test:e2e"`.
- [ ] CI tối thiểu (GitHub Actions hoặc script `composer test` + `npm run build`): cài PHP extensions,
      chạy migrate, test, build. Không cần deploy.

### 3. Hạ tầng dev

- [ ] `config/cors.php` (nếu chạy Vite khác origin): cho phép `http://localhost:5173`,
      `http://127.0.0.1:5173`; methods/headers cần thiết; không dùng `*` cho credentialed request.
- [ ] Vite proxy `/api` → `http://127.0.0.1:8000` (hoặc `VITE_API_URL`) để dev không CORS.
- [ ] Middleware security headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`,
      `Referrer-Policy: no-referrer`, CSP cơ bản (chặn script ngoài; cho phép Vite dev nếu cần).
- [ ] Kiểm tra upload: `upload_max_filesize`, `post_max_size` ≥ 12M; ghi vào README.
- [ ] Storage: `php artisan storage:link` nếu dùng public; Vault dùng `local` private, không public.
- [ ] Queue: hướng dẫn `php artisan queue:work --tries=1`; với demo có thể `QUEUE_CONNECTION=sync`.
- [ ] (Tuỳ chọn/P2) `docker-compose.yml` gồm app, MySQL/Postgres, Redis; healthcheck; không bắt buộc nếu
      thời gian hạn chế, nhưng NFR-REL-01 yêu cầu chạy từ máy sạch.

### 4. README

Thay README Laravel bằng README TrustGate theo dàn bài SRS §13.4:

- [ ] Một câu chuyện Lan và agent thuê nhà.
- [ ] Vấn đề + 4 ràng buộc niềm tin (bảng §13.2 rút gọn).
- [ ] Giải pháp, kiến trúc, luồng Release Bundle.
- [ ] TideCloak làm gì / nhóm tự xây; chế độ `tide`/`fallback` đang chạy; bảng V1–V7.
- [ ] Cài đặt: PHP extensions, Composer, npm, `.env`, migrate/seed, queue, build, chạy.
- [ ] Tài khoản demo + mật khẩu + passphrase Vault `password`.
- [ ] Kịch bản demo 2 phút (SRS §9) + link video (điền sau).
- [ ] API list ngắn + mã lỗi.
- [ ] Test commands + kết quả hiện tại.
- [ ] Giới hạn đã biết: server đọc được phần đã duyệt trong hạn grant; thu hồi không lấy lại dữ liệu
      agent đã đọc; MIME ciphertext không verify được; V1–V7 chưa xác minh.
- [ ] Không overclaim «AI không bao giờ thấy dữ liệu».

### 5. NFR checks

- [ ] **SEC-01:** test quét log/audit/response không có `content_b64`, `grant_key`, `password`, token,
      title/plaintext. Thêm test hoặc script.
- [ ] **SEC-02:** CORS + CSP + headers; test header cơ bản.
- [ ] **SEC-03:** IDOR test cho documents, grants, tasks, audit; owner khác → 403/404.
- [ ] **SEC-04:** upload >10MB → 422; doc_type sai → 422; filename không dùng làm path.
- [ ] **PERF-01:** đo mã hoá 5MB < 3s trên máy dev; ghi số đo (không cần CI).
- [ ] **REL-01:** seeder idempotent; chạy 2 lần không nhân đôi; README cài từ máy sạch.
- [ ] **TEST-01:** backend test + Vitest + 1 Playwright demo.
- [ ] **UX-01:** đếm số click luồng chính ≤ 6; ghi vào README.

### 6. E2E Playwright

- [ ] Kịch bản:
  1. Seed; login owner Lan.
  2. Mở Vault, thấy 5 tài liệu; upload thêm 1 (nếu ổn định).
  3. Chọn playbook Thuê nhà, tạo task, chờ proposal.
  4. Consent: bỏ 1 optional, chọn TTL 15, Duyệt.
  5. Agent Console chạy; thấy log.
  6. Admin evil: dump DB; self-elevate 1/2; out-of-scope.
  7. Slow-run + revoke; task failed_revoked.
  8. Timeline verify hợp lệ.
- [ ] Chạy headless trong CI; nếu Tide/OIDC phức tạp, dùng `AUTH_MODE=session` cho E2E và ghi rõ.

### Không làm

- Không deploy production.
- Không commit `.env`, database dump, video lớn.
- Không thêm framework test thứ ba nếu không cần.

## Acceptance criteria

- [ ] `php artisan test` chạy, không còn lỗi driver; số pass/fail được ghi lại.
- [ ] `npm run build` xanh.
- [ ] `php artisan migrate:fresh --seed` + queue worker chạy demo.
- [ ] README mới đủ để người khác chạy từ máy sạch.
- [ ] Có ít nhất 1 E2E chạy được (hoặc lý do chặn rõ ràng).
- [ ] Các test NFR cốt lõi (SEC-01/03, REL-01) xanh.

## File liên quan

- `phpunit.xml`, `.env.example`, `composer.json`, `package.json`, `vite.config.js`.
- `README.md`.
- `bootstrap/app.php` (middleware headers), `config/cors.php` (mới).
- `tests/`, `e2e/`, `.github/workflows/` (nếu có).
- Tất cả issue khác — issue này là nơi tổng hợp bằng chứng.

## Ghi chú

- Không sửa `phpunit.xml` sang MySQL nếu chưa tạo DB test riêng; tránh xoá dữ liệu dev.
- Nếu dùng Docker, đặt tên service và volume rõ; không mount `.env` production.
- Video demo nên quay sau khi issue 10 xong; README để placeholder link.

## Definition of Done

- [ ] Test/build/migrate/seed chạy được và có log đính kèm.
- [ ] README TrustGate hoàn chỉnh, không còn README Laravel mặc định.
- [ ] E2E hoặc checklist demo 2 phút chạy thành công.
- [ ] Các giới hạn được ghi trung thực.
