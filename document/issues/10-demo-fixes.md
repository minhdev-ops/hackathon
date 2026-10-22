# 10 — DEMO «Làm admin xấu»: sửa lỗi và nối dây

**SRS:** FR-DEMO-01..05 · **WP:** WP14 · **Ưu tiên:** P1 · **Phụ thuộc:** 08, 09

## Mục tiêu

Làm cho 5 nút demo chạy đúng end-to-end: đổ database chỉ thấy bản mã; tự cấp quyền kẹt 1/2; agent xin
tài liệu ngoài phạm vi trả `OUT_OF_SCOPE`; thu hồi giữa chừng dừng agent; reset dữ liệu demo. Màn
`/admin/evil` phải truy cập được và không lỗi JS.

## Hiện trạng

Đã có `DemoController`, `SlowRunAgentJob`, `AdminEvil.jsx`, `DemoSeeder`, nhưng có nhiều lỗi chặn demo:

| # | Lỗi | File |
| --- | --- | --- |
| 1 | `/admin/evil` không có route web; chỉ có route client-side | `routes/web.php` |
| 2 | Axios baseURL `/api/v1` + `API_DEMO='/api/v1/demo'` → gọi `/api/v1/api/v1/demo/...` | `resources/js/components/AdminEvil.jsx` |
| 3 | `className="mt-6 {card}"` và `"mt-3 {code}"` không nội suy | `AdminEvil.jsx` |
| 4 | `{a || b && (<section>)}` sai precedence: có result nhưng không render | `AdminEvil.jsx` |
| 5 | `DocumentRequestController` gọi `AuditService::record()` thiếu `subjectId`, truyền array vào
      `subject_id` → TypeError 500 | `DocumentRequestController.php` |
| 6 | Route `/agent/documents/request` yêu cầu `role:agent`; admin demo nhận 403 `FORBIDDEN_ROLE`,
      không phải `OUT_OF_SCOPE` | `routes/api.php` |
| 7 | `DemoController::slowRun` không đưa K_g vào `GrantKeyStore` → job dừng `grant_key_shredded` | `DemoController.php` |
| 8 | `SlowRunAgentJob` log bị context ghi đè `step`, frontend đọc `entry.context` không khớp | `SlowRunAgentJob.php`, `AdminEvil.jsx` |
| 9 | `DemoSeeder` dùng `title_ct=base64(title)`, ciphertext ngẫu nhiên, không có file thật | `DemoSeeder.php` |
| 10 | `DemoController::reset` chỉ seed lại, không xoá grant/task/doc cũ | `DemoController.php` |
| 11 | `agentToken` trả token ngẫu nhiên nhưng không lưu/không dùng được | `DemoController.php` |

## Phạm vi

### 1. Route và UI shell

- [ ] `routes/web.php`: thêm
      `GET /admin/evil` → `view('admin.evil')`, middleware `['auth','role:admin','demo.enabled']`,
      name `admin.evil`.
- [ ] Tạo `resources/views/admin/evil.blade.php` với `@vite('resources/js/app.jsx')` + `<div id="root">`.
- [ ] Link «Làm admin xấu» từ `/admin/quorum` (hoặc header) khi `APP_DEMO=true`.
- [ ] `php artisan route:list` có `admin/evil`; khi `APP_DEMO=false` trả 404.

### 2. Sửa `AdminEvil.jsx`

- [ ] Đổi mọi call sang đường dẫn tương đối với baseURL `/api/v1`:
      `api.post('/demo/evil/dump-db')`, `'/demo/evil/self-elevate'`, `'/demo/evil/slow-run'`,
      `'/demo/evil/slow-run-log/'+id`, `'/demo/reset'`, `'/demo/evil/out-of-scope'`.
- [ ] Sửa template string: `` className={`mt-6 ${card}`} ``, `` className={`mt-3 ${code}`} ``; tìm tất cả
      `"{...}"` còn lại.
- [ ] Sửa điều kiện render: `{(dumpResult || dumpError) && (...)}` cho tất cả 5 section.
- [ ] Log: đọc `entry.event` + `entry.context` (shape issue 07) hoặc `entry.step` nếu chưa đổi; hiển thị
      an toàn, không render HTML.
- [ ] Hiển thị dump: `documents` với `title_ct`, `iv`, `ciphertext_b64` (rút gọn nếu dài), `grants` với
      `bundle_iv`, status, expires_at; ghi rõ «không có plaintext».
- [ ] Nút out-of-scope gọi endpoint demo admin (mục 3), hiện `error.code/message`.
- [ ] Slow-run: poll 1s, dừng khi status terminal; cleanup interval. Hiện `grant_id` + hướng dẫn
      «Đăng nhập Lan để bấm Thu hồi»; **không** thêm quyền admin revoke grant của owner.
- [ ] `npm run build` xanh; không còn warning JSX.

### 3. Backend demo

- [ ] `DocumentRequestController`: sửa `AuditService::record()` đúng 6 tham số:
      `subjectId = null`, `meta = ['reason' => ...]`; vẫn trả `403 OUT_OF_SCOPE`.
- [ ] Thêm `POST /api/v1/demo/evil/out-of-scope` (admin, `APP_DEMO`): gọi cùng logic/ service, audit
      actor `agent` (hoặc `user` kèm meta demo), trả `403 OUT_OF_SCOPE`. `AdminEvil` dùng endpoint này.
