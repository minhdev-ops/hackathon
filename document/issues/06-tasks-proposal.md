# 06 — TASK + PROPOSAL: hoàn thiện backend

**SRS:** FR-TASK-01, FR-AGENT-01 · **WP:** WP8 · **Ưu tiên:** P0 · **Phụ thuộc:** 01, 05

## Mục tiêu

Owner tạo task từ playbook + chỉ dẫn; job `GenerateProposalJob` sinh đề xuất
`{required, optional, rationale}` bằng `MockLlmClient` (mặc định) hoặc `AnthropicLlmClient`; đề xuất
bị cắt về đúng phạm vi playbook trước khi lưu. Task có trạng thái, log và lỗi rõ ràng.

## Hiện trạng

Phần lớn code vừa được thêm (22/10 13:51–13:55), nhưng **chưa nối dây xong**:

- `app/Http/Controllers/Api/V1/TasksController.php` có `store/index/show`.
- `app/Jobs/GenerateProposalJob.php`, `app/Llm/{LlmClient,MockLlmClient,AnthropicLlmClient}.php`,
  `config/llm.php`, `app/Services/TaskService.php`, `app/Policies/TaskPolicy.php`,
  `app/Http/Requests/StoreTaskRequest.php`, `app/Http/Resources/TaskResource.php` đã có.
- `routes/api.php` đã thêm 3 route `/tasks` nhưng **thiếu import**
  `use App\Http\Controllers\Api\V1\TasksController;` → class not found.
- `AppServiceProvider` **chưa bind** `LlmClient` → job không resolve được.
- `GenerateProposalJob` chưa bắt exception từ LLM; task có thể kẹt `proposing`.
- `GenerateProposalJob` hiện đã tạo `Grant::STATUS_PENDING` sau proposal và chuyển task sang
  `awaiting_approval`; cần giữ hành vi này và test.
- `TaskResource` chưa trả grant đang hoạt động để UI mở Consent Review.

## Phạm vi

### Làm

- [ ] Sửa import `TasksController` trong `routes/api.php`; kiểm tra `php artisan route:list`.
- [ ] Bind `LlmClient` trong `AppServiceProvider`:
  - `LLM_DRIVER=mock` → `MockLlmClient`.
  - `LLM_DRIVER=anthropic` → `AnthropicLlmClient` với `config('llm.anthropic.api_key')` và model;
    thiếu API key → lỗi cấu hình rõ ràng, không gọi mạng.
  - Dùng singleton để không tạo client mỗi job.
- [ ] `GenerateProposalJob`:
  - Dispatch sau commit: `GenerateProposalJob::dispatch($task->getKey())->afterCommit()` trong
    `TaskService`/controller.
  - Bọc `$llm->propose()` trong `try/catch Throwable`; ghi log `proposal.failed` với `reason` ngắn
    (không ghi prompt/instruction đầy đủ), đặt trạng thái lỗi rõ ràng (thêm `AgentTask::STATUS_FAILED`
    nếu cần; cập nhật UI label ở issue 07).
  - Chuẩn hoá proposal: `required` và `optional` là list string duy nhất, giao nhau thì ưu tiên
    `required`; cắt theo `Playbook::allowedDocTypes()`; luôn có `rationale` string.
  - Lưu `proposal`; append log `proposal_generated` chỉ với doc types.
  - Tạo `grant` pending (`firstOrCreate` theo task/status) và chuyển task sang `awaiting_approval`;
    append log `awaiting_approval` kèm `grant_id`.
  - Không tạo grant nếu proposal rỗng/không có doc type hợp lệ; ghi log lỗi rõ.
- [ ] `TaskService`:
  - Kiểm tra playbook `active`; agent `active`; nếu không có agent → lỗi `AGENT_UNAVAILABLE`.
  - Ghi audit `task.created` (đã có).
  - Không nhận instruction rỗng/quá dài (request đã validate).
- [ ] `TasksController@index`: dùng `$request->user()->id` thay vì `auth()->id()` để tương thích JWT.
- [ ] `TaskResource`: thêm `active_grant`:
  ```json
  { "id": "...", "status": "pending|active|...", "expires_at": "...", "remaining_seconds": 900 }
  ```
  và `playbook.recipient_label`, `playbook.required_doc_types`, `playbook.optional_doc_types`.
