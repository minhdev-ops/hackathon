# 01 — AUTH backend: TideCloak JWT/JWKS, role, `/me`

**SRS:** FR-AUTH-01..04 · **WP:** WP2 · **Ưu tiên:** P0 · **Phụ thuộc:** 00 (chế độ auth)

## Mục tiêu

Thay session tạm bằng xác thực TideCloak (hoặc OIDC cùng JWKS nếu V2 không đạt), mapping user theo
`sub`, trả vai trò từ `realm_access.roles`, và thêm `GET /api/v1/me`. Các controller hiện tại không
phải sửa nếu middleware giữ nguyên hợp đồng `auth` + `$request->user()`.

## Hiện trạng

- `routes/api.php` dùng middleware `auth` (session guard) và `role:<role>`.
- `app/Http/Middleware/EnsureRole.php` đã trả `403 FORBIDDEN_ROLE` đúng dạng lỗi.
- `routes/web.php` có `/login` Blade (`LoginController`) — chỉ là tạm.
- **Chưa có:** `VerifyTideJwt`, JWKS cache, `tide_sub`, `GET /me`, config TideCloak.
- `users` hiện có `name`, `email`, `password`, `role`; chưa có `tide_sub`, `vault_kdf_salt`.
- `.env.example` chưa có `TIDECLOAK_URL/REALM/CLIENT_ID/JWKS_CACHE_SECONDS`.

## Phạm vi

### Làm

- [ ] `config/tidecloak.php`: `url`, `realm`, `client_id`, `audience`, `jwks_cache_seconds` (mặc định 600).
- [ ] Migration `add_tide_sub_to_users_table`: `tide_sub` string nullable unique; `vault_kdf_salt` string
      nullable (cho Vault fallback — issue 03 sẽ dùng).
- [ ] Middleware `VerifyTideJwt`:
  - Đọc `Authorization: Bearer <token>`; thiếu/sai → `401 UNAUTHENTICATED`.
  - Verify RS256 bằng JWKS của realm; cache JWKS theo TTL cấu hình; hỗ trợ xoay key theo `kid`.
  - Kiểm tra `iss`, `aud`/`azp`, `exp`, `nbf` (nếu có).
  - Map `sub` → `users.tide_sub`; lần đầu tạo user với `name`/`email` từ claims, `role` lấy từ
    `realm_access.roles` (một trong `owner`, `admin`, `agent`; mặc định `owner` nếu không có).
  - Gắn user vào request để `$request->user()` hoạt động; gọi `Auth::setUser()` nếu cần cho policy.
  - Không ghi token vào log.
- [ ] `GET /api/v1/me`: trả `{ id, name, email, role, tide_sub, vault_kdf_salt }` (không trả password).
- [ ] Đổi nhóm route `/api/v1` sang middleware mới (giữ alias `auth` hoặc đặt tên `tide.auth`).
      Giữ khả năng chạy session trong môi trường test/demo nếu `AUTH_MODE=session` (cấu hình rõ ràng).
- [ ] `EnsureRole` giữ nguyên; bổ sung test cho `403 FORBIDDEN_ROLE`.
- [ ] Cập nhật `.env.example`, README mục đăng nhập.

### Không làm

- Không làm frontend login (issue 02).
- Không thay toàn bộ QRM/Grant; chỉ đảm bảo middleware tương thích.
- Không lưu access token vào DB/session.

## Hợp đồng API

```http
GET /api/v1/me
Authorization: Bearer <access_token>

200 {
  "data": {
    "id": 1,
    "name": "Lan",
    "email": "lan@example.com",
    "role": "owner",
    "tide_sub": "...",
    "vault_kdf_salt": "base64..."
  }
}
```

Lỗi:

```json
{ "error": { "code": "UNAUTHENTICATED", "message": "Chưa đăng nhập hoặc phiên đã hết hạn." } }
```

## Test

- [ ] Token không có → 401.
- [ ] Token sai chữ ký → 401.
- [ ] Token hết hạn → 401.
- [ ] Sai `iss`/`aud` → 401.
- [ ] Token hợp lệ, `sub` mới → tạo user; gọi `/me` hai lần chỉ có một user (FR-AUTH-04).
- [ ] `realm_access.roles = ['admin']` → `/me` trả `admin`; gọi route admin được.
- [ ] `role=owner` gọi route admin → `403 FORBIDDEN_ROLE`.
- [ ] Không có token trong log (`storage/logs/laravel.log`).
- [ ] Test dùng JWKS giả (khoá RSA sinh trong test) để không phụ thuộc mạng.

## File liên quan

- `bootstrap/app.php` — đăng ký middleware alias.
- `routes/api.php`, `routes/web.php`.
- `app/Http/Middleware/EnsureRole.php`.
- `app/Models/User.php` — fillable/casts/relations.
- `config/auth.php`, `.env.example`, `phpunit.xml`.
- Test mới: `tests/Feature/Auth/TideAuthTest.php`.

## Ghi chú triển khai

- Nếu V2 không đạt: dùng `oidc-client-ts` + cùng middleware JWKS; chỉ khác luồng lấy token ở frontend.
- Nếu demo buộc dùng session: đặt `AUTH_MODE=session` và ghi rõ trong README; vẫn phải có `/me` và test
  role. Không giả vờ đã xác thực Tide.
- `vault_kdf_salt` nên sinh một lần khi tạo user (random 16–32 byte, base64). Không trả salt qua route
  công khai; chỉ `/me` cho chính user.

## Definition of Done

- [ ] `php artisan route:list` có `GET api/v1/me`.
- [ ] Test auth xanh (sau khi issue 11 cài `pdo_sqlite`).
- [ ] Không còn route API nào dựa vào session ngoài chế độ `AUTH_MODE=session` được ghi rõ.
- [ ] README cập nhật biến môi trường và cách lấy token demo.
