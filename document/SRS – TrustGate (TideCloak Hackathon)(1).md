# SRS – TrustGate: Két dữ liệu và Consent Broker cho AI Agent

Phiên bản 0.1 · 22/10/2026 · Stack: Laravel 11 (PHP 8.3) + React 18 + Vite + TypeScript · Danh tính, khoá và quorum: TideCloak

## 1. Giới thiệu

### 1.1 Mục đích

Tài liệu mô tả yêu cầu đủ chi tiết để nhóm và các AI coding agent triển khai prototype cho hackathon. Mỗi yêu cầu có mã (FR-…, NFR-…) để giao việc, viết test và nghiệm thu. Phần 10 chia sẵn work package theo thứ tự phụ thuộc.

### 1.2 Phạm vi

TrustGate là web app cho phép người dùng cất giấy tờ nhạy cảm vào két mã hoá. AI agent làm thủ tục thay người dùng nhưng chỉ nhận đúng phần được duyệt, cho đúng mục đích, trong thời hạn nhất định, và quyền này thu hồi được. Mọi thay đổi quản trị (vai trò, playbook, danh sách agent) cần quorum admin duyệt.

Prototype dùng dữ liệu giả và demo 2 playbook khác lĩnh vực: **Thuê nhà** và **Khám bệnh**, chạy trên cùng một lõi.

**Ngoài phạm vi:** thanh toán, app di động, eKYC thật, tuân thủ pháp lý (HIPAA/GDPR), chia sẻ cho người thứ ba qua link công khai, khôi phục khẩn cấp qua người thân (chỉ làm nếu xác minh được, xem V7).

### 1.3 Thuật ngữ

| Thuật ngữ | Ý nghĩa |
| --- | --- |
| Owner | Người dùng sở hữu tài liệu |
| Vault | Két tài liệu đã mã hoá của Owner |
| Playbook | Khai báo một lĩnh vực: cần loại tài liệu nào, cho bên nhận nào, TTL tối đa |
| Agent | Tác nhân AI được phép nhận grant |
| Grant | Quyền có phạm vi (tài liệu), mục đích, hạn dùng, thu hồi được |
| Bundle | Gói các tài liệu đã duyệt, mã hoá bằng khoá riêng của grant |
| Grant key (K\_g) | Khoá AES-256 ngẫu nhiên của một grant, có TTL, bị xoá khi thu hồi |
| Change Request (CR) | Yêu cầu đổi cấu hình quản trị, cần quorum |
| Quorum | Ngưỡng admin phải duyệt (mặc định 2 trên 3) |
| CryptoProvider | Lớp trừu tượng mã hoá: chế độ `tide` hoặc `fallback` |

## 2. Tổng quan sản phẩm

### 2.1 Vấn đề

Agent AI sắp lo thủ tục thay người dùng (thuê nhà, khám bệnh, học bổng). Muốn làm được, agent cần giấy tờ nhạy cảm, và hiện chỉ có hai lựa chọn: đưa hết hoặc không dùng. Bên vận hành app cũng thường đọc được toàn bộ dữ liệu. TrustGate giải quyết: dữ liệu nằm trong két mà bên vận hành không đọc được, agent chỉ nhận phần đã duyệt, và một admin xấu không tự mở rộng quyền được.

### 2.2 Người dùng

| Vai trò | Mô tả | Mục tiêu |
| --- | --- | --- |
| Owner (Lan, sinh viên) | Dùng két và nhờ agent | Giao việc mà không phải đưa hết giấy tờ |
| Agent | Tác nhân AI (dịch vụ) | Hoàn thành thủ tục với phạm vi tối thiểu |
| Admin (3 người) | Quản trị app, nằm trong nhóm quorum | Đổi cấu hình an toàn |
| Verifier (chủ nhà, phòng khám) | Bên nhận hồ sơ cuối | Ngoài prototype: chỉ là nhãn trong playbook |

### 2.3 Giả định cần xác minh với Tide (làm ở WP0, trước khi code)

Tài liệu công khai chưa xác nhận các điểm sau. Mỗi điểm có phương án dự phòng để dự án không bị chặn. Kết quả xác minh ghi vào mục 12.

| Mã | Cần xác minh | Nếu không có thì |
| --- | --- | --- |
| V1 | SDK TypeScript mã hoá/giải mã dữ liệu tuỳ ý trong trình duyệt (encrypt/decrypt qua MPC) | Dùng `fallback`: WebCrypto AES-GCM, khoá người dùng bọc bằng passphrase (PBKDF2); UI hiện banner «Chế độ dự phòng, không trustless» |
| V2 | `@tidecloak/react` chạy trong Vite, claims của access token (sub, realm\_access.roles) | Dùng OIDC chuẩn qua `oidc-client-ts`, cùng JWKS |
| V3 | Dùng quyền/vai trò Tide để bảo vệ K\_g | K\_g nằm ở Redis có TTL (xem 3.3) |
| V4 | Cấu hình QEA cho thay đổi admin của realm và cách app đọc trạng thái | Chỉ dùng quorum mức app (bảng change\_requests) |
| V5 | Gọi Sign/Authenticate để tạo chữ ký phê duyệt cho CR | Lưu phê duyệt kèm xác thực lại bằng đăng nhập, không có chữ ký mật mã |
| V6 | Service account/client riêng cho agent trong TideCloak | Token agent demo do backend cấp khi `APP_DEMO=true` |
| V7 | Mở khoá khẩn cấp có người thân duyệt ở mức người dùng cuối | Bỏ khỏi prototype |

### 2.4 Bảng nói thật (dùng lại trong README)

