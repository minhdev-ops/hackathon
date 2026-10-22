# 07 — AGENT RUN + TASK DETAIL + CONSOLE

**SRS:** FR-AGENT-02..03, §7 · **WP:** WP8 + WP10 · **Ưu tiên:** P1 · **Phụ thuộc:** 06, 08

## Mục tiêu

Khi grant `active`, `RunAgentJob` mở bundle, chạy từng bước, ghi log và lưu kết quả. Nếu grant bị thu
hồi giữa chừng, job dừng ở bước kế tiếp với `failed_revoked`. Frontend có `/tasks/:id` (trạng thái, đề
xuất, Consent Review, log, kết quả, nút Thu hồi) và `/agent-console` (log theo thời gian thực + nút xin
tài liệu ngoài phạm vi).

## Hiện trạng

- `app/Jobs/RunAgentJob.php` đã có: mở grant, lặp steps, kiểm tra task/grant/key trước mỗi bước, lưu
  `result`.
- `app/Jobs/SlowRunAgentJob.php` có cho demo, nhưng `DemoController::slowRun` không đưa K_g vào
  `GrantKeyStore` → job dừng ngay `grant_key_shredded`.
- `GrantService::approve()` dispatch `RunAgentJob` **trong transaction**, chưa `afterCommit()`.
- `RunAgentJob` không bắt `GrantUnavailableException` từ `$grants->open()` → job fail, task có thể kẹt
  `running`.
- `AgentTask::appendLog()` đang trộn `step` (tên event) với `context['step']` (số bước) → log mất tên
  event. `AdminEvil` lại đọc `entry.context` nên không khớp.
- Chưa có `/tasks/:id`, `/agent-console`; chưa có polling.
- `RunAgentJob` result mới là mock (`submission` string), chưa ghép trường tài liệu.

## Phạm vi

### Backend

- [ ] `GrantService::approve()`: `RunAgentJob::dispatch($task->getKey())->afterCommit()` để job không
      chạy trước khi grant/bundle commit.
- [ ] `RunAgentJob`:
  - Bọc `$grants->open($grant)` trong `try/catch GrantUnavailableException` → task `failed_revoked`,
    log `run.aborted` reason `grant_unavailable`.
  - Bọc toàn bộ `handle()` trong `try/catch Throwable`; log `run.failed` (không lộ plaintext/K_g),
    đặt `failed_revoked` hoặc `failed` rõ ràng; không để task kẹt `running`.
  - Kiểm tra grant/key **trước mỗi bước** (đã có); giữ.
  - Kết quả mock nên ghép metadata tài liệu đã mở (doc_id, doc_type, name) và `submission`; không lưu
    `content_b64`/plaintext vào `agent_tasks.result` (NFR-SEC-01).
  - Log dùng shape thống nhất: `{ "event": "run.step", "at": "...", "context": { ... } }`.
- [ ] `AgentTask::appendLog(string $event, array $context = [])`:
  - Đổi shape sang `event` + `context`; cập nhật mọi call site (`TaskService`, `GrantService`,
    `RunAgentJob`, `SlowRunAgentJob`, `DemoSeeder` nếu có).
  - Hoặc giữ `step` nhưng đổi key số bước thành `index`; **không** để context ghi đè event. Chọn một
    shape và ghi vào issue 10/AdminEvil.
- [ ] `SlowRunAgentJob`: sửa cùng shape; kiểm tra grant/key; dừng khi `failed_revoked` (issue 10 sẽ
      đưa K_g vào store).
- [ ] `TaskResource` trả `log` mới; `GET /tasks/{id}` là nguồn poll.
- [ ] Test:
  - Grant active, đủ key/bundle → job chạy hết, task `done`, result có doc metadata, không có plaintext.
  - Revoke giữa bước 2 → task `failed_revoked`, log dừng ở bước kế tiếp, không chạy tiếp.
  - Grant hết hạn trước khi mở → `failed_revoked`/`run.aborted`, không exception ra queue.
  - `approve()` dùng `afterCommit`: với `Queue::fake`, job được push sau commit.
  - Không có `content_b64`/plaintext/K_g trong `result`/log.