- [ ] Route: `auth` + `role:owner` cho create/list/show (hoặc policy-only; policy đã chặn create).
      Throttle 60/phút.
- [ ] `TaskPolicy`: giữ `view` owner; thêm `create` owner; không cho admin/agent xem task của owner khác.
- [ ] Cập nhật `.env.example`/README: cần chạy `php artisan queue:work` cho job; `LLM_DRIVER=mock` mặc định.

### Không làm

- Không làm UI Task detail/Agent Console (issue 07).
- Không gọi LLM thật trong test/CI.
- Không gửi toàn bộ tài liệu/plaintext vào prompt LLM.

## Hợp đồng API

```http
POST /api/v1/tasks
{ "playbook_slug": "rental", "instruction": "Nộp hồ sơ thuê căn hộ A" }

201 {
  "data": {
    "id": "uuid",
    "status": "proposing",
    "instruction": "...",
    "proposal": null,
    "result": null,
    "log": [{ "step": "task_created", "at": "..." }],
    "agent": { "id": "...", "name": "TrustGate Agent" },
    "playbook": { "id": "...", "slug": "rental", "name": "Thuê nhà" },
    "active_grant": null,
    "created_at": "..."
  }
}
```

`GET /api/v1/tasks`, `GET /api/v1/tasks/{task}` trả cùng cấu trúc.

Lỗi: `422 PLAYBOOK_NOT_FOUND`, `422 AGENT_UNAVAILABLE`, `403 FORBIDDEN`, `401`.

## Test

- [ ] `Queue::fake()`: POST task → 201, status `proposing`, `GenerateProposalJob` được push.
- [ ] Chạy job với `MockLlmClient`: task chuyển `awaiting_approval`; grant `pending` được tạo; proposal
      đúng playbook; `rationale` string.
- [ ] LLM trả doc type ngoài playbook → proposal lưu không chứa nó.
- [ ] LLM trả trùng required/optional → không trùng, required thắng.
- [ ] LLM ném exception → task không kẹt im lặng; log `proposal.failed`; status lỗi rõ.
- [ ] Owner khác gọi `GET /tasks/{id}` → 403.
- [ ] Agent gọi `POST /tasks` → 403.
- [ ] Playbook inactive → 422.
- [ ] `LLM_DRIVER=anthropic` thiếu key → lỗi cấu hình, không gọi HTTP.
- [ ] `php artisan route:list` không còn lỗi class not found.

## File liên quan

- `routes/api.php`.
- `app/Providers/AppServiceProvider.php`.
- `app/Http/Controllers/Api/V1/TasksController.php`.
- `app/Jobs/GenerateProposalJob.php`.
- `app/Llm/*`, `config/llm.php`.
- `app/Services/TaskService.php`, `app/Policies/TaskPolicy.php`.
- `app/Http/Requests/StoreTaskRequest.php`, `app/Http/Resources/TaskResource.php`.
- `app/Models/AgentTask.php` (nếu thêm status).
- Test: `tests/Feature/Task/TaskApiTest.php`, `tests/Feature/Agent/GenerateProposalJobTest.php`.

## Ghi chú

- `AgentTask::appendLog()` đang lưu mảng phẳng `{step, at, ...context}`. Issue 07 sẽ thống nhất shape với
  frontend; nếu đổi sang `{step, at, context}` phải sửa cả `AdminEvil`/`SlowRunAgentJob`.
- `AnthropicLlmClient` hiện gọi `claude-3-haiku-20240307` mặc định; model trong `.env.example` là
  `claude-3-5-sonnet-latest` — thống nhất một giá trị.
- Không đưa `instruction` vào audit meta nếu có thể chứa dữ liệu nhạy cảm; chỉ lưu `playbook_slug`.

## Definition of Done

- [ ] Tạo task qua API chạy được với queue worker; proposal sẵn sàng cho Consent Review.
- [ ] Test task/proposal xanh.
- [ ] Không còn lỗi wiring `TasksController`/`LlmClient`.
- [ ] README ghi cách chạy queue và LLM driver.