| TideCloak làm | Nhóm tự xây |
| --- | --- |
| Đăng nhập BYOiD, mật khẩu không rời trình duyệt | Két tài liệu, định dạng gói, UI |
| Khoá phân tán, không máy nào giữ khoá đầy đủ (V1) | Mô hình grant, TTL, thu hồi bằng xoá K\_g |
| QEA cho thay đổi quyền admin của realm (V4) | Change Request mức app, hash-chain audit |
| OIDC/JWT để Laravel xác thực | Playbook, agent adapter, demo «Làm admin xấu» |

**Nguyên tắc phát biểu:** không nói «AI không bao giờ thấy dữ liệu». Nói: agent chỉ thấy phần Owner đã duyệt, trong hạn grant; phần còn lại bên vận hành chỉ thấy bản mã.

## 3. Kiến trúc

### 3.1 Thành phần

```
Browser (React+Vite)
  ├─ TideCloak login (OIDC)
  ├─ CryptoProvider: encrypt/decrypt tại trình duyệt
  └─ gọi REST /api/v1 (Bearer JWT)
Laravel 11 API
  ├─ Middleware VerifyTideJwt (JWKS) + RequireRole
  ├─ Services: Vault, Grant, Agent, Quorum, Audit
  ├─ Queue worker (Redis): GenerateProposalJob, RunAgentJob
  └─ LlmClient: MockLlmClient (mặc định), AnthropicLlmClient (tuỳ chọn)
PostgreSQL: metadata + bản mã     Redis: queue + K_g có TTL
TideCloak: danh tính, vai trò, (khoá, quorum nếu V1/V4 đạt)
```

### 3.2 Ranh giới tin cậy

- Trình duyệt Owner là nơi duy nhất thấy plaintext toàn bộ tài liệu.
- Database chỉ chứa bản mã và metadata. Tên tài liệu được mã hoá; chỉ `doc_type` ở dạng rõ để khớp playbook.
- Server chỉ giữ K\_g của grant đang hiệu lực. Hết hạn hoặc thu hồi thì K\_g bị xoá (crypto-shredding), bundle không còn mở được.

### 3.3 Luồng Release Bundle (lõi của sản phẩm)

1. Owner tạo task. Server chạy agent lập đề xuất: cần những `doc_type` nào theo playbook.
2. Màn duyệt hiện danh sách tài liệu khớp. Owner chọn tài liệu và TTL.
3. Khi bấm Duyệt, **trình duyệt** giải mã đúng các tài liệu đã chọn bằng khoá của Owner, sinh K\_g (AES-256-GCM), mã hoá thành bundle và gửi bundle cùng K\_g lên server.
4. Server lưu bundle vào DB, lưu K\_g vào Redis với TTL bằng thời hạn grant, ghi audit.
5. Agent mở grant: server lấy K\_g từ Redis, giải bundle trong bộ nhớ, đưa nội dung cho agent, ghi audit mỗi lần mở.
6. Hết hạn hoặc Owner thu hồi: server xoá K\_g. Lần mở tiếp theo trả `410 GRANT_UNAVAILABLE`.

Hệ quả cần ghi trong README: trong thời hạn grant, server có thể đọc phần đã duyệt. Nếu V3 đạt, bảo vệ K\_g bằng cơ chế Tide để giảm quyền này.

### 3.4 CryptoProvider (frontend, `src/crypto/`)

```ts
interface CryptoProvider {
  mode: 'tide' | 'fallback';
  encryptDocument(file: ArrayBuffer): Promise<{ciphertext: ArrayBuffer; iv: Uint8Array; sha256: string}>;
  decryptDocument(ciphertext: ArrayBuffer, iv: Uint8Array): Promise<ArrayBuffer>;
  encryptText(text: string): Promise<string>;   // tên tài liệu
  decryptText(b64: string): Promise<string>;
}
```

Các helper chung (không phụ thuộc Tide): `createGrantBundle(docs) → {bundle, iv, keyB64}` dùng WebCrypto AES-GCM 256, IV 12 byte ngẫu nhiên, mỗi bundle một khoá mới.

## 4. Yêu cầu chức năng

Ưu tiên: **M** = bắt buộc cho demo, **S** = nên có, **C** = có thì tốt.

### 4.1 AUTH – Xác thực và phân quyền

- **FR-AUTH-01 (M)** Đăng nhập qua TideCloak. AC: chưa đăng nhập thì mọi route trừ `/login` chuyển về trang đăng nhập; sau đăng nhập có access token trong bộ nhớ (không localStorage).
- **FR-AUTH-02 (M)** Laravel xác thực JWT bằng JWKS của realm (cache 10 phút), kiểm tra `iss`, `aud`/`azp`, `exp`. AC: token sai chữ ký, hết hạn hoặc sai issuer trả `401`.
- **FR-AUTH-03 (M)** Vai trò lấy từ `realm_access.roles`: `owner`, `admin`, `agent`. Middleware `RequireRole:<role>`. AC: sai vai trò trả `403` mã `FORBIDDEN_ROLE`.
- **FR-AUTH-04 (M)** Lần đầu đăng nhập tạo bản ghi `users` theo `sub`. AC: gọi `GET /me` hai lần chỉ có một user.

### 4.2 VAULT – Két tài liệu

