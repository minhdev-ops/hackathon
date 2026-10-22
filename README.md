<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## TrustGate – Quorum quản trị (QRM)

Prototype TrustGate (Laravel 12 + React + Vite). Màn quản trị quorum: **`/admin/quorum`**.

### Đăng nhập demo

`php artisan db:seed` tạo sẵn tài khoản (mật khẩu `password`):

| Email | Vai trò |
| --- | --- |
| `admin1@example.com`, `admin2@example.com`, `admin3@example.com` | admin |
| `lan@example.com` | owner |

Đăng nhập tạm bằng session tại `/login` (WP3 sẽ thay bằng TideCloak OIDC). Chỉ user có `role = admin`
mới vào được API/màn quorum (`403 FORBIDDEN_ROLE`).

### Luồng Change Request (FR-QRM-01..03)

1. `POST /api/v1/admin/change-requests` với `{ "type": "...", "payload": { ... } }`.
2. CR lưu ở `pending`, `required_approvals` lấy từ `QUORUM_THRESHOLD` (mặc định 2).
3. Admin xác thực lại (`POST /api/v1/admin/reauth`) rồi duyệt: `POST /api/v1/admin/change-requests/{id}/approve`.
4. Đủ ngưỡng: server khoá dòng `FOR UPDATE`, thực thi payload trong **một transaction** và đặt `executed`.
   Đủ ngưỡng từ chối thì `rejected`.
5. Mỗi admin chỉ bỏ phiếu một lần — ràng buộc `unique(change_request_id, admin_id)`.

Loại CR: `role_grant`, `role_revoke`, `playbook_update`, `agent_allowlist_update`,
`quorum_threshold_update`. Gọi thẳng API đổi trực tiếp (`/api/v1/admin/direct/*`) luôn trả
`409 QUORUM_REQUIRED`.

`GET /api/v1/admin/quorum/meta` trả ngưỡng hiện tại, trạng thái QEA, hạn re-auth và dữ liệu cho form.
`GET /api/v1/admin/change-requests?status=pending` trả danh sách CR kèm tiến độ duyệt.

### FR-QRM-04 – Xác thực lại (fallback khi V5 chưa đạt)

Chưa có chữ ký TideCloak, nên mỗi phiếu **duyệt** yêu cầu nhập lại mật khẩu trong vòng 5 phút
(`QUORUM_REAUTH_MINUTES`). Cột `change_request_approvals.signature` để sẵn cho V5; khi V5 đạt, lưu
chữ ký và kiểm tra trong `ChangeRequestExecutor` trước khi thực thi.

### FR-QRM-05 – Cấu hình QEA cho thay đổi admin của realm

Khi V4 đạt (QEA bảo vệ thay đổi quyền admin của realm), thực hiện:

1. Vào TideCloak admin console → realm TrustGate → phần Authorization/QEA → bật QEA cho nhóm
   `admin` và cho thao tác gán/thu hồi role admin của realm.
2. Cấu hình backend: `TIDECLOAK_URL`, `TIDECLOAK_REALM`, `TIDECLOAK_CLIENT_ID`, đặt
   `TIDECLOAK_QEA_ENABLED=true`.
3. Màn `/admin/quorum` hiển thị badge trạng thái QEA lấy từ `/api/v1/admin/quorum/meta`
   (`qea.enabled`, `qea.label`, `qea.note`). Khi bật, badge chuyển sang «QEA: bật (TideCloak realm)».
4. Khi V4/V5 được xác minh, bật kiểm tra chữ ký ở bước thực thi và có thể bỏ re-auth.

> Prototype chưa xác minh V4/V5 (SRS mục 2.3), mặc định dùng quorum mức app + re-auth. Ngưỡng có thể
> đổi bằng CR `quorum_threshold_update`; giá trị lưu ở bảng `settings` (không cần sửa `.env`).

## TrustGate – TASK & AGENT (giao việc cho agent)

### Luồng

1. **FR-TASK-01** — Owner `POST /api/v1/tasks` với `{ "playbook_slug": "rental", "instruction": "Nộp hồ sơ thuê căn hộ A" }`.
   Task lưu ở `proposing`, audit `task.created`, và `GenerateProposalJob` được đẩy vào queue.
