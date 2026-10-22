# 05 — PLAYBOOK: API, seed, quy tắc phạm vi

**SRS:** FR-PB-01..03 · **WP:** WP7 · **Ưu tiên:** P1 · **Phụ thuộc:** 01

## Mục tiêu

Owner xem được danh sách playbook (lĩnh vực) kèm loại giấy tờ bắt buộc/tuỳ chọn, bên nhận và TTL; server
giữ quy tắc cứng: grant không được chứa `doc_type` bị cấm hoặc ngoài phạm vi playbook. Admin chỉ sửa
playbook qua Change Request.

## Hiện trạng

- Đã có `Playbook` model, migration `2026_10_22_100001_create_playbooks_table.php`,
  `PlaybookFactory`, `PlaybookSeeder`, và dữ liệu seed `rental`/`clinic`.
- `Playbook::allowsDocType()` đã kiểm tra required + optional và trừ forbidden.
- `GrantService::assertDocumentsInScope()` đã dùng `allowsDocType()` và kiểm tra owner.
- `DirectChangeController@updatePlaybook` đã trả `409 QUORUM_REQUIRED`; executor xử lý `playbook_update`.
- **Chưa có:** `GET /api/v1/playbooks`, `PlaybookController`, `PlaybookResource`.
- `DemoSeeder` và `PlaybookSeeder` cùng seed playbook/agent → dễ lệch dữ liệu.

## Phạm vi

### Làm

- [ ] `PlaybookController@index`: trả playbook đang `active` cho owner; admin thấy tất cả (hoặc lọc
      `?active=1`). Sắp xếp theo `name`.
- [ ] `PlaybookResource`: `id`, `slug`, `name`, `description`, `required_doc_types`,
      `optional_doc_types`, `forbidden_doc_types`, `recipient_label`, `default_ttl_minutes`,
      `max_ttl_minutes`, `ttl_options` (đã lọc theo config), `version`, `active`.
- [ ] Route `GET /api/v1/playbooks` trong nhóm `auth`, middleware `role:owner,admin`.
- [ ] Hợp nhất seed: `DemoSeeder` gọi `PlaybookSeeder` (hoặc bỏ phần playbook trùng) để một nguồn dữ
      liệu duy nhất; vẫn idempotent.
- [ ] Bổ sung test phạm vi grant:
  - doc_type ngoài required+optional → `422 OUT_OF_SCOPE`.
  - doc_type trong `forbidden_doc_types` → `422 OUT_OF_SCOPE` dù cũng có trong optional (nếu cấu hình sai).
  - doc_type hợp lệ nhưng document của owner khác → `422 OUT_OF_SCOPE` / `NOT_AVAILABLE`.
  - document đã soft delete → không dùng được.
- [ ] (Nếu chưa có) kiểm tra `ChangeRequestExecutor` xử lý `playbook_update` đúng field và tăng
      `version`; test CR playbook update.
- [ ] Cập nhật README: 2 playbook seed, quy tắc required/optional/forbidden, TTL.

### Không làm

- Không làm UI Playbooks (nằm trong issue 07/08 hoặc issue này nếu thời gian; tối thiểu API).
- Không cho admin sửa trực tiếp; giữ CR.
- Không thêm playbook thứ 3 nếu không cần cho demo.

## Hợp đồng API

```http
GET /api/v1/playbooks
Authorization: Bearer <token>
```

```json
{
  "data": [
    {
      "id": "uuid",
      "slug": "rental",
      "name": "Thuê nhà",
      "description": "Hồ sơ thuê nhà",
      "required_doc_types": ["id_card", "income_proof"],
      "optional_doc_types": ["rental_history", "reference_letter"],
      "forbidden_doc_types": ["medical_summary", "lab_result", "insurance_card"],
      "recipient_label": "Chủ nhà",
      "default_ttl_minutes": 60,
      "max_ttl_minutes": 1440,
      "ttl_options": [5, 15, 60, 1440],
      "version": 1,
      "active": true
    }
  ]
}
```

Lỗi: `401 UNAUTHENTICATED`, `403 FORBIDDEN_ROLE`.

## Test

- [ ] Owner thấy 2 playbook seed; admin thấy; agent gọi → 403 `FORBIDDEN_ROLE`.
- [ ] `ttl_options` của `clinic` chỉ còn `[5, 15, 60]` (max 60).
- [ ] `forbidden_doc_types` không nằm trong `required/optional` (seed đúng).
- [ ] Grant chứa `medical_summary` cho playbook `rental` → 422 `OUT_OF_SCOPE`.
- [ ] Grant chứa `lab_result` cho `rental` → 422.
- [ ] Grant chứa `insurance_card` cho `rental` → 422.
- [ ] `GET /playbooks` không trả playbook `active=false` cho owner.
- [ ] CR `playbook_update` thực thi đúng sau đủ quorum; direct PATCH vẫn 409.

## File liên quan

- `app/Models/Playbook.php`.
- `database/migrations/2026_10_22_100001_create_playbooks_table.php`.
- `database/seeders/PlaybookSeeder.php`, `database/seeders/DemoSeeder.php`.
- `app/Services/GrantService.php`, `app/Services/ChangeRequestExecutor.php`.
- `routes/api.php`.
- Mới: `app/Http/Controllers/Api/V1/PlaybookController.php`,
  `app/Http/Resources/PlaybookResource.php`.
- Test: `tests/Feature/Playbook/PlaybookApiTest.php`, bổ sung `tests/Feature/Grant/GrantApiTest.php`.

## Definition of Done

- [ ] `GET /playbooks` có trong route list và trả đúng resource.
- [ ] Test phạm vi grant xanh.
- [ ] Chỉ còn một nguồn seed playbook, chạy lại không nhân đôi dữ liệu.
- [ ] README mô tả playbook và quy tắc.
