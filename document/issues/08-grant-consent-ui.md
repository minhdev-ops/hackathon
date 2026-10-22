# 08 — GRANT CONSENT UI + release bundle

**SRS:** FR-GRANT-01..06, §3.3, §7 · **WP:** WP9 + WP10 · **Ưu tiên:** P0 · **Phụ thuộc:** 03, 04, 06

## Mục tiêu

Màn Consent Review cho Owner: xem agent, mục đích, tài liệu đề xuất, bỏ tài liệu tuỳ chọn, chọn TTL,
Duyệt/Từ chối. Khi Duyệt, trình duyệt giải mã tài liệu đã chọn bằng khoá Vault, tạo K_g + bundle
AES-256-GCM, gửi lên server. Sau khi grant active: đồng hồ đếm ngược và nút Thu hồi.

## Hiện trạng

- Backend đã có:
  - `GrantController` (`show/approve/deny/revoke`), `GrantPolicy`, `GrantResource`.
  - `GrantService::approve()` kiểm tra quyền sở hữu, phạm vi playbook, TTL, giải bundle và so khớp
    `doc_ids`; lưu bundle; đưa K_g vào `GrantKeyStore`; dispatch `RunAgentJob`.
  - `GenerateProposalJob` tạo `grant` pending sau khi có proposal, task chuyển `awaiting_approval`.
  - `GrantBundleCipher` dùng AES-256-GCM, tag 16 byte nối sau ciphertext; key/IV base64.
- **Chưa có:** UI Consent Review, tạo bundle ở browser, countdown, nút revoke.
- **Thiếu backend:** chưa bắt buộc tài liệu `required` phải có trong `doc_ids`; Owner có thể bỏ cả
  required nếu bundle khớp danh sách đã chọn. SRS chỉ cho bỏ tài liệu tuỳ chọn.
- `GrantResource.review.documents` đã trả `required`, `selected`, `title_ct`, `sha256`, `size_bytes`,
  `enc_mode`, TTL options, rationale.

## Phạm vi

### Backend hardening

- [ ] Trong `GrantService::approve()`: sau khi `assertDocumentsInScope`, kiểm tra mọi `doc_type` thuộc
      `playbook.required_doc_types` đều có mặt trong `doc_ids`; thiếu → `422 OUT_OF_SCOPE` (hoặc
      `MISSING_REQUIRED_DOC` với details). Không dựa vào client.
- [ ] `assertBundleScope`: đã đúng exact doc_ids; giữ.
- [ ] `ApproveGrantRequest`: đã có `doc_ids.*` UUID/distinct và `ttl_minutes` integer; giữ `bundle`
      max 50 MB, bổ sung test validation.
- [ ] `GrantResource`: thêm `can_approve`, `can_revoke`, `is_expired` nếu cần; giữ `server_time` để
      countdown không lệch.
- [ ] Test: bỏ required → 422; bỏ optional → OK; bundle thừa/thiếu doc → 422 `BUNDLE_INVALID`;
      TTL vượt max → 422; owner khác → 403.

### Frontend

- [ ] `resources/js/components/grant/ConsentReview.jsx`:
  - Nhận `grant` từ `GET /grants/{id}` (hoặc `TaskResource.active_grant` + fetch).
  - Hiện agent name, purpose, rationale, recipient label, playbook.
  - Danh sách tài liệu: checkbox; required disable + luôn chọn; optional cho bỏ; hiện `title_ct` đã
    giải mã (dùng CryptoProvider issue 04), doc_type label, size.
  - Chọn TTL từ `review.ttl_options`; default `default_ttl_minutes`; cảnh báo nếu > max.
  - Nút «Duyệt»: disable nếu thiếu required hoặc chưa chọn TTL.
  - Nút «Từ chối»: gọi `POST /grants/{id}/deny`, có confirm; hiện trạng thái.
- [ ] Luồng Duyệt:
  1. Đảm bảo Vault key đã mở (nếu chưa, mở dialog issue 04).
  2. Với từng `doc_id` đã chọn: `GET /documents/{id}/ciphertext`, giải mã bằng Vault key + `iv`; kiểm
     tra `sha256`; lấy tên đã giải mã.
  3. `createGrantBundle(docs)` (issue 04) → `{bundle, iv, keyB64}` với K_g mới.
  4. Gửi multipart `POST /grants/{id}/approve`:
     `doc_ids[]`, `ttl_minutes`, `grant_key_b64`, `bundle_iv`, `bundle` (Blob).
  5. Thành công: cập nhật grant/task; bắt đầu countdown; hiện «Agent đang chạy».
  6. Lỗi 422 `OUT_OF_SCOPE`/`BUNDLE_INVALID`/`TTL_NOT_ALLOWED`: hiện message + details; không mất
     selection.
