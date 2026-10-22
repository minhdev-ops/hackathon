# TrustGate — Danh sách issue còn lại

> **Ảnh chụp lúc:** 22/10/2026 14:00 (Asia/Ho_Chi_Minh)
> **SRS chuẩn:** `document/SRS – TrustGate (TideCloak Hackathon)(1).md` (bản mới nhất, có mục 13).
> **Lưu ý:** repo đang được một agent khác sửa song song. Trước khi nhận issue, chạy `git status`,
> `php artisan route:list` và đọc lại các file trong mục «File liên quan». Nếu việc đã xong, đóng issue
> kèm bằng chứng (test/build/screenshot), không làm lại từ đầu.

Mỗi file là một work package độc lập, có thể giao cho một agent. Thứ tự dưới đây là thứ tự đề xuất,
không bắt buộc tuyệt đối; các issue backend có thể chạy song song sau khi issue 00 và 11 được xử lý.

## Bảng issue

| ID | Issue | SRS / WP | Ưu tiên | Phụ thuộc | Trạng thái hiện tại |
| --- | --- | --- | --- | --- | --- |
| 00 | [Xác minh TideCloak V1–V7](00-wp0-tidecloak-verification.md) | §2.3, §12, §13.3 / WP0 | P0 — cổng quyết định | không | Chưa điền mục 12 |
| 01 | [AUTH backend — JWT/JWKS, role, /me](01-auth-backend.md) | FR-AUTH-01..04 / WP2 | P0 | 00 | Chưa có; đang dùng session |
| 02 | [AUTH frontend — login, guard, Bearer](02-auth-frontend.md) | FR-AUTH-01..03 / WP3 | P1 | 01 | Chưa có; `/login` là Blade |
| 03 | [VAULT backend — upload/list/download/delete](03-vault-backend.md) | FR-VAULT-01..05 / WP5 | P0 | 01 (có thể làm trước với session) | Một phần: model/migration/factory có, API chưa có |
| 04 | [VAULT frontend + CryptoProvider](04-vault-frontend.md) | FR-VAULT-01..05, §3.4 / WP4+WP6 | P0 | 03 | Chưa có |
| 05 | [PLAYBOOK — API, seed, phạm vi](05-playbooks.md) | FR-PB-01..03 / WP7 | P1 | 01 | Một phần: model/migration/seeder có, API chưa có |
| 06 | [TASK + PROPOSAL — hoàn thiện backend](06-tasks-proposal.md) | FR-TASK-01, FR-AGENT-01 / WP8 | P0 | 01, 05 | Một phần: controller/job/LLM vừa được thêm, còn lỗi wiring |
| 07 | [AGENT RUN + CONSOLE](07-agent-run-console.md) | FR-AGENT-02..03 / WP8+WP10 | P1 | 06, 08 | Một phần: `RunAgentJob` có, UI/test chưa có |
| 08 | [GRANT CONSENT UI + bundle](08-grant-consent-ui.md) | FR-GRANT-01..06 / WP9+WP10 | P0 | 03, 04, 06 | Một phần: backend grant có, frontend chưa có |
| 09 | [AUDIT — API, verify, timeline](09-audit-timeline.md) | FR-AUD-01..03 / WP13 | P1 | 03, 06 | Một phần: service + một số event có, API/UI chưa có |
| 10 | [DEMO — sửa lỗi «Làm admin xấu»](10-demo-fixes.md) | FR-DEMO-01..05 / WP14 | P1 | 08, 09 | Một phần: controller/UI có nhưng nhiều bug |
| 11 | [Hạ tầng, test, README, E2E](11-infra-tests-docs.md) | NFR-SEC/PERF/REL/TEST, §9 / WP1+WP15 | P0 — chặn test | không | Chưa có; `pdo_sqlite` đang thiếu |

## Thứ tự thực hiện đề xuất

1. **00** — chốt V1–V7 và chế độ `tide`/`fallback`. Đây là cổng: nếu V1 và V4 đều không đạt, phải
   chốt lại trọng tâm demo trước khi code thêm.