- **FR-VAULT-01 (M)** Tải lên tài liệu (PDF/PNG/JPG, tối đa 10 MB). Mã hoá tại trình duyệt trước khi gửi. AC: request chỉ chứa bản mã, không có plaintext; server lưu `ciphertext_path`, `iv`, `sha256`, `doc_type`, `title_ct`, `enc_mode`.
- **FR-VAULT-02 (M)** Danh sách tài liệu của Owner, hiện tên đã giải mã tại trình duyệt, loại, ngày tải. AC: Owner khác không thấy và không tải được (403).
- **FR-VAULT-03 (M)** Xem tài liệu: tải bản mã, giải mã trong trình duyệt, hiển thị bằng Blob URL. AC: sai khoá thì báo lỗi giải mã, không crash.
- **FR-VAULT-04 (S)** Xoá tài liệu (soft delete) và chặn dùng trong grant mới. Grant đang chạy vẫn giữ bundle cho đến khi hết hạn.
- **FR-VAULT-05 (M)** Banner «Chế độ dự phòng, không trustless» khi `enc_mode=fallback`.

### 4.3 PLAYBOOK – Lĩnh vực

- **FR-PB-01 (M)** Bảng `playbooks` có: slug, tên, mô tả, `required_doc_types`, `optional_doc_types`, `forbidden_doc_types`, nhãn bên nhận, TTL mặc định và tối đa. Seed 2 playbook ở Phụ lục A.
- **FR-PB-02 (M)** `GET /playbooks` cho Owner xem. Admin chỉ sửa playbook qua Change Request (FR-QRM-01).
- **FR-PB-03 (M)** Quy tắc cứng: grant không được chứa `doc_type` thuộc `forbidden_doc_types` hoặc ngoài `required` + `optional`. AC: server trả `422 OUT_OF_SCOPE` dù client cố gửi.

### 4.4 TASK và AGENT – Giao việc

- **FR-TASK-01 (M)** Owner tạo task: chọn playbook, nhập chỉ dẫn tự do (ví dụ «Nộp hồ sơ thuê căn hộ A»). AC: task trạng thái `proposing`, job `GenerateProposalJob` được đẩy vào queue.
- **FR-AGENT-01 (M)** `GenerateProposalJob` tạo đề xuất JSON: `{required: [doc_type], optional: [doc_type], rationale: string}`. `MockLlmClient` suy ra từ playbook (không gọi mạng). `AnthropicLlmClient` bật bằng `LLM_DRIVER=anthropic`. Đề xuất bị cắt về phạm vi playbook trước khi lưu. AC: dù LLM trả loại ngoài playbook, đề xuất lưu xuống không chứa nó.
- **FR-AGENT-02 (M)** `RunAgentJob` chạy khi grant `active`: mở grant, soạn «hồ sơ nộp» (mock: ghép các trường đã trích; LLM: tóm tắt) và lưu kết quả vào `agent_tasks.result`. Mỗi bước ghi vào luồng log để Agent Console hiển thị.
- **FR-AGENT-03 (M)** Nếu giữa chừng grant bị thu hồi, job dừng ở bước kế tiếp với trạng thái `failed_revoked` và log rõ lý do.
- **FR-AGENT-04 (M)** Endpoint mô phỏng agent xin tài liệu ngoài phạm vi (cho demo): `POST /agent/documents/request`. AC: trả `403 OUT_OF_SCOPE` và ghi audit.

### 4.5 GRANT – Duyệt, hạn dùng, thu hồi

- **FR-GRANT-01 (M)** Màn duyệt hiện: agent, mục đích, danh sách tài liệu đề xuất kèm checkbox, chọn TTL (5, 15, 60 phút, 24 giờ; không vượt TTL tối đa của playbook). Owner có thể bỏ tài liệu tuỳ chọn.
- **FR-GRANT-02 (M)** `POST /grants/{id}/approve` nhận danh sách `doc_ids`, `ttl_minutes`, file bundle, `bundle_iv`, `grant_key_b64`. Server kiểm tra quyền sở hữu, phạm vi playbook, TTL. AC: ok thì grant `active`, K\_g vào Redis với TTL, bản ghi `expires_at` đúng.
- **FR-GRANT-03 (M)** `POST /grants/{id}/revoke` xoá K\_g, đặt `revoked`, ghi audit. AC: sau thu hồi, `POST /agent/grants/{id}/open` trả `410`; task đang chạy chuyển `failed_revoked` trong tối đa 5 giây.
- **FR-GRANT-04 (M)** Hết hạn: Redis tự xoá K\_g; lệnh schedule mỗi phút đặt grant quá hạn sang `expired`. AC: mở sau hạn trả `410`.
- **FR-GRANT-05 (S)** Đồng hồ đếm ngược trên màn grant, tự cập nhật.
- **FR-GRANT-06 (M)** `POST /grants/{id}/deny` đặt `denied`. Agent không mở được.

### 4.6 QRM – Quorum quản trị

- **FR-QRM-01 (M)** Các hành động cần Change Request: `role_grant`, `role_revoke`, `playbook_update`, `agent_allowlist_update`, `quorum_threshold_update`. Gọi thẳng API đổi trực tiếp phải trả `409 QUORUM_REQUIRED`.
- **FR-QRM-02 (M)** Tạo CR ở trạng thái `pending`, `required_approvals` lấy từ cấu hình (mặc định 2). Mỗi admin duyệt tối đa một lần; người tạo cũng được tính nhưng một người không duyệt hai lần. AC: ràng buộc unique `(change_request_id, admin_id)`.
- **FR-QRM-03 (M)** Khi đủ phê duyệt, server thực thi payload trong một transaction và đặt `executed`. Từ chối đủ ngưỡng thì `rejected`. AC: không thể thực thi hai lần (khoá dòng `FOR UPDATE`).
- **FR-QRM-04 (S)** Nếu V5 đạt, mỗi phê duyệt lưu chữ ký và server kiểm tra khi thực thi. Nếu không, bắt buộc xác thực lại (re-login trong 5 phút) trước khi duyệt.
- **FR-QRM-05 (S)** Nếu V4 đạt, README hướng dẫn cấu hình QEA cho thay đổi admin của realm và màn Admin hiện trạng thái.