- [ ] Countdown (FR-GRANT-05):
  - Dùng `expires_at` và `server_time` để tính offset; cập nhật mỗi giây.
  - Hiện `mm:ss` hoặc `hh:mm:ss`; khi hết hạn hiện «Đã hết hạn» và disable revoke.
  - Không dựa hoàn toàn vào đồng hồ máy.
- [ ] Revoke:
  - Nút «Thu hồi» khi `status=active`; confirm; gọi `POST /grants/{id}/revoke`; hiện trạng thái
    `revoked`; task sẽ `failed_revoked` (issue 07).
- [ ] Deny: hiện trạng thái `denied`, task `denied`; không cho approve lại (409).
- [ ] Nhúng `ConsentReview` vào `TaskDetail` (issue 07) khi task `awaiting_approval`/`proposal_ready`
      và grant `pending`.
- [ ] Xử lý 409 `GRANT_NOT_PENDING`, 410 `GRANT_UNAVAILABLE`, 403; message tiếng Việt.
- [ ] Không log plaintext/K_g; xoá `content_b64` khỏi memory sau khi tạo bundle; không giữ trong state.

### Không làm

- Không đổi wire format bundle nếu chưa thống nhất với `GrantBundleCipher`/`GrantService`.
- Không gửi K_g vào query string/URL; chỉ trong multipart body.
- Không cho admin duyệt thay owner.

## Hợp đồng approve

```http
POST /api/v1/grants/{id}/approve
Content-Type: multipart/form-data

doc_ids[]=uuid1
doc_ids[]=uuid2
ttl_minutes=15
grant_key_b64=<base64 32 bytes>
bundle_iv=<base64 12 bytes>
bundle=<binary AES-GCM ciphertext+tag>
```

Bundle plaintext:

```json
{
  "documents": [
    { "doc_id": "uuid", "doc_type": "id_card", "name": "CMND Lan", "content_b64": "..." }
  ]
}
```

## Acceptance criteria

- [ ] Consent Review hiện đúng agent/purpose/rationale/tài liệu/TTL.
- [ ] Required không bỏ được; optional bỏ được.
- [ ] Duyệt thành công → grant `active`, K_g có TTL, task chạy; countdown đúng.
- [ ] Bỏ required (cố ý) → 422, UI hiện lỗi rõ.
- [ ] Bundle thiếu/thừa doc → 422 `BUNDLE_INVALID`.
- [ ] Từ chối → `denied`; không mở được.
- [ ] Thu hồi → `revoked`; agent dừng; countdown dừng.
- [ ] Hết hạn → UI hiện hết hạn; mở grant trả 410.
- [ ] Không có plaintext/K_g trong console/network ngoài multipart cần thiết.

## Test

- [ ] PHPUnit: approve happy path; required missing; optional missing; bundle mismatch; TTL; IDOR.
- [ ] Vitest: `createGrantBundle` roundtrip, decrypt by `GrantBundleCipher` compat (nếu chạy được Node
      WebCrypto/PHP test vector).
- [ ] Manual: full flow Vault → task → consent → agent run → revoke.
- [ ] Kiểm tra countdown khi reload trang (dùng server_time).

## File liên quan

- Backend: `app/Services/GrantService.php`, `app/Http/Requests/ApproveGrantRequest.php`,
  `app/Http/Resources/GrantResource.php`, `app/Services/GrantBundleCipher.php`.
- Frontend: `resources/js/crypto/bundle.js` (issue 04),
  `resources/js/components/grant/ConsentReview.jsx`,
  `resources/js/pages/TaskDetail.jsx` (issue 07), `resources/js/api/client.js`.
- Test: `tests/Feature/Grant/GrantApiTest.php`.

## Definition of Done

- [ ] Consent Review chạy end-to-end với bundle thật.
- [ ] Required/optional đúng SRS; countdown/revoke hoạt động.
- [ ] Test grant xanh; không lộ plaintext/K_g.
- [ ] README mô tả luồng release bundle và giới hạn «server đọc được phần đã duyệt trong hạn grant».
