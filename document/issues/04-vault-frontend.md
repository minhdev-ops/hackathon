# 04 — VAULT frontend + CryptoProvider

**SRS:** FR-VAULT-01..05, §3.4, §7 · **WP:** WP4 + WP6 · **Ưu tiên:** P0 · **Phụ thuộc:** 03, 00 (chế độ crypto)

## Mục tiêu

Làm toàn bộ lớp crypto trình duyệt và màn `/` (Vault): mở khoá bằng passphrase, tải lên tài liệu đã
mã hoá, hiển thị danh sách với tên được giải mã tại chỗ, xem tài liệu qua Blob URL, xoá mềm. Banner
«Chế độ dự phòng, không trustless» khi `enc_mode=fallback`.

## Hiện trạng

- Chưa có thư mục `resources/js/crypto/`.
- `resources/js/components/App.jsx` route `/` đang là trang Home tạm.
- Chưa có Vault page, upload dialog, viewer.
- `package.json` chưa có Vitest/Testing Library.
- Backend Vault API sẽ do issue 03 cung cấp.

## Phạm vi

### 1. CryptoProvider

Tạo `resources/js/crypto/`:

- [ ] `provider.js` — interface chung:
  ```js
  // mode: 'tide' | 'fallback'
  // encryptDocument(arrayBuffer) -> { ciphertext: ArrayBuffer, iv: Uint8Array, sha256: string }
  // decryptDocument(ciphertext, iv) -> ArrayBuffer
  // encryptText(text) -> base64(iv || ciphertext+tag)
  // decryptText(b64) -> string
  ```
- [ ] `fallback.js`:
  - Derive key: `PBKDF2-SHA256(passphrase, salt, 210_000, 32 bytes)` từ `vault_kdf_salt` của `/me`.
  - AES-256-GCM, IV 12 byte ngẫu nhiên cho mỗi lần mã hoá.
  - File: ciphertext = `AES-GCM(plaintext)` gồm tag; `iv` lưu riêng; `sha256` = hex của plaintext.
  - Title: `title_ct = base64(iv || ciphertext+tag)`; giải mã ngược, sai khoá → lỗi có kiểm soát.
  - Không dùng `localStorage`; key chỉ nằm trong memory (React context/ref).
- [ ] `tide.js`:
  - Nếu WP0 chọn `tide` và V1 đạt: gọi SDK Tide để mã hoá/giải mã.
  - Nếu chưa đạt: export provider ném lỗi `TIDE_UNAVAILABLE` và UI chuyển fallback có thông báo.
- [ ] `bundle.js`:
  - `createGrantBundle(docs)` → `{ bundle: ArrayBuffer, iv: Uint8Array, keyB64: string }` với K_g
    random 32 byte, AES-256-GCM, payload:
    `{"documents":[{"doc_id","doc_type","name","content_b64"}]}`.
  - `decryptGrantBundle(...)` chỉ dùng cho test/agent console nếu cần.
- [ ] `keyStore.js` (memory): `setVaultKey`, `getVaultKey`, `clearVaultKey`; không persist.
- [ ] `resources/js/crypto/README.md` ngắn: format `title_ct`, file ciphertext, bundle, cách đổi passphrase.

### 2. Vault UI (`resources/js/pages/Vault.jsx`)

- [ ] Khi vào `/`:
  - Gọi `/me` lấy `vault_kdf_salt` và role.
  - Nếu chưa có key trong memory: hiện dialog «Mở khoá két» yêu cầu passphrase; demo dùng `password`
    (ghi trong README, không hardcode vào production).
  - Derive key; nếu sai, thử giải mã một title; sai → báo «Sai mật khẩu két» và cho thử lại.
  - Nút «Khoá két» xoá key khỏi memory và quay lại dialog.
- [ ] Danh sách:
  - Gọi `GET /documents`; mỗi dòng giải mã `title_ct` → tên; hiện `doc_type` (nhãn tiếng Việt), ngày tải,
    dung lượng, badge `enc_mode`.
  - Trạng thái loading skeleton, rỗng («Chưa có tài liệu. Tải lên giấy tờ đầu tiên.»), lỗi tiếng Việt.
  - Nút «Tải lên», «Xem», «Xoá» (confirm).
- [ ] Upload dialog:
  - Chọn file PDF/PNG/JPG; kiểm tra MIME + dung lượng ≤ 10 MB trước khi mã hoá.
  - Chọn `doc_type` từ danh sách playbook/doc types; nhập tên tài liệu (plaintext chỉ trong memory).
  - Đọc `ArrayBuffer`, mã hoá, tính sha256, tạo `title_ct`.
  - Gửi multipart `POST /documents` với `file` (Blob ciphertext), `iv`, `sha256`, `doc_type`,
    `title_ct`, `enc_mode`, `mime_type`.
  - Hiện tiến độ/loading; lỗi 422 hiển thị message từ server; thành công đóng dialog và refresh list.
  - Không log plaintext/title ra console.