2. **11 (phần unblock)** — cài `pdo_sqlite` (hoặc đổi test DB), chạy được `php artisan test`,
   `npm run build`, có queue worker. Không ai có thể nghiệm thu nếu test không chạy.
3. **01 + 03 + 05 + 06** — backend nền: auth, vault API, playbook API, task/proposal.
4. **04 + 02** — CryptoProvider/Vault UI và login/guard. Ưu tiên Vault vì là lõi sản phẩm.
5. **08 + 07** — Consent Review và Agent Console/Task detail.
6. **09 + 10** — audit/timeline và demo «Làm admin xấu».
7. **11 (phần còn lại)** — E2E Playwright, README, NFR.

## Trạng thái hiện tại (tóm tắt)

- **Đã có:** session auth + role middleware; QRM backend/UI; grant lifecycle backend; grant bundle
  AES-256-GCM; `AuditService` hash-chain; document model/migration/factory; playbook model/migration/
  seeder; agent/task/LLM/job vừa được thêm; demo controller + AdminEvil UI.
- **Chưa có:** TideCloak JWT/OIDC; `/me`; Vault API/UI; Playbook API/UI; Consent Review; Task detail/
  Agent Console; Audit API/verify/Timeline; route `/admin/evil`; CryptoProvider; test suite đầy đủ.
- **Đang lỗi/chặn:**
  - `php artisan test` không chạy: thiếu driver `pdo_sqlite` (`could not find driver`), dù `phpunit.xml`
    dùng `sqlite :memory:`.
  - `routes/api.php` dùng `TasksController` nhưng thiếu `use App\Http\Controllers\Api\V1\TasksController;`
    → route `/tasks` sẽ lỗi class not found.
  - `App\Llm\LlmClient` chưa được bind trong container → `GenerateProposalJob` không resolve được.
  - `DocumentRequestController` gọi `AuditService::record()` sai signature (truyền array vào
    `subject_id`) → 500; route lại yêu cầu `role:agent` nên admin demo nhận 403 `FORBIDDEN_ROLE`.
  - `DemoController::slowRun` không đưa K_g vào `GrantKeyStore` → `SlowRunAgentJob` dừng ngay với
    `grant_key_shredded`.
  - `AdminEvil.jsx` dùng baseURL `/api/v1` cộng thêm `/api/v1/demo` → gọi sai `/api/v1/api/v1/demo/...`;
    một số `className` viết nhầm `"mt-6 {card}"`; một số điều kiện render sai thứ tự toán tử.
  - `DemoSeeder` đặt `title_ct = base64(title)` và ciphertext ngẫu nhiên → không phải bản mã thật, demo
    «đổ database» vẫn lộ tên dạng base64.

## Definition of Done chung

Một issue chỉ được đóng khi:

- [ ] Code chạy được trên nhánh sạch, không conflict với phần đang có.
- [ ] `php artisan route:list` không lỗi; route mới đúng prefix `/api/v1`.
- [ ] Test liên quan xanh; nếu chưa thể chạy toàn bộ, ghi rõ lệnh và kết quả từng test.
- [ ] `npm run build` xanh (nếu có thay đổi frontend).
- [ ] Không có plaintext, K_g, access token trong log/audit/response không cần thiết.
- [ ] Mọi lỗi API trả đúng dạng `{ "error": { "code", "message", "details?" } }`.
- [ ] README hoặc tài liệu liên quan được cập nhật nếu hành vi/cấu hình thay đổi.
- [ ] Có bằng chứng: test output, screenshot, hoặc curl request/response.

## Quy ước

- Không sửa trực tiếp dữ liệu test bằng SQL nếu có thể dùng factory/seeder.
- Không commit `.env`, khoá, token, hay file ciphertext thật.
- Mọi thay đổi schema phải có migration mới; không sửa migration đã chạy trên môi trường chung.
- Nếu phải chọn giữa «đúng SRS» và «chạy được demo», ghi rõ quyết định và lý do trong issue.