- [ ] Giữ `/agent/documents/request` cho role agent; test admin gọi route đó vẫn 403 `FORBIDDEN_ROLE`
      (đúng vai trò), còn nút demo dùng endpoint riêng.
- [ ] `DemoController::slowRun`:
  - Tạo grant active có `expires_at`.
  - Sinh K_g random 32 byte, `base64_encode`, gọi `GrantKeyStore::put($grant, $key, ttl)` **trước khi**
    dispatch job. Không ghi key vào log/response.
  - Dispatch `SlowRunAgentJob` với số bước và sleep giây cấu hình (mặc định 2; test 0).
  - Trả `task_id`, `grant_id`, status.
- [ ] `SlowRunAgentJob`:
  - Nhận `sleepSeconds` (mặc định 2); dùng `usleep($seconds * 1_000_000)` để test nhanh.
  - Log shape thống nhất `{event, at, context}` (issue 07).
  - Kiểm tra task status/key trước mỗi bước; dừng `failed_revoked` khi revoke.
- [ ] `DemoController::reset`:
  - Xoá dữ liệu demo cũ: grants, agent_tasks, documents, change_request_approvals, change_requests của
    user demo; audit_logs (hoặc giữ và ghi event reset); settings quorum về mặc định nếu cần.
  - Xoá file ciphertext/bundle trong storage demo.
  - Chạy `DemoSeeder`; trả số bản ghi đã tạo.
  - Không dùng `migrate:fresh` trên môi trường có dữ liệu thật.
- [ ] `DemoSeeder`:
  - Seed user `lan` với `vault_kdf_salt` cố định (chỉ set nếu null) để browser derive đúng.
  - Tạo 5 file PDF/PNG giả (bytes tối thiểu hợp lệ) trong memory; mã hoá bằng
    `hash_pbkdf2('sha256','password',$salt,210_000,32,true)` + AES-256-GCM; lưu ciphertext vào
    `storage/app/private/documents/demo/...`.
  - `title_ct = base64(iv . ciphertext . tag)`; `iv` base64; `sha256` của plaintext; `size_bytes` của
    plaintext; `enc_mode=fallback`.
  - Idempotent: nếu document đã tồn tại thì cập nhật metadata và ghi đè file; không nhân đôi.
  - Không dùng `base64(title)`.
- [ ] `agentToken`: nếu chưa triển khai auth agent demo, trả `501 NOT_IMPLEMENTED` kèm ghi chú, hoặc
      implement token demo được `VerifyTideJwt`/middleware chấp nhận khi `APP_DEMO=true`. Không trả token
      giả không dùng được.

### 4. Không làm

- Không cho demo đọc plaintext từ server.
- Không nới quyền admin cho các route nghiệp vụ thật (chỉ endpoint `/demo/*`).
- Không xoá dữ liệu ngoài phạm vi demo khi reset.

## Acceptance criteria

- [ ] `/admin/evil` mở được khi `APP_DEMO=true`, 404 khi false.
- [ ] 5 nút gọi đúng URL, không còn `/api/v1/api/v1`.
- [ ] Đổ DB: thấy `title_ct`/`iv`/ciphertext base64; không có plaintext; title không đọc được bằng
      base64.
- [ ] Tự cấp quyền: CR `pending`, tiến độ `1/2`, role chưa đổi.
- [ ] Out-of-scope: UI hiện `OUT_OF_SCOPE`; audit có event; không 500.
- [ ] Slow-run: key có trong store; job chạy; revoke → task `failed_revoked` trong ≤ 5 giây; log dừng
      đúng bước.
- [ ] Reset: 5 tài liệu demo được tạo lại; không còn grant/task cũ; seeder chạy lại không nhân đôi.
- [ ] `npm run build` xanh.

## Test

- [ ] `DemoTest`: dumpDb không chứa plaintext; selfElevate tạo CR pending; out-of-scope trả 403 đúng;
      slowRun có key; reset idempotent.
- [ ] `SlowRunAgentJobTest`: với `sleepSeconds=0`, revoke giữa bước 2 → dừng; log đúng.
- [ ] `DemoSeederTest`: 5 document, `title_ct` không phải base64(title), file tồn tại, giải mã bằng
      passphrase `password` ra đúng bytes.
- [ ] Manual: chạy toàn bộ 5 nút; kiểm tra Network/console.

## File liên quan

- `routes/web.php`, `routes/api.php`.
- `app/Http/Controllers/Api/V1/DemoController.php`.
- `app/Http/Controllers/Api/V1/Agent/DocumentRequestController.php`.
- `app/Jobs/SlowRunAgentJob.php`, `app/Services/GrantKeyStore.php`, `app/Services/AuditService.php`.
- `database/seeders/DemoSeeder.php`.
- `resources/js/components/AdminEvil.jsx`, `resources/js/components/App.jsx`.
- Mới: `resources/views/admin/evil.blade.php`.
- Test: `tests/Feature/Demo/DemoTest.php`, `tests/Feature/Demo/DemoSeederTest.php`.

## Definition of Done

- [ ] 5 nút demo chạy đúng, không lỗi JS/500.
- [ ] Dữ liệu demo là bản mã thật, giải mã được bằng passphrase demo.
- [ ] Slow-run + revoke chứng minh được crypto-shredding.
- [ ] README có kịch bản demo 2 phút và lưu ý `APP_DEMO`.