2. **FR-AGENT-01** — `GenerateProposalJob` gọi `LlmClient` sinh đề xuất
   `{ required: [doc_type], optional: [doc_type], rationale: string }`, **cắt về `required` +
   `optional` của playbook trước khi lưu** (loại lạ/`forbidden_doc_types` bị bỏ), rồi tạo grant
   `pending` để Owner duyệt ở màn consent (`/api/v1/grants/{id}/approve`).
3. **FR-AGENT-02** — Khi grant `active`, `RunAgentJob` mở grant, giải bundle, soạn «hồ sơ nộp»
   (mock: ghép trường đã trích; Anthropic: tóm tắt) và lưu vào `agent_tasks.result`. Mỗi bước ghi
   một dòng vào `agent_tasks.log` để Agent Console hiển thị.
4. **FR-AGENT-03** — Grant bị thu hồi giữa chừng: job kiểm tra grant/key trước **mỗi** bước, dừng
   ở bước kế tiếp với `status = failed_revoked`, log `run.stopped` kèm `reason` (`grant_revoked`,
   `grant_key_shredded`, `grant_not_active`, …).
5. **FR-AGENT-04** — Demo agent xin tài liệu ngoài phạm vi:
   `POST /api/v1/agent/documents/request` (role `agent`) → `403 OUT_OF_SCOPE` + audit
   `agent.document_request.out_of_scope`. Owner gọi endpoint này nhận `403 FORBIDDEN_ROLE`.

Xem task: `GET /api/v1/tasks` (chỉ task của mình) và `GET /api/v1/tasks/{id}` (task người khác → `403`).

### LLM driver & queue

- Mặc định `LLM_DRIVER=mock`: `MockLlmClient` suy ra đề xuất từ playbook, **không gọi mạng**.
- `LLM_DRIVER=anthropic` + `ANTHROPIC_API_KEY` (+ tuỳ chọn `ANTHROPIC_MODEL`):
  `AnthropicLlmClient` gọi Messages API. Lỗi provider khi chạy job được fallback về mock cho phần
  soạn hồ sơ; riêng bước đề xuất sẽ làm job fail và task giữ `proposing` để retry.
- `QUEUE_CONNECTION=database` cần worker. Chạy song song khi demo:

```bash
php artisan queue:work --tries=1
```

Đặt `QUEUE_CONNECTION=sync` nếu muốn job chạy ngay trong request (test/demo nhanh).
`AGENT_STEP_DELAY_MS` (mặc định 200) là độ trễ mỗi bước của `RunAgentJob`.

## TrustGate – AUDIT hash-chain & Timeline (FR-AUD-01..03)

- Mỗi sự kiện nhạy cảm được ghi append-only vào `audit_logs` với
  `hash = sha256(prev_hash + canonical_json(payload))`. Sửa/xoá một dòng trực tiếp trong DB sẽ làm
  chuỗi đứt và bị `GET /api/v1/audit/verify` phát hiện.
- `GET /api/v1/audit?action=&subject_type=&before_id=&limit=` (`owner`, `admin`):
  - Owner chỉ thấy sự kiện do mình tạo hoặc gắn với document/task/grant của mình.
  - Admin thấy toàn bộ; `limit` mặc định 50, tối đa 200; `before_id` dùng cho nút «Tải thêm».
  - `meta` được lọc bỏ `content_b64`, `grant_key*`, token, title, instruction, …
- `GET /api/v1/audit/verify`: kiểm tra toàn bộ chuỗi theo `id ASC`, trả `valid`, `checked`,
  `broken_at`, `reason` (`hash_mismatch` | `prev_hash_mismatch`). Owner cũng gọi được nhưng chỉ nhận
  kết quả integrity toàn cục, không nhận meta.
- Màn `/timeline` (guard `owner,admin`) nhóm sự kiện theo ngày, hiển thị câu FR-AUD-03 dạng
  «Agent TrustGate đã mở 3 tài liệu lúc …, hết hạn lúc …», có filter hành động/đối tượng, «Tải thêm»
  và nút «Xác minh chuỗi».

**Giới hạn:** hash-chain là toàn cục và không có chữ ký số, nên chỉ phát hiện sửa đổi chứ không
ngăn được người có quyền ghi DB xoá cả chuỗi hoặc tính lại toàn bộ hash. Owner xem subset nhưng verify
toàn cục — muốn verify riêng cần thêm `owner_id`/chain riêng (ngoài phạm vi prototype).

### Kiểm thử

```bash
php artisan test --filter=QuorumTest
php artisan test --filter=TaskAgentTest
php artisan test --filter=Audit
npm run test:js
npm run build
```