### Frontend

- [ ] `resources/js/pages/TaskDetail.jsx` (`/tasks/:id`):
  - Fetch task; hiện trạng thái (map nhãn tiếng Việt), playbook, agent, instruction, proposal.
  - Hiện Consent Review (component issue 08) khi status `proposal_ready`/`awaiting_approval` và grant
    `pending`; sau khi approve hiện countdown + nút Thu hồi.
  - Hiện log dạng timeline, mỗi dòng `event` + giờ + context; tự cuộn xuống.
  - Poll `GET /tasks/{id}` mỗi 1 giây khi `proposing`/`running`/`awaiting_approval`; dừng khi
    `done`/`failed_revoked`/`denied`; cleanup interval khi unmount.
  - Hiện `result.submission`, `document_count`; nút «Xem grant»/«Thu hồi».
- [ ] `resources/js/pages/AgentConsole.jsx` (`/agent-console`):
  - Danh sách task gần đây hoặc chọn task; panel log realtime (poll 1s).
  - Nút «Xin tài liệu ngoài phạm vi» gọi endpoint demo (issue 10), hiện `OUT_OF_SCOPE` rõ ràng.
  - Trạng thái rỗng/loading/lỗi tiếng Việt.
- [ ] `App.jsx` thêm route + guard owner/agent; link từ Playbooks/Vault.
- [ ] Không dùng SSE nếu polling đủ; nếu dùng SSE phải có cleanup và fallback polling.

### Không làm

- Không làm Consent Review chi tiết (issue 08) — chỉ nhúng component.
- Không lưu plaintext tài liệu vào state lâu hơn cần thiết; xoá khi đóng viewer.

## Acceptance criteria

- [ ] Approve grant → task chuyển `running`, log có `run.started` và từng bước.
- [ ] Thu hồi giữa chừng → trong ≤ 5 giây task `failed_revoked`; log ghi bước dừng và lý do.
- [ ] Task done → result có metadata tài liệu, không có nội dung plaintext.
- [ ] `/tasks/:id` hiển thị đúng trạng thái/proposal/log/result; poll dừng đúng lúc.
- [ ] `/agent-console` hiển thị log theo thời gian thực; nút out-of-scope trả lỗi đúng.
- [ ] Refresh trang vẫn xem lại được log/result từ DB.
- [ ] Không có interval/Blob URL rò rỉ sau unmount.

## Test

- [ ] PHPUnit: `RunAgentJobTest`, `GrantApproveDispatchTest` (afterCommit).
- [ ] PHPUnit: revoke mid-run với `Queue::fake`/job chạy đồng bộ; assert status/log.
- [ ] Manual: approve → xem console; revoke → xem dừng; mở lại task sau reload.
- [ ] `npm run build` xanh.

## File liên quan

- `app/Jobs/RunAgentJob.php`, `app/Jobs/SlowRunAgentJob.php`.
- `app/Services/GrantService.php` (dispatch afterCommit).
- `app/Models/AgentTask.php` (`appendLog`), `app/Http/Resources/TaskResource.php`.
- `resources/js/components/App.jsx`, `resources/js/api/client.js`.
- Mới: `resources/js/pages/TaskDetail.jsx`, `resources/js/pages/AgentConsole.jsx`,
  `resources/js/components/agent/LogTimeline.jsx`.
- Test: `tests/Feature/Agent/RunAgentJobTest.php`.

## Definition of Done

- [ ] Job chạy/ dừng đúng; log shape thống nhất; UI đọc được.
- [ ] Test revoke giữa chừng xanh.
- [ ] Không có plaintext/K_g trong result/log.
- [ ] README mô tả cách chạy queue và xem console.