### 4.7 AUDIT – Nhật ký

- **FR-AUD-01 (M)** Mọi sự kiện: tải tài liệu, mở tài liệu, tạo/duyệt/thu hồi/hết hạn grant, agent mở/bị từ chối, tạo/duyệt CR. Mỗi dòng có `prev_hash` và `hash = sha256(prev_hash + payload_json)`.
- **FR-AUD-02 (M)** `GET /audit` (Owner thấy của mình, Admin thấy tất cả). `GET /audit/verify` kiểm tra chuỗi hash. AC: sửa một dòng trực tiếp trong DB thì verify trả vị trí đứt chuỗi.
- **FR-AUD-03 (S)** Màn Timeline cho Owner: «Agent X đã mở 3 tài liệu lúc …, hết hạn lúc …».

### 4.8 DEMO – «Làm admin xấu» (chỉ bật khi `APP_DEMO=true`)

- **FR-DEMO-01 (M)** Nút **Đổ database**: `POST /demo/evil/dump-db` trả các hàng thô của `documents`, `grants`. AC: UI hiển thị `ciphertext`, `iv`, `title_ct` dạng base64; không có plaintext nào.
- **FR-DEMO-02 (M)** Nút **Tự cấp quyền**: tạo CR `role_grant` cho chính admin đó. AC: UI hiện «Chờ quorum 1/2», quyền chưa đổi.
- **FR-DEMO-03 (M)** Nút **Agent xin tài liệu ngoài phạm vi**: gọi FR-AGENT-04, hiện lỗi `OUT_OF_SCOPE`.
- **FR-DEMO-04 (M)** Nút **Thu hồi giữa chừng**: bắt đầu một `RunAgentJob` chậm (mỗi bước 2 giây), Owner thu hồi, agent hỏng ngay. Log hiển thị từng bước theo thời gian thực (polling 1 giây hoặc SSE).
- **FR-DEMO-05 (M)** Nút **Đặt lại dữ liệu demo** chạy seeder.

## 5. Mô hình dữ liệu (PostgreSQL)

Khoá chính UUID. Mọi bảng có `created_at`, `updated_at`.

| Bảng | Cột chính |
| --- | --- |
| users | id, tide\_sub (unique), display\_name, created\_at |
| documents | id, owner\_id (fk), doc\_type (enum: id\_card, income\_proof, rental\_history, reference\_letter, insurance\_card, medical\_summary, lab\_result), title\_ct (text), ciphertext\_path, iv (text), sha256, size\_bytes, enc\_mode (tide/fallback), deleted\_at |
| playbooks | id, slug (unique), name, description, required\_doc\_types (jsonb), optional\_doc\_types (jsonb), forbidden\_doc\_types (jsonb), recipient\_label, default\_ttl\_minutes, max\_ttl\_minutes, version, active |
| agents | id, name, client\_id (unique), allowed\_playbooks (jsonb), status (active/disabled) |
| agent\_tasks | id, owner\_id, agent\_id, playbook\_id, instruction (text), status (proposing/proposal\_ready/awaiting\_approval/running/done/failed\_revoked/denied), proposal (jsonb), result (jsonb), log (jsonb) |
| grants | id, task\_id, owner\_id, agent\_id, purpose, doc\_ids (jsonb), status (pending/active/revoked/expired/denied), approved\_at, expires\_at, revoked\_at, bundle\_path, bundle\_iv |
| change\_requests | id, type, payload (jsonb), requested\_by, status (pending/executed/rejected), required\_approvals, executed\_at |
| change\_request\_approvals | id, change\_request\_id, admin\_id, decision (approve/reject), signature (nullable), unique (change\_request\_id, admin\_id) |
| audit\_logs | id (bigserial), actor\_type, actor\_id, action, subject\_type, subject\_id, meta (jsonb), prev\_hash, hash |

Redis: `grantkey:{grant_id}` → K\_g (base64), TTL = thời hạn grant. Không bao giờ ghi K\_g vào DB hoặc log.

## 6. Đặc tả API (`/api/v1`, JSON, Bearer JWT)

Lỗi trả dạng `{ "error": { "code": "STRING", "message": "…" } }`.

| Method + path | Vai trò | Mô tả | Mã lỗi chính |
| --- | --- | --- | --- |
| GET /me | any | Thông tin user, vai trò | 401 |
| POST /documents | owner | multipart: file (bản mã), iv, sha256, doc\_type, title\_ct, enc\_mode | 422 |
| GET /documents | owner | Danh sách của chính mình |  |
| GET /documents/{id}/ciphertext | owner | Tải bản mã | 403, 404 |
| DELETE /documents/{id} | owner | Soft delete | 403 |
| GET /playbooks | owner, admin | Danh sách playbook |  |
| POST /tasks | owner | {playbook\_slug, instruction} | 422 |
| GET /tasks, GET /tasks/{id} | owner | Trạng thái, đề xuất, log, kết quả | 403 |
| GET /grants/{id} | owner | Chi tiết grant, thời gian còn lại | 403 |
| POST /grants/{id}/approve | owner | {doc\_ids, ttl\_minutes, grant\_key\_b64, bundle\_iv} + file bundle | 422 OUT\_OF\_SCOPE, 409 |
| POST /grants/{id}/deny | owner | Từ chối |  |
| POST /grants/{id}/revoke | owner | Thu hồi, xoá K\_g | 409 nếu không active |
| POST /agent/grants/{id}/open | agent | Mở grant, trả nội dung đã giải | 410 GRANT\_UNAVAILABLE |
| POST /agent/documents/request | agent | {task\_id, doc\_type} | 403 OUT\_OF\_SCOPE |
| GET /admin/change-requests | admin | Danh sách CR |  |
| POST /admin/change-requests | admin | {type, payload} | 422 |
| POST /admin/change-requests/{id}/approve | admin | Duyệt (kèm signature nếu có) | 409 ALREADY\_VOTED |
| POST /admin/change-requests/{id}/reject | admin | Từ chối | 409 |
| GET /audit, GET /audit/verify | owner, admin | Nhật ký, kiểm tra chuỗi hash |  |
| POST /demo/evil/dump-db | admin | Dump thô (APP\_DEMO) | 404 khi tắt demo |
| POST /demo/evil/self-elevate | admin | Tạo CR tự cấp quyền |  |
| POST /demo/evil/slow-run | admin | Chạy agent chậm cho demo thu hồi |  |
| POST /demo/reset | admin | Seed lại |  |
| POST /demo/agent-token | admin | Token agent demo nếu V6 không đạt |  |

