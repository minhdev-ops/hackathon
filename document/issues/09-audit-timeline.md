# 09 — AUDIT: API, verify, Timeline

**SRS:** FR-AUD-01..03 · **WP:** WP13 · **Ưu tiên:** P1 · **Phụ thuộc:** 03, 06

## Mục tiêu

Hoàn thiện nhật ký append-only có hash-chain: API `GET /audit` (Owner thấy sự kiện của mình, Admin thấy
tất cả), `GET /audit/verify` phát hiện sửa dữ liệu trực tiếp, và màn `/timeline` cho Owner với nút xác
minh chuỗi.

## Hiện trạng

- `AuditService` đã có:
  - `record(action, actorType, actorId, subjectType, subjectId, meta)`.
  - `hash = sha256(prev_hash + canonical_json(payload))`.
  - `payloadFor()` chuẩn hoá để recompute; `chainHash()`.
  - Lock dòng cuối khi ghi để tránh đua.
- Đã gọi audit ở: `TaskService` (task.created), `GrantService` (approved/denied/revoked/expired/
  opened/open_denied), `QuorumService`/`ChangeRequestExecutor` (CR tạo/duyệt/thực thi).
- **Chưa có:** API `/audit`, `/audit/verify`, `AuditController`, `AuditResource`, UI `/timeline`.
- **Chưa có event:** document upload/view/delete (issue 03 sẽ thêm); out-of-scope hiện lỗi signature.
- `AuditLog` không có `owner_id`; cần lọc Owner qua `actor_id` + subject ownership.
- Chưa có test sửa DB → verify phát hiện.

## Phạm vi

### API

- [ ] `AuditController@index`:
  - Admin: tất cả, mới nhất trước; filter `?action=`, `?subject_type=`, `?limit=` (mặc định 50, max 200).
  - Owner: chỉ sự kiện liên quan:
    - `actor_type=user AND actor_id=<owner id>`, hoặc
    - `subject_type=document` và `subject_id` thuộc document của owner, hoặc
    - `subject_type=agent_task` và task thuộc owner, hoặc
    - `subject_type=grant` và grant thuộc owner.
  - Không trả dữ liệu của owner khác; không trả `meta` chứa plaintext/K_g/token.
- [ ] `AuditController@verify`:
  - Đọc toàn bộ log theo `id ASC` (có thể chunk).
  - Recompute `payloadFor(row)` + `chainHash(payload, prev_hash)`; so với `row.hash` và `prev_hash`.
  - Trả:
    ```json
    { "data": { "valid": true, "checked": 123, "broken_at": null } }
    ```
    Nếu hỏng:
    ```json
    { "data": { "valid": false, "checked": 123, "broken_at": 42,
      "expected_hash": "...", "actual_hash": "...", "reason": "hash_mismatch|prev_hash_mismatch" } }
    ```
  - Owner cũng gọi được nhưng chỉ nhận kết quả integrity toàn cục (không trả meta); ghi rõ trong README.
- [ ] Route `GET /api/v1/audit`, `GET /api/v1/audit/verify` trong nhóm `auth`, `role:owner,admin`.
- [ ] `AuditResource`: `id`, `action`, `action_label` (tiếng Việt), `actor_type`, `actor_id`,
      `subject_type`, `subject_id`, `meta` (đã lọc an toàn), `created_at`, `hash`, `prev_hash`.
- [ ] Thêm event còn thiếu:
  - `document.uploaded`, `document.viewed`, `document.deleted` (issue 03).
  - `agent.document_request.out_of_scope` (sửa `DocumentRequestController`, issue 10).
  - `proposal.generated` (issue 06) — optional nhưng hữu ích cho timeline.
  - `grant.expired` đã có; kiểm tra scheduler gọi `expireStale`.
- [ ] Không ghi `instruction`, title, `content_b64`, `grant_key_b64`, token, password vào `meta`.

### Frontend `/timeline`

- [ ] `resources/js/pages/Timeline.jsx`:
  - Fetch `/audit` (owner) hoặc `/audit` (admin, có filter).
  - Nhóm theo ngày; mỗi dòng: giờ, nhãn hành động tiếng Việt, actor, subject rút gọn, meta an toàn.
  - Ví dụ: «Agent TrustGate đã mở 3 tài liệu lúc …, grant hết hạn lúc …» (FR-AUD-03).
  - Nút «Xác minh chuỗi» gọi `/audit/verify`; hiện xanh «Chuỗi hợp lệ (N sự kiện)» hoặc đỏ «Đứt chuỗi
    tại #id».
  - Loading/rỗng/lỗi tiếng Việt; phân trang hoặc «Tải thêm».
- [ ] Route `/timeline` + link trong header; guard owner/admin.
- [ ] Không render `meta` thô nếu có thể chứa dữ liệu nhạy cảm; dùng whitelist key.

### Không làm

- Không sửa/xoá log qua API.
- Không expose hash-chain toàn bộ meta cho owner.
- Không dùng audit làm nguồn dữ liệu nghiệp vụ.

## Test

- [ ] Ghi 3 event → `GET /audit` trả đúng thứ tự, hash/prev_hash khớp.
- [ ] Owner chỉ thấy event của mình; không thấy event owner khác.
- [ ] Admin thấy tất cả.
- [ ] `verify` trên chuỗi sạch → `valid=true`, `checked=N`.
- [ ] Sửa `meta` một row bằng DB → `valid=false`, `broken_at` đúng id, reason `hash_mismatch`.
- [ ] Sửa `prev_hash` → `broken_at` đúng, reason `prev_hash_mismatch`.
- [ ] Xoá một row giữa chuỗi → verify phát hiện (prev_hash mismatch ở row sau).
- [ ] Meta không chứa `content_b64`, `grant_key`, token, title, instruction.
- [ ] Test NFR-SEC-01: quét `storage/logs` và audit meta các key cấm.

## File liên quan

- `app/Services/AuditService.php`, `app/Models/AuditLog.php`.
- `database/migrations/2026_10_22_100006_create_audit_logs_table.php`.
- `app/Services/GrantService.php`, `TaskService.php`, `QuorumService.php`,
  `ChangeRequestExecutor.php` — nơi gọi audit.
- `app/Http/Controllers/Api/V1/Agent/DocumentRequestController.php` — sửa signature.
- Mới: `app/Http/Controllers/Api/V1/AuditController.php`,
  `app/Http/Resources/AuditResource.php`,
  `resources/js/pages/Timeline.jsx`.
- Test: `tests/Feature/Audit/AuditApiTest.php`.

## Ghi chú

- Hash chain là toàn cục; Owner xem subset nhưng verify toàn cục. Nếu muốn verify riêng, cần lưu thêm
  `owner_id` hoặc chain riêng — không làm trong prototype.
- `AuditService::record` hiện lock dòng cuối; với SQLite test có thể không hỗ trợ `lockForUpdate` hiệu
  quả, nhưng test đơn luồng vẫn chạy.
- `action_label` nên có map tiếng Việt; fallback hiển thị action gốc.

## Definition of Done

- [ ] API audit/verify chạy; test sửa DB phát hiện đúng.
- [ ] Timeline hiển thị và verify được.
- [ ] Owner isolation đúng; không lộ meta nhạy cảm.
- [ ] README mô tả hash-chain và giới hạn verify.