- [ ] Viewer:
  - `GET /documents/{id}/ciphertext` → ArrayBuffer.
  - Giải mã bằng key trong memory + `iv`; kiểm tra `sha256` khớp; sai → «Không giải mã được tài liệu».
  - Tạo Blob URL theo `mime_type` (pdf/png/jpeg), mở trong modal/iframe/img.
  - Khi đóng: `URL.revokeObjectURL`; không giữ Blob URL sau khi unmount.
- [ ] Banner:
  - Nếu bất kỳ document `enc_mode=fallback` hoặc chế độ đang dùng là fallback: banner vàng
    «Chế độ dự phòng, không trustless — khoá được suy ra từ mật khẩu trong trình duyệt.»
  - Nếu `tide`: banner xanh «Chế độ TideCloak» (chỉ hiện khi V1 thực sự đạt).
- [ ] `App.jsx`: `/` render `<Vault />` cho owner; admin `/` redirect `/admin/quorum`; guest `/login`.
- [ ] Header/sidebar: link Vault, Playbooks, Timeline, (admin) Quorum/Evil, user menu/logout.

### 3. Không làm

- Không tự ý đổi format bundle nếu chưa thống nhất với issue 08/06.
- Không lưu key/passphrase vào localStorage, cookie, URL, hay log.
- Không dùng `dangerouslySetInnerHTML` cho nội dung tài liệu.

## Acceptance criteria

- [ ] Upload PDF 5 MB: request chỉ chứa ciphertext; DB không có plaintext; list hiện tên giải mã đúng.
- [ ] Mở tài liệu: ảnh/PDF hiển thị; đóng thì Blob URL được revoke.
- [ ] Sai passphrase: báo lỗi, không crash, không mất list.
- [ ] Sai khoá khi xem: báo «Không giải mã được tài liệu», không crash.
- [ ] Owner khác không thấy tài liệu (backend issue 03 đảm bảo; UI chỉ hiện của mình).
- [ ] Xoá: confirm, list cập nhật, tài liệu không dùng được trong grant mới.
- [ ] Banner fallback hiện đúng; không hiện banner Tide khi chưa xác minh.
- [ ] `npm run build` xanh; không có token/passphrase trong bundle JS.

## Test

- [ ] Vitest cho `fallback.js`:
  - Roundtrip file (PDF bytes giả) → decrypt đúng.
  - Roundtrip title → đúng chuỗi.
  - Sai key → reject/throw, không treo.
  - `title_ct` có IV 12 byte + tag; `sha256` đúng.
- [ ] Vitest cho `bundle.js`: tạo bundle, decrypt đúng payload; sai key fail; doc list đúng.
- [ ] Manual: upload/xem/xoá; refresh trang → phải mở khoá lại (key không persist).
- [ ] Kiểm tra Network: request upload không chứa title/plaintext; response list không có plaintext.

## File liên quan

- Mới: `resources/js/crypto/{provider,fallback,tide,bundle,keyStore}.js`,
  `resources/js/pages/Vault.jsx`,
  `resources/js/components/vault/{UploadDialog,DocumentViewer,UnlockDialog}.jsx`.
- Sửa: `resources/js/components/App.jsx`, `resources/js/app.jsx`, `resources/js/api/client.js`.
- Backend: `app/Http/Controllers/Api/V1/DocumentController.php` (issue 03).
- `package.json`, `vite.config.js` (Vitest config — phối hợp issue 11).
- SRS §3.4, §7; `document/issues/08-grant-consent-ui.md` dùng lại `bundle.js`.

## Ghi chú kỹ thuật

- `crypto.subtle` chỉ có trong secure context (localhost được coi là secure). Ghi rõ trong README nếu
  demo qua IP LAN.
- `PBKDF2` 210k iterations có thể mất 100–300ms; cache key sau khi derive, không derive lại mỗi lần render.
- `TextEncoder/TextDecoder` dùng UTF-8; title tiếng Việt phải roundtrip đúng.
- Nếu `enc_mode=tide` nhưng SDK lỗi: fallback có kiểm soát, hiện banner và ghi audit/telemetry nhẹ;
  không âm thầm đổi chế độ mà không thông báo.

## Definition of Done

- [ ] Vault là trang `/` cho owner; luồng mở khoá → tải lên → xem → xoá chạy được.
- [ ] Crypto test xanh; bundle dùng được cho issue 08.
- [ ] Không lộ plaintext/key/passphrase; Blob URL được revoke.
- [ ] README ghi passphrase demo, chế độ crypto, giới hạn 10 MB.