Rate limit: 60 request/phút/user, riêng `/agent/*` 30 request/phút.

## 7. Giao diện React (Vite + TypeScript)

Thư viện: React Router, TanStack Query, Tailwind CSS, `@tidecloak/react` (hoặc `oidc-client-ts`), Vitest, Playwright.

| Route | Màn hình | Thành phần chính |
| --- | --- | --- |
| /login | Đăng nhập | Nút đăng nhập TideCloak |
| / | Vault | Bảng tài liệu, nút Tải lên, banner chế độ mã hoá |
| /playbooks | Chọn lĩnh vực | Thẻ playbook, nút «Giao việc cho agent» |
| /tasks/new | Tạo task | Chọn playbook, ô chỉ dẫn |
| /tasks/:id | Chi tiết task | Trạng thái, đề xuất, **Consent Review** (checkbox tài liệu, chọn TTL, Duyệt/Từ chối), đồng hồ đếm ngược, nút Thu hồi |
| /agent-console | Agent Console | Log thời gian thực, nút «Xin tài liệu ngoài phạm vi» |
| /timeline | Nhật ký | Dòng thời gian, nút Xác minh chuỗi |
| /admin/quorum | Quorum | Danh sách CR, tiến độ duyệt (1/2), nút Duyệt/Từ chối |
| /admin/evil | Làm admin xấu | 4 nút ở FR-DEMO, vùng hiển thị kết quả |

Quy tắc UI: token chỉ giữ trong bộ nhớ; plaintext chỉ tồn tại trong bộ nhớ và Blob URL, thu hồi (`URL.revokeObjectURL`) khi đóng; mọi trạng thái lỗi có thông báo tiếng Việt; có trạng thái loading và rỗng.

## 8. Yêu cầu phi chức năng

- **NFR-SEC-01** Không ghi plaintext, K\_g, token vào log. Có test kiểm tra.
- **NFR-SEC-02** CORS chỉ cho origin frontend; CSP chặn script ngoài; header bảo mật cơ bản.
- **NFR-SEC-03** Mọi truy vấn theo `owner_id` qua Policy của Laravel; test IDOR cho từng endpoint có `{id}`.
- **NFR-SEC-04** Kích thước và MIME tải lên được kiểm tra ở server; tên file không dùng làm đường dẫn.
- **NFR-PERF-01** Tải 5 MB và mã hoá dưới 3 giây trên laptop thường; mở grant dưới 1 giây.
- **NFR-REL-01** Seeder idempotent; `docker compose up` chạy từ máy sạch trong 10 phút theo README.
- **NFR-TEST-01** Pest cho backend (ít nhất: JWT, phạm vi grant, thu hồi, quorum, hash chain, IDOR); Vitest cho CryptoProvider và bundle; 1 kịch bản Playwright chạy trọn demo.
- **NFR-UX-01** Luồng chính (tải lên → giao việc → duyệt → thu hồi) không quá 6 lần bấm.

## 9. Kịch bản demo 2 phút (tiêu chí nghiệm thu tổng)

1. (0:00) Đăng nhập Lan. Vault có 5 giấy tờ giả. Banner cho thấy chế độ mã hoá.
2. (0:20) Chọn playbook Thuê nhà, giao việc. Agent đề xuất 3 giấy tờ. Lan bỏ một mục tuỳ chọn, chọn TTL 15 phút, Duyệt.
3. (0:50) Agent Console chạy từng bước và soạn hồ sơ.
4. (1:05) Chuyển tab «Làm admin xấu»: Đổ database thấy bản mã; Tự cấp quyền kẹt ở 1/2; Agent xin hồ sơ y tế bị `OUT_OF_SCOPE`.
5. (1:25) Chạy lại task chậm, Lan bấm Thu hồi, agent hỏng giữa chừng. Timeline hiện đủ sự kiện, Xác minh chuỗi báo hợp lệ.
6. (1:45) Đổi sang playbook Khám bệnh để chứng minh cùng lõi.

## 10. Phân việc cho agent (work package)

Mỗi WP phải qua Definition of Done: code chạy, test liên quan xanh, không lộ plaintext/K\_g trong log, cập nhật README mục liên quan.

