# 03 — VAULT backend: upload/list/download/delete

**SRS:** FR-VAULT-01..05 · **WP:** WP5 · **Ưu tiên:** P0 · **Phụ thuộc:** 01 (có thể làm trước với session)

## Mục tiêu

Cung cấp API két tài liệu: Owner tải lên bản mã, xem danh sách của mình, tải bản mã để giải mã ở
trình duyệt, và xoá mềm. Server không bao giờ nhận plaintext, không lưu tên tài liệu dạng rõ, và chặn
IDOR.

## Hiện trạng

- Đã có `Document` model, migration `2026_10_22_100003_create_documents_table.php`, factory.
- Bảng `documents`: `id` UUID, `owner_id`, `doc_type`, `title_ct`, `ciphertext_path`, `iv`, `sha256`,
  `size_bytes`, `enc_mode`, `deleted_at`, timestamps.
- `User` đã có quan hệ `documents()`.
- **Chưa có:** `DocumentController`, `StoreDocumentRequest`, `DocumentResource`, `DocumentPolicy`,
  `VaultService`, route `/documents`, `vault_kdf_salt` trên `users`, cấu hình `ENC_MODE`.
- `GrantService::assertDocumentsInScope()` đã loại document soft-deleted (query mặc định) và kiểm tra
  `owner_id`, nên chỉ cần API tạo dữ liệu đúng.

## Phạm vi

### Làm

- [ ] Migration mới thêm `users.vault_kdf_salt` (string nullable). Sinh salt một lần cho user cũ/mới
      (16–32 byte random, base64); không trả qua API công khai, chỉ `/me` của chính user.
- [ ] `config/trustgate.php`: thêm `enc_mode` (`ENC_MODE`, mặc định `fallback`), `vault_disk` (`local`),
      `vault_max_bytes` (10 MB + 16 byte tag), `vault_allowed_mime` (pdf/png/jpeg), `vault_doc_types`
      (dùng `doc_types` hiện có).
- [ ] `VaultService`:
  - `store(User $owner, UploadedFile $ciphertext, array $meta): Document`.
  - Kiểm tra `size_bytes <= vault_max_bytes`; `doc_type` thuộc whitelist; `iv` base64 đúng 12 byte;
    `sha256` đúng 64 hex; `enc_mode` thuộc `tide|fallback`.
  - Lưu file với tên sinh tự động `documents/{owner_id}/{uuid}.bin` trên disk `vault_disk`; **không**
    dùng tên gốc của client làm path.
  - Tạo `Document` trong transaction; nếu lỗi xoá file đã lưu.
  - `list(User $owner)`, `findOwned(User $owner, string $id)`, `delete(Document $doc)`.
- [ ] `StoreDocumentRequest`:
  - `file` required file max 10 MB (ciphertext), `iv` required string max 32, `sha256` required
    regex `/^[a-f0-9]{64}$/i`, `doc_type` required in whitelist, `title_ct` required string max 4096,
    `enc_mode` required in `tide,fallback`, `mime_type` required in whitelist (advisory).
  - Thông báo lỗi tiếng Việt.
- [ ] `DocumentPolicy`: `view`, `download`, `delete` chỉ owner. Admin cũng không đọc được.
- [ ] `DocumentResource`: `id`, `doc_type`, `title_ct`, `iv`, `sha256`, `size_bytes`, `enc_mode`,
      `created_at`; không trả `ciphertext_path` (tránh lộ layout) và không trả nội dung.
- [ ] `DocumentController`:
  - `POST /api/v1/documents` (owner, throttle 60/phút) → 201 `{data: DocumentResource}`.
  - `GET /api/v1/documents` (owner) → danh sách của chính mình, mới nhất trước.
  - `GET /api/v1/documents/{document}/ciphertext` (owner) → binary, header `Content-Type:
    application/octet-stream`, `Content-Disposition: attachment; filename="document.bin"`,
    `Cache-Control: no-store`, `X-Content-Type-Options: nosniff`.
  - `DELETE /api/v1/documents/{document}` (owner) → 200/204, soft delete.
