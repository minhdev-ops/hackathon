# 02 — AUTH frontend: login TideCloak, route guard, Bearer

**SRS:** FR-AUTH-01..03, §7 · **WP:** WP3 · **Ưu tiên:** P1 · **Phụ thuộc:** 01

## Mục tiêu

Thay trang `/login` Blade bằng luồng đăng nhập TideCloak (hoặc OIDC nếu V2 không đạt) trong React.
Access token chỉ nằm trong bộ nhớ; API client gắn `Authorization: Bearer`; route được bảo vệ theo vai
trò; `/me` hiển thị user.

## Hiện trạng

- `resources/js/app.jsx` chỉ có `<BrowserRouter><App /></BrowserRouter>`.
- `resources/js/components/App.jsx` có `/` (Home) và `/admin/evil`; chưa có guard.
- `resources/js/api/client.js` dùng axios `baseURL: '/api/v1'`, không interceptor token.
- `/login` là Blade (`resources/views/auth/login.blade.php`), đăng nhập session.
- Chưa có `AuthProvider`, `useAuth`, `ProtectedRoute`, `oidc-client-ts`, `@tidecloak/react`.

## Phạm vi

### Làm

- [ ] Chọn thư viện theo WP0: `@tidecloak/react` (V2 đạt) hoặc `oidc-client-ts`.
- [ ] Tạo `resources/js/auth/AuthProvider.jsx`:
  - Khởi tạo OIDC (authority/issuer, client_id, redirect_uri, scope `openid profile email`).
  - Lưu access token **trong memory** (React state/ref + module-level getter cho axios).
  - Không dùng `localStorage`/`sessionStorage` cho token.
  - Xử lý callback, refresh token nếu có, logout (xoá memory + gọi end-session nếu có).
  - Gọi `GET /me` sau khi có token; lưu `{id, name, email, role, vault_kdf_salt}`.
- [ ] `resources/js/auth/tokenStore.js`: `getToken()`, `setToken()`, `clearToken()`; axios request
      interceptor đọc token và gắn Bearer.
- [ ] `resources/js/auth/ProtectedRoute.jsx`: chưa đăng nhập → `/login`; sai role → trang 403 tiếng Việt.
- [ ] Trang `/login`: nút «Đăng nhập TideCloak», trạng thái loading/lỗi, không render form mật khẩu.
- [ ] Cập nhật `App.jsx`: bọc `AuthProvider`; route `/` = Vault (issue 04), `/playbooks`, `/tasks/:id`,
      `/agent-console`, `/timeline`, `/admin/quorum`, `/admin/evil`; guard theo role.
- [ ] Sau login: owner → `/`; admin → `/admin/quorum`; agent → `/agent-console` (hoặc trang phù hợp).
- [ ] Logout ở header/sidebar; gọi API logout nếu backend có.
- [ ] Xử lý 401 toàn cục: xoá token, chuyển `/login`, không loop.
- [ ] Nếu dùng session tạm (`AUTH_MODE=session`): vẫn giữ guard dựa trên `/me`, nhưng ghi rõ trong README
      là chưa phải TideCloak.

### Không làm

- Không build Vault UI (issue 04) hay Consent (issue 08), chỉ đặt route/shell.
- Không lưu token vào cookie do JS đọc được.

## Acceptance criteria

- [ ] Chưa đăng nhập vào `/` → chuyển `/login`.
- [ ] Đăng nhập xong `/me` hiển thị đúng tên/role.
- [ ] Owner không vào được `/admin/quorum` (403 UI, không crash).
- [ ] Admin vào `/admin/quorum` được.
- [ ] Refresh trang vẫn còn phiên (OIDC silent refresh hoặc session cookie nếu chế độ session); token
      không xuất hiện trong `localStorage`.
- [ ] Mọi request API có `Authorization` khi ở chế độ JWT.
- [ ] 401 từ API đưa về `/login` với thông báo tiếng Việt.
- [ ] `npm run build` xanh.

## Test

- [ ] Vitest (nếu có): `tokenStore` set/get/clear; interceptor gắn header; guard redirect.
- [ ] Manual: đăng nhập owner/admin; logout; refresh; gọi API sai quyền.
- [ ] Kiểm tra DevTools → Application → Local Storage rỗng token.

## File liên quan

- `resources/js/app.jsx`, `resources/js/components/App.jsx`.
- `resources/js/api/client.js`.
- `resources/views/auth/login.blade.php`, `app/Http/Controllers/Auth/LoginController.php`.
- `package.json`, `vite.config.js`.
- Mới: `resources/js/auth/*`, `resources/js/pages/Login.jsx`.

## Ghi chú

- Nếu V2 đạt nhưng SDK chỉ hỗ trợ React cũ, có thể dùng `oidc-client-ts` + cùng JWKS; không cần ép SDK.
- Cần thống nhất với issue 01 về tên claim vai trò (`realm_access.roles`).
- Không đặt token trong URL fragment lâu hơn mức cần thiết; xoá sau callback.

## Definition of Done

- [ ] Luồng login/logout chạy được với backend issue 01.
- [ ] Guard role hoạt động, không lộ token.
- [ ] README mô tả cách đăng nhập demo và chế độ auth đang dùng.