| WP | Việc | Phụ thuộc | Đầu ra / DoD |
| --- | --- | --- | --- |
| WP0 | Xác minh V1–V7 với Tide MCP/Discord #support; chọn `tide` hay `fallback` | không | Mục 12 điền đủ, quyết định bằng văn bản |
| WP1 | Khung dự án: monorepo `backend/`, `frontend/`, docker-compose (app, node, postgres, redis, tidecloak), `.env.example` | WP0 | `docker compose up` chạy cả hai, healthcheck ok |
| WP2 | Backend AUTH: middleware JWKS, RequireRole, `GET /me`, bảng users | WP1 | FR-AUTH-01..04, Pest: token sai/hết hạn/sai role |
| WP3 | Frontend AUTH: provider đăng nhập, route guard, API client gắn Bearer | WP1 | Đăng nhập thật tới TideCloak, `/me` hiển thị |
| WP4 | CryptoProvider (`tide` + `fallback`), helper bundle, unit test | WP0 | FR-VAULT-05, Vitest: mã hoá/giải mã khứ hồi, sai khoá |
| WP5 | Backend VAULT: migrations documents, upload/list/download/delete, Policy | WP2 | FR-VAULT-01..04, test IDOR |
| WP6 | Frontend VAULT: danh sách, upload dialog, viewer giải mã | WP3, WP4, WP5 | FR-VAULT-01..03, 05 |
| WP7 | Playbook + seed + quy tắc phạm vi | WP2 | FR-PB-01..03 |
| WP8 | Task + Agent: LlmClient (mock + anthropic), GenerateProposalJob, RunAgentJob, log | WP5, WP7 | FR-TASK-01, FR-AGENT-01..03 |
| WP9 | Grant: approve/deny/revoke/expire, K\_g trong Redis, scheduler, `/agent/*` | WP8 | FR-GRANT-02..04, 06, FR-AGENT-04, test thu hồi giữa chừng |
| WP10 | Frontend Consent Review + Task detail + Agent Console | WP6, WP9 | FR-GRANT-01, 05, màn /tasks/:id, /agent-console |
| WP11 | Quorum: change\_requests, approvals, executor, (chữ ký nếu V5) | WP2 | FR-QRM-01..03, test đua duyệt đồng thời |
| WP12 | Frontend Quorum + Admin | WP3, WP11 | /admin/quorum |
| WP13 | Audit hash-chain + verify + Timeline | WP5 (gắn dần vào các WP khác) | FR-AUD-01..03, test sửa DB bị phát hiện |
| WP14 | Demo «Làm admin xấu» + reset + token agent demo | WP9, WP11, WP13 | FR-DEMO-01..05 |
| WP15 | E2E Playwright cho kịch bản mục 9, README (bảng nói thật, kiến trúc, hướng dẫn chạy), video demo | tất cả | Chạy trọn 2 phút không lỗi |

Gợi ý song song: sau WP1, nhóm chia ba luồng: (A) WP2, WP5, WP7, WP9; (B) WP3, WP4, WP6, WP10; (C) WP11, WP12, WP13.

## 11. Rủi ro

| Rủi ro | Giảm thiểu |
| --- | --- |
| V1/V4/V5 không có sẵn trong SDK công khai | Fallback đã thiết kế sẵn, ghi rõ giới hạn trong README |
| Server đọc được phần đã duyệt trong hạn grant | Nói thẳng trong README; thử bảo vệ K\_g bằng Tide nếu V3 đạt |
| Thời gian ngắn | Ưu tiên mục M; cắt S/C trước; chỉ 2 playbook |
| Lộ plaintext qua log hoặc LLM | NFR-SEC-01, mặc định MockLlmClient, chỉ gửi LLM phần trong grant |
| Demo hỏng khi mạng yếu | Mock mặc định, seed cục bộ, quay video dự phòng |

## 12. Nhật ký xác minh (điền ở WP0)