- [ ] Route đặt trong nhóm `auth` + `role:owner` (hoặc policy-only nếu admin không cần).
- [ ] Ghi audit `document.uploaded`, `document.viewed`, `document.deleted` (issue 09 sẽ làm API đọc;
      ở đây chỉ gọi `AuditService::record`, meta không chứa title/plaintext).
- [ ] Cập nhật `.env.example`: `ENC_MODE=fallback`, `VAULT_DISK=local`, `VAULT_MAX_MB=10`.

### Không làm

- Không mã hoá/giải mã nội dung ở server.
- Không trả plaintext hoặc title đã giải mã.
- Không xoá cứng file ngay khi soft delete (grant đang chạy đã có bundle riêng; giữ file để an toàn).

## Hợp đồng API

### Upload

```http
POST /api/v1/documents
Content-Type: multipart/form-data

file=<ciphertext binary>
iv=<base64 12 bytes>
sha256=<hex 64>
doc_type=id_card
title_ct=<base64(iv || ciphertext+tag)>
enc_mode=fallback
mime_type=application/pdf
```

```json
201 {
  "data": {
    "id": "uuid",
    "doc_type": "id_card",
    "title_ct": "base64...",
    "iv": "base64...",
    "sha256": "hex...",
    "size_bytes": 12345,
    "enc_mode": "fallback",
    "created_at": "2026-10-22T14:00:00+07:00"
  }
}
```

### Lỗi

| Mã | HTTP | Khi nào |
| --- | --- | --- |
| `VALIDATION_ERROR` | 422 | Thiếu field, sai doc_type, quá 10 MB, sai IV/sha256 |
| `FORBIDDEN` | 403 | Owner khác tải/xoá |
| `NOT_FOUND` | 404 | Document không tồn tại hoặc đã soft delete |

> **Giới hạn cần ghi README:** server chỉ thấy ciphertext nên không thể kiểm tra MIME thật của
> plaintext. Server kiểm tra `mime_type` do client khai báo, kích thước, `doc_type` và `enc_mode`;
> kiểm tra MIME thật diễn ra ở trình duyệt trước khi mã hoá. Không dùng tên file làm path.

## Test

- [ ] Upload hợp lệ: file lưu đúng disk/path; DB có đủ field; response không có `ciphertext_path`.
- [ ] Upload thiếu `file`/`iv`/`sha256`/`doc_type` → 422.
- [ ] `doc_type` ngoài whitelist → 422.
- [ ] File > 10 MB → 422.
- [ ] `iv` sai độ dài, `sha256` sai format → 422.
- [ ] List chỉ trả document của owner; owner khác không thấy.
- [ ] Download document của owner khác → 403; document đã xoá → 404.
- [ ] Delete: soft delete, không còn trong list; `deleted_at` khác null; file vẫn tồn tại (hoặc ghi rõ
      chính sách).
- [ ] Header download đúng `no-store`/`nosniff`.
- [ ] DB không chứa plaintext: `title_ct` khác title, file content khác plaintext.
- [ ] Audit có `document.uploaded`, `document.viewed`, `document.deleted`; meta không chứa title.
- [ ] `GET /documents` không N+1 (dùng `latest()->get()` đơn giản).

## File liên quan

- `app/Models/Document.php`, `app/Models/User.php`.
- `database/migrations/2026_10_22_100003_create_documents_table.php`.
- `database/migrations/*_add_vault_kdf_salt_to_users_table.php` (mới).
- `app/Services/GrantService.php` — tham chiếu cách kiểm tra owner/scope.
- `app/Services/AuditService.php`.
- `config/trustgate.php`, `config/filesystems.php`, `.env.example`.
- Mới: `app/Http/Controllers/Api/V1/DocumentController.php`,
  `app/Http/Requests/StoreDocumentRequest.php`,
  `app/Http/Resources/DocumentResource.php`,
  `app/Policies/DocumentPolicy.php`,
  `app/Services/VaultService.php`.
- Test: `tests/Feature/Vault/DocumentApiTest.php`.

## Definition of Done

- [ ] 4 route Vault có trong `php artisan route:list`.
- [ ] Test Vault xanh; test IDOR cho download/delete.
- [ ] Không có plaintext/title rõ trong DB, log, response list.
- [ ] README ghi rõ giới hạn MIME và chế độ `enc_mode`.
