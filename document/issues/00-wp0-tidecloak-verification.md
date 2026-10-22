# 00 — WP0: Xác minh TideCloak V1–V7

**SRS:** §2.3, §12, §13.3 · **WP:** WP0 · **Ưu tiên:** P0 — cổng quyết định · **Phụ thuộc:** không

## Mục tiêu

Chốt bằng văn bản những gì TideCloak thực sự làm được trước khi viết thêm code. Điền đầy đủ bảng
«Nhật ký xác minh» (mục 12 SRS) và chọn chế độ `tide` hay `fallback`. Kết quả quyết định toàn bộ
kiến trúc crypto, auth và quorum.

## Vì sao là P0

Nếu cả V1 (mã hoá MPC trong trình duyệt) và V4 (QEA cho thay đổi admin realm) đều không đạt, sản phẩm
vẫn chạy được ở mức app nhưng TideCloak chỉ còn là «ô đăng nhập». Khi đó phải đổi cách kể chuyện demo
và ghi rõ giới hạn trong README, trước khi tiếp tục đầu tư vào Vault/Quorum.

## Cần xác minh

| Mã | Câu hỏi | Cách xác minh | Phương án nếu không đạt |
| --- | --- | --- | --- |
| V1 | SDK TypeScript có `encrypt/decrypt` dữ liệu tuỳ ý trong browser qua MPC không? | Đọc SDK/docs; thử một đoạn mã nhỏ trong Vite; hỏi Tide MCP/Discord #support | `fallback`: WebCrypto AES-GCM + PBKDF2; banner «Chế độ dự phòng, không trustless» |
| V2 | `@tidecloak/react` chạy trong Vite; access token có `sub` và `realm_access.roles`? | Cài thử, đăng nhập, decode token | OIDC chuẩn qua `oidc-client-ts`, cùng JWKS |
| V3 | Có dùng quyền/vai trò Tide để bảo vệ K_g không? | Hỏi API/role mapping; thử đọc key bằng role | K_g nằm Redis TTL; ghi rõ server đọc được trong hạn grant |
| V4 | Cấu hình QEA cho thay đổi admin realm; app đọc được trạng thái? | Admin console + API trạng thái | Quorum mức app (`change_requests`) + re-auth |
| V5 | Gọi Sign/Authenticate để tạo chữ ký phê duyệt CR? | SDK call thử; xác nhận verify | Lưu approval + re-auth, không có chữ ký |
| V6 | Có service account/client riêng cho agent? | Tạo client, lấy token | Token demo do backend cấp khi `APP_DEMO=true` |
| V7 | Mở khoá khẩn cấp có người thân duyệt? | Hỏi Tide | Bỏ khỏi prototype |

## Việc phải làm

- [ ] Liên hệ Tide (MCP/Discord #support) hoặc đọc docs chính thức; ghi nguồn + ngày + người trả lời.
- [ ] Thử nghiệm thực tế tối thiểu cho V1, V2, V4 (không chỉ đọc docs).
- [ ] Điền bảng mục 12 SRS cho V1–V7: `Kết quả`, `Nguồn`, `Quyết định`.
- [ ] Chọn `ENC_MODE=tide|fallback`; ghi lý do.
- [ ] Chọn `AUTH_MODE=tide|oidc|session` cho demo; ghi rõ đây là tạm hay chính thức.
- [ ] Ghi vào README mục «TideCloak làm / nhóm tự xây» và giới hạn đã biết.
- [ ] Nếu V1/V4 không đạt: cập nhật kịch bản demo 2 phút để không overclaim.

## Acceptance criteria

- Mục 12 SRS không còn ô trống; mỗi kết quả có nguồn và ngày.
- Có quyết định bằng văn bản cho `ENC_MODE`, `AUTH_MODE`, `K_g` storage, chữ ký CR.
- README nói đúng chế độ đang chạy, không dùng câu khẳng định quá mức như «AI không bao giờ thấy dữ liệu».
- Các issue 01/03/08 có thể đọc quyết định này và không phải hỏi lại.

## Không thuộc phạm vi

- Không cần implement Vault/Auth trong issue này.
- Không cần chứng minh mọi tính năng Tide; chỉ cần đủ để chọn hướng và nói thật.

## File liên quan

- `document/SRS – TrustGate (TideCloak Hackathon)(1).md` — §2.3, §12, §13.3.
- `README.md` — mục TrustGate/TideCloak.
- `config/trustgate.php` — nơi sẽ thêm `enc_mode` nếu chọn fallback.

## Definition of Done

- Bảng V1–V7 hoàn chỉnh trong SRS, có link/nguồn.
- Quyết định chế độ được ghi trong README và issue 04/01.
- Không còn blocker «chưa biết Tide có hỗ trợ không» cho các issue sau.