| Mã | Kết quả | Nguồn (liên kết, tên người trả lời) | Quyết định |
| --- | --- | --- | --- |
| V1 | **Chưa đạt — chờ xác minh runtime.** SDK công khai *có* API mã hoá/giải mã trong trình duyệt: `@tidecloak/js@0.14.41` export `TideCloak.encrypt/decrypt`, `commitEncryption/commitDecryption`, `ApprovalEnclave`, `PolicySignRequest` (đọc từ `.d.ts` trong tarball npm). Chưa chạy thử thành công với server thật: cần enclave/changeSet endpoint + tài khoản Tide. | npm registry `@tidecloak/js@0.14.41`, `dist/types/lib/tidecloak.d.ts` (thử 2026-10-22); container `tideorg/tidecloak-dev` chạy local; **chưa** liên hệ được Discord #support/Tide MCP. | `ENC_MODE=fallback`: WebCrypto AES-256-GCM + PBKDF2-SHA256 210k, khoá suy ra từ passphrase ở trình duyệt. UI hiện banner «Chế độ dự phòng, không trustless». `crypto/tide.js` giữ chỗ, ném `TIDE_UNAVAILABLE` cho tới khi V1 chạy thật. |
| V2 | **Một phần đạt.** Realm dev `myrealm` trả OIDC discovery chuẩn và JWKS RS256 (`issuer = http://127.0.0.1:8080/realms/myrealm`, `.../protocol/openid-connect/certs` hoạt động, scopes có `roles`). SDK `@tidecloak/react@0.14.33` tồn tại (React ≥18, front-channel). Chưa login qua CMK/Tide nên **chưa thấy token thật** có `sub` và `realm_access.roles`. | curl OIDC discovery + JWKS local (2026-10-22); `npm view @tidecloak/react` (2026-10-22). | `AUTH_MODE=session` cho demo — ghi rõ là **tạm**, không phải TideCloak. Backend có sẵn `VerifyTideJwt` (verify RS256 theo JWKS realm, map `sub` → `users.tide_sub`, role từ `realm_access.roles`) và `AUTH_MODE=tide` để bật khi có token thật. `GET /api/v1/me` dùng chung hợp đồng ở cả hai chế độ. |
| V3 | **Không đạt (không có bằng chứng).** Không tìm thấy cơ chế gắn vai trò Tide để bảo vệ K\_g: realm import chỉ chứa client OIDC + IdP `tide`; không có API role → key. | `/opt/keycloak/data/import/myrealm-realm.json` trong container `tidecloak` (đọc 2026-10-22). | K\_g nằm trong cache store với TTL bằng TTL grant: `GRANT_KEY_STORE=file` cho dev/máy không có ext `redis`, `redis` cho production. Không ghi K\_g vào DB/log. **Server đọc được K\_g trong hạn grant** — ghi thẳng vào README. |
| V4 | **Chưa đạt.** Không có endpoint nào app-đọc-được trạng thái QEA; admin console TideCloak không truy cập được từ môi trường này (không có credential admin); không tìm thấy docs QEA công khai. | Quét endpoint local (2026-10-22: OIDC/QEA paths → 404, `/admin` → 302 Keycloak); tìm kiếm tài liệu Keycloak/Tide (2026-10-22) không có mục QEA cho realm admin change. | Quorum **mức app**: bảng `change_requests`, ngưỡng `QUORUM_THRESHOLD` (mặc định 2), khoá dòng `FOR UPDATE` khi thực thi. Badge QEA ở màn quorum đọc cờ `TIDECLOAK_QEA_ENABLED` (mặc định `false`, do người cấu hình bật khi có thật) — không tự nhận «đã có QEA». |
| V5 | **Có tín hiệu trong SDK, chưa xác minh end-to-end.** `@tidecloak/js` có `executeSignRequest`, `PolicySignRequest`, `DPoPSignatureProvider` — đủ API để ký phê duyệt, nhưng chưa ký/verify được chữ ký cho CR. | `@tidecloak/js@0.14.41` types (thử 2026-10-22). | Cột `change_request_approvals.signature` để sẵn (không nullable). Hiện dùng **re-auth mật khẩu trong 5 phút** (`QUORUM_REAUTH_MINUTES`) làm chữ ký phê duyệt; khi V5 đạt, lưu signature và `ChangeRequestExecutor` verify trước khi thực thi. |
| V6 | **Không đạt (local).** Realm import chỉ có client công khai `myclient`/`mytest` với standard flow, **không** có client service-account → không lấy được token agent từ Tide. | `/opt/keycloak/data/import/myrealm-realm.json` (đọc 2026-10-22). | Agent dùng token demo do backend cấp: `POST /api/v1/demo/agent-token` (chỉ khi `APP_DEMO=true`). README ghi rõ đây là token demo, không phải identity Tide. |
| V7 | **Không có thông tin.** Chưa tìm thấy tài liệu về mở khoá khẩn cấp (người thân duyệt). | Chưa liên hệ được Tide (Discord #support/MCP) — ghi ngày 2026-10-22. | Bỏ khỏi prototype; ghi vào mục «Giới hạn đã biết» của README. |

## Phụ lục A. Dữ liệu seed

**Người dùng:** `lan` (owner), `admin1`, `admin2`, `admin3` (admin), agent `trustgate-agent` (agent).

**Tài liệu giả của Lan:** CMND giả (id\_card), xác nhận thu nhập (income\_proof), lịch sử thuê nhà (rental\_history), thẻ bảo hiểm (insurance\_card), tóm tắt bệnh án (medical\_summary).

**Playbook `rental` (Thuê nhà):** required = id\_card, income\_proof; optional = rental\_history, reference\_letter; forbidden = medical\_summary, lab\_result, insurance\_card; bên nhận «Chủ nhà»; TTL mặc định 60 phút, tối đa 24 giờ.

**Playbook `clinic` (Khám bệnh):** required = insurance\_card, medical\_summary; optional = lab\_result; forbidden = income\_proof, rental\_history; bên nhận «Phòng khám»; TTL mặc định 15 phút, tối đa 60 phút.

## Phụ lục B. Biến môi trường

| Biến | Ý nghĩa |
| --- | --- |
| TIDECLOAK\_URL, TIDECLOAK\_REALM, TIDECLOAK\_CLIENT\_ID | Kết nối TideCloak (backend và frontend) |
| TIDECLOAK\_JWKS\_CACHE\_SECONDS | Mặc định 600 |
| ENC\_MODE | `tide` hoặc `fallback` |
| QUORUM\_THRESHOLD | Mặc định 2 |
| LLM\_DRIVER | `mock` (mặc định) hoặc `anthropic` |
| ANTHROPIC\_API\_KEY | Chỉ khi LLM\_DRIVER=anthropic, không commit |
| APP\_DEMO | `true` để bật nhóm /demo/\* |
| REDIS\_URL, DB\_URL | Hạ tầng |

## Phụ lục C. Cấu trúc thư mục gợi ý

```
backend/app/Http/Middleware/VerifyTideJwt.php
backend/app/Services/{VaultService,GrantService,AgentService,QuorumService,AuditService}.php
backend/app/Jobs/{GenerateProposalJob,RunAgentJob}.php
backend/app/Llm/{LlmClient,MockLlmClient,AnthropicLlmClient}.php
backend/database/seeders/DemoSeeder.php
frontend/src/crypto/{provider.ts,tide.ts,fallback.ts,bundle.ts}
frontend/src/pages/{Vault,Playbooks,TaskDetail,AgentConsole,Timeline,AdminQuorum,AdminEvil}.tsx
frontend/src/api/client.ts
```

## 13. Đối chiếu tiêu chí chấm điểm và dàn bài README

Không ai đảm bảo được điểm, nhưng mỗi tiêu chí dưới đây đều có phần chứng minh cụ thể trong sản phẩm. Điều kiện then chốt: ít nhất V1 hoặc V4 (mục 2.3) phải đạt trong WP0, nếu không TideCloak chỉ còn là ô đăng nhập và dự án rơi đúng vào cảnh báo của đề.

### 13.1 Ma trận tiêu chí

| Tiêu chí (trọng số) | Giám khảo cần thấy | TrustGate đáp ứng bằng | Bằng chứng |
| --- | --- | --- | --- |
| Vision & originality (25%) | Ý tưởng mới, tham vọng | Lớp consent cho AI agent dùng chung nhiều lĩnh vực: một lõi, nhiều playbook, thay vì một két cho một ngành | Bước 6 kịch bản demo; đoạn «khác gì két hồ sơ thông thường» trong README |
| Trust constraint insight (20%) | Chỉ rõ niềm tin, quyền riêng tư, quyền giám hộ hoặc quyền lực đang ngăn điều gì | Bốn ràng buộc ở bảng 13.2, mỗi ràng buộc gắn một màn demo | FR-DEMO-01..04 |
| Value & impact (20%) | Giá trị thật cho người dùng và tổ chức | Người dùng giao việc cho agent mà không phải đưa hết giấy tờ; tổ chức vận hành không phải giữ dữ liệu họ không cần | Số đo lấy từ demo: số tài liệu chia sẻ trên số tài liệu trong két (ví dụ 3/5), thời gian từ thu hồi đến agent dừng (dưới 5 giây). Không bịa số liệu thị trường |
| Why TideCloak (20%) | Sản phẩm không thể làm như vậy nếu chỉ có đăng nhập thường | Bảng 13.3, kèm điều kiện xác minh | WP0, mục 12, bảng «TideCloak làm / nhóm tự xây» |
| Prototype & storytelling (15%) | Hiểu ngay và thấy chạy | Một nhân vật (Lan), một luồng chạy thật từ đầu đến cuối, demo 2 phút, sơ đồ kiến trúc | Mục 9, WP15, dàn bài 13.4 |

### 13.2 Bốn ràng buộc niềm tin hôm nay và cách TrustGate đổi chúng

| Ràng buộc hôm nay | Hậu quả | TrustGate thay đổi | Yêu cầu |
| --- | --- | --- | --- |
| Giám hộ: bên vận hành giữ khoá | Phải tin cả công ty và mọi người có quyền vào database | Database chỉ chứa bản mã; khoá nằm ở trình duyệt Owner (và trong mạng Tide nếu V1 đạt) | FR-VAULT-01, FR-DEMO-01 |
| Quyền lực: một admin đủ để đổi quyền | Admin bị chiếm hoặc làm bậy tự nâng quyền được | Mọi đổi quyền cần quorum | FR-QRM-01..03, FR-DEMO-02 |
| Riêng tư khi uỷ quyền: giao việc cho agent thường là đưa hết hoặc không | Agent thấy nhiều hơn mức cần | Grant theo từng tài liệu, mục đích và thời hạn; server chặn loại tài liệu ngoài playbook | FR-GRANT-01..02, FR-PB-03, FR-DEMO-03 |
| Thu hồi: thường chỉ là ẩn nút | Quyền đã cấp vẫn dùng được | Xoá K\_g nên agent hỏng ngay giữa chừng | FR-GRANT-03, FR-DEMO-04 |

Giới hạn nói thật trong README: thu hồi không lấy lại được dữ liệu agent đã đọc và tự lưu ra ngoài; TrustGate chỉ chặn các lần đọc tiếp theo.

### 13.3 Bài kiểm tra «thay TideCloak bằng đăng nhập thường»

| Chức năng | Với đăng nhập thường | Với TideCloak | Điều kiện |
| --- | --- | --- | --- |
| Đọc database | Khoá nằm cùng server nên admin đọc được | Khoá phân tán, không máy nào giữ khoá đầy đủ | V1 |
| Admin tự nâng quyền | Một admin là đủ | Cần đủ số admin ký duyệt (QEA) | V4 |
| Đăng nhập | Mật khẩu đi qua server | Mật khẩu không rời trình duyệt | V2 |
| Bằng chứng phê duyệt | Nhật ký sửa được | Chữ ký mật mã | V5 |

Quy tắc quyết định ở WP0: nếu cả V1 và V4 đều không đạt, hãy dừng và đổi trọng tâm demo trước khi code. Khi đó quorum mức app và mã hoá WebCrypto vẫn chạy được, nhưng giám khảo sẽ thấy TideCloak chỉ làm phần đăng nhập, và điểm «Why TideCloak» sẽ thấp.

### 13.4 Dàn bài README (mỗi mục trả lời một tiêu chí)

1. Một câu mở bằng nhân vật: Lan nhờ agent thuê nhà nhưng không muốn đưa hết giấy tờ. (Storytelling)
2. Vấn đề và ràng buộc niềm tin: bảng 13.2 rút gọn. (Trust constraint insight)
3. Giải pháp và điểm khác biệt so với két hồ sơ thông thường. (Vision & originality)
4. Vì sao cần TideCloak: bảng 13.3 và bảng «TideCloak làm / nhóm tự xây» (mục 2.4), ghi rõ chế độ `tide` hay `fallback` đang chạy. (Why TideCloak)
5. Sơ đồ kiến trúc và luồng Release Bundle (mục 3). (Storytelling)
6. Cách chạy demo và liên kết video 2 phút (mục 9). (Prototype)
7. Tác động: số đo lấy từ demo và các lĩnh vực mở rộng bằng playbook. (Value & impact)
8. Giới hạn đã biết: server đọc được phần đã duyệt trong hạn grant, dữ liệu đã đọc không thu hồi được, phần nào chưa xác minh. (Độ tin cậy)

Lưu ý: trang «README prompts» của ban tổ chức chặn truy cập tự động nên chưa được đối chiếu. Hãy dán nội dung các câu hỏi gợi ý vào đây để khớp từng đầu mục với dàn bài trên.
