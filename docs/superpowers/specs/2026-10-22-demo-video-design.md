# Design: TrustGate — video demo tất cả chức năng, mọi vai trò

- **Ngày:** 2026-10-22
- **Trạng thái:** Draft → chờ user review
- **Phạm vi:** một video demo sản phẩm, quay từ hệ thống thật

---

## 1. Mục tiêu

Tạo **một video demo** giới thiệu TrustGate (két dữ liệu + consent broker cho AI agent) quay từ
**hệ thống thật đang chạy** — không dựng lại giao diện, không mock. Video phải phủ được **mọi chức
năng chính** và **cả ba vai trò** (`owner`, `admin`, `agent`) trong thời lượng **dưới 3 phút**.

Video là tài sản trình bày: dùng được cho hackathon demo, đăng trên landing page, hoặc gửi cho
reviewer kỹ thuật. Vì vậy nó phải **chạy lại được** (không phải sản phẩm thủ công một lần) và
phải **nói thẳng** phần nào chưa được xác minh (đúng tinh thần README).

## 2. Các quyết định đã chốt (từ user)

| Hạng mục | Quyết định |
| --- | --- |
| Lời dẫn | **Tiếng Anh** (UI vẫn tiếng Việt) |
| Thời lượng | **Dưới 3 phút** (mục tiêu ~2:45) |
| Cấu trúc | **Nhiều cảnh + tiêu đề mở đầu từng phần** (title card) |
| Mục đích | **Cả hai**: lớp 1 kể chuyện (đầu video), lớp 2 giải thích kỹ thuật (từ giữa video trở đi) |
| Phụ đề | Burn-in **tiếng Anh**, khớp lời dẫn |

## 3. Sản phẩm bàn giao

| Đường dẫn | Nội dung |
| --- | --- |
| `demo-video/out/trustgate-demo.mp4` | Video cuối: 1920×1080, H.264/AAC, ~2:45, có lời dẫn + phụ đề |
| `demo-video/out/trustgate-demo.storyboard.md` | Shot list có timestamp từng cảnh, lời dẫn, và trạng thái từng cảnh |
| `scripts/demo-video/**` | Toàn bộ pipeline, chạy lại được bằng một lệnh |
| `demo-video/out/` | gitignored (trừ `*.md` storyboard nếu user muốn commit) |

**Không** sửa file nào trong `app/`, `resources/`, `routes/`, `database/`. Video là sản phẩm phụ
trợ, không được làm hỏng app.

## 4. Kiến trúc pipeline

```
scripts/demo-video/
├── README.md                 # hướng dẫn chạy lại
├── storyboard.json           # nguồn sự thật duy nhất: cảnh, title card, lời dẫn
├── setup.sh                  # tạo venv, cài edge-tts + imageio-ffmpeg, link ffmpeg
├── playwright.config.js      # config Playwright riêng cho quay video
├── global-setup.mjs          # đăng nhập sẵn 3 vai trò → storageState
├── record.spec.mjs           # 12 cảnh, mỗi cảnh 1 test có recordVideo
├── titles.mjs                # render title card PNG (Playwright) theo design token
├── narrate.py                # edge-tts: mp3 + WebVTT từng cảnh
├── assemble.py               # ffmpeg: ghép, mux âm thanh, burn phụ đề, xuất mp4
├── build.sh                  # orchestrate toàn bộ: setup → record → narrate → assemble
└── bin/ffmpeg                # symlink tới static ffmpeg của imageio-ffmpeg (gitignored)
```

### 4.1 Stage 1 — `setup.sh`

- Tạo venv Python tại `scripts/demo-video/.venv/` (`python3 -m venv`).
- `pip install edge-tts imageio-ffmpeg`.
- Symlink `bin/ffmpeg` → binary của `imageio_ffmpeg` (không cài ffmpeg hệ thống).
- **Đã kiểm chứng**: binary này có `libx264`, `aac`, muxer `mp4`, filter `ass`/`subtitles` (libass),
  filter `overlay`. **Không** có `drawtext` (không build với freetype) → mọi text trong video phải
  đi qua **libass** hoặc **render bằng Playwright**. **Không** có `x11grab` → không thể quay màn
  hình thật, chỉ quay được video do Playwright ghi.

### 4.2 Stage 2 — `storyboard.json`

Mảng 12 scene. Mỗi scene có:

```jsonc
{
  "id": "vault",                 // slug, dùng làm tên file
  "card": "1 · The Vault",       // tiêu đề mở đầu phần (null = không có card)
  "role": "owner",               // vai trò đăng nhập cho cảnh này
  "narration": "…",              // lời dẫn tiếng Anh
  "target": 13                   // giây (mục tiêu, dùng để kiểm tra ngân sách thời gian)
}
```

Đây là nguồn sự thật duy nhất: `record.spec.mjs` đọc nó để biết cảnh nào cần chạy, `narrate.py`
đọc nó để sinh audio, `assemble.py` đọc nó để ghép. Thêm/bớt cảnh = sửa đúng một file.

### 4.3 Stage 3 — `narrate.py` (edge-tts)

**Chạy TRƯỚC khi quay**, vì độ dài lời dẫn quyết định độ dài từng cảnh quay.

- Voice: `en-US-AndrewNeural` (male, warm/confident) — hợp lớp kể chuyện.
- Rate: `+0%`; nếu cảnh vượt `target` giây thì tăng `+10%` cho cảnh đó (mục tiêu: lời dẫn vừa
  khít `target`).
- Xuất `--write-media scene.mp3` và `--write-subtitles scene.vtt` (edge-tts cho word-boundary
  timing → dùng để dựng ASS chính xác).
- **Retry**: 3 lần, backoff 2s/5s/10s. Nếu vẫn fail → fallback `gTTS` (`lang='en'`), vẫn ra MP3
  nhưng **không có VTT** → khi đó phụ đề của cảnh đó suy ra từ câu (chia đều theo tỉ lệ độ dài
  câu) thay vì word-level.
- `edge-tts` đã từng fail 1 lần ("NoAudioReceived") trong lúc khảo sát → retry là bắt buộc, không
  phải tuỳ chọn.
- **Đầu ra**: `demo-video/work/audio/{id}.mp3`, `demo-video/work/audio/{id}.vtt`, và
  `demo-video/work/durations.json` = `{ id: { narration: 12.4, card: 2.0, slot: 13.0 } }`.
  File này là hợp đồng giữa stage quay và stage ghép.

### 4.4 Stage 4 — `record.spec.mjs` (Playwright)

**Môi trường demo riêng** — không đụng DB dev:

| Biến | Giá trị | Lý do |
| --- | --- | --- |
| `DB_DATABASE` | `trustgate_demo` | DB riêng cho demo |
| `APP_DEMO` | `true` | bật trang "Làm admin xấu" (`/demo/*` trả 404 nếu tắt) |
| `QUEUE_CONNECTION` | `database` | `GenerateProposalJob` / `RunAgentJob` chạy nền → phải có worker |
| `CACHE_STORE` | `file` | tránh phụ thuộc Redis |
| `GRANT_KEY_STORE` | `file` | `K_g` nằm trong cache, TTL theo grant |
| `SESSION_DRIVER` | `database` | đồng bộ với harness E2E hiện có |
| cổng | `8010` | tách khỏi dev (8000) và E2E (8001) |

Script `scripts/demo-video/demo-server.sh` (mô phỏng `scripts/e2e-server.sh`): tạo DB →
`migrate:fresh --seed` → `optimize:clear` → `storage:link` → chạy `queue:work` →
chạy `artisan serve --port=8010`.

**Ghi hình**: `recordVideo: { dir, size: { width: 1920, height: 1080 } }`, `viewport 1920×1080`,
`channel: 'chrome'`, `headless: true`, `slowMo` nhẹ. Mỗi scene là **một test riêng** → Playwright
tạo một file `.webm` cho mỗi test, đặt tên theo title.

**Đổi vai trò nhanh**: `global-setup.mjs` đăng nhập trước `lan@example.com`,
`admin1@example.com`, `admin2@example.com`, `agent@example.com` (mật khẩu `password`) và lưu
`storageState` vào `scripts/demo-video/.auth/{role}.json`. Mỗi scene dùng
`test.use({ storageState })` theo `role` trong storyboard → không phải gõ lại mật khẩu 12 lần.

**Độ dài cảnh (quan trọng — tránh lệch A/V)**: mỗi scene có một *slot* thời gian:

```
slot_i   = narration_i + 0.6s          (0.6s là khoảng lặng đệm)
card_i   = 2.0s nếu cảnh có title card, ngược lại 0
video_i  = slot_i - card_i             (thời lượng mà screen recording phải đúng bằng)
```

- Recorder **đọc `durations.json`** để biết `video_i` và **chủ động đợi** cho tới khi đủ
  `video_i` giây đã trôi qua — thời gian chờ đó **giữ nguyên trạng thái UI cuối cùng** (không
  `sleep` trên một element đang chuyển động), nên video tự nhiên đúng độ dài mong muốn và khung
  hình cuối không bị "đóng băng" giật cục.
- Nếu hành động trong cảnh **chạy lâu hơn** `video_i` → `assemble.py` cắt còn `video_i` và **in
  cảnh báo** (không cắt lặng lẽ).
- `assemble.py` cắt chính xác `video_i` cho mọi cảnh → tổng thời lượng = tổng `slot_i` → audio và
  video **không bao giờ trôi**.

**Kiểm tra sau khi quay**: mỗi `.webm` phải tồn tại và `size > 20KB`; nếu không thì dừng ngay
(tránh ghép video rỗng). Đây là bảo vệ cho lỗi "Channel closed" từng gặp ở lần thử trước.

### 4.5 Stage 5 — `assemble.py` (ffmpeg)

Thứ tự:

1. **Title card**: `titles.mjs` render mỗi card thành PNG 1920×1080 bằng Chromium, dùng **đúng
   design token của app** (`resources/css/app.css`: `--color-bg`, `--color-text`, `--color-accent`,
   `--color-muted`, `--radius-lg`, font `Instrument Sans` → fallback `Lato`/`DejaVu Sans`).
   Card gồm: badge `TG`, tên section, và số thứ tự.
2. **Mỗi cảnh**: ghép `card_i` (nếu có) + `video_i` thành một clip dài đúng `slot_i`:
   - Card: `-loop 1 -t {card_i}` + `fade=t=in:st=0:d=0.4,fade=t=out:st={card_i-0.4}:d=0.4`.
   - Video cảnh: cắt chính xác còn `video_i` bằng `-t {video_i}`.
   - Nếu video cảnh **ngắn hơn** `video_i` (do recorder chưa đủ giây) → bù bằng
     `tpad=stop_mode=clone:stop_duration=…` đóng băng frame cuối.
3. **Ghép**: `concat` filter toàn bộ clip theo thứ tự storyboard.
4. **Âm thanh**: nối các MP3 theo đúng thứ tự, chèn 0.15s silence giữa các cảnh → một track.
5. **Phụ đề**: VTT → ASS (hàm `vtt_to_ass` nhúng trong `assemble.py`), style: Lato 42px,
   `Alignment=2` (bottom-center), `MarginV=48`, `BackColour=&H80000000`, `Outline=2`.
   Burn bằng filter `ass=...:fontsdir=/usr/share/fonts` (libass đọc file font hệ thống).
6. **Xuất**: `-c:v libx264 -preset slow -crf 20 -pix_fmt yuv420p -r 30 -movflags +faststart
   -c:a aac -b:a 192k`.

**Đồng bộ A/V**: nhờ công thức ở §4.4, mỗi slot dài đúng bằng `narration + 0.6s` → audio và video
không bao giờ trôi. Đây cũng là cơ chế **ép tổng thời lượng < 3 phút**.

### 4.6 Ngân sách thời gian

| Thành phần | Giây |
| --- | --- |
| Lời dẫn (12 cảnh) | ~150 |
| Title card (6 card × 2.0s) | ~12 |
| Padding sau mỗi cảnh (12 × 0.6s) | ~7 |
| **Tổng** | **~169s ≈ 2:49** |

Dự phòng ~11s dưới mốc 3 phút. Quy tắc tự động của `assemble.py` khi vượt ngân sách, theo thứ tự
ưu tiên: (1) rút padding 0.6s → 0.4s mỗi cảnh, (2) rút card 2.0s → 1.5s, (3) **fail** kèm báo
cáo scene nào vượt. Không bao giờ cắt lời dẫn để giữ đúng mốc thời gian.

## 5. Storyboard (12 cảnh)

| # | id | Title card | Shot (vai trò) | Lớp |
| --- | --- | --- | --- | --- |
| 1 | `login` | *TrustGate* (mở đầu) | login → trang chủ (owner) | kể chuyện |
| 2 | `vault` | `1 · The Vault` | /vault — bảng tài liệu, tiêu đề hiện **bản mã** (owner) | kể chuyện |
| 3 | `scope` | `2 · Purpose` | /playbooks → /tasks/new → đề xuất của LLM (owner) | kể chuyện |
| 4 | `consent` | `3 · Consent` | /grants/{id}: mở két, bỏ tài liệu tuỳ chọn, TTL 15, bấm Duyệt (owner) | kể chuyện |
| 5 | `agent-run` | `4 · The Agent Runs` | /agent-console — log từng bước, task `done` (owner) | kể chuyện |
| 6 | `db-dump` | `5 · The Server Sees Nothing` | /admin/evil → "1. Đổ database" → chỉ thấy ciphertext (admin) | kỹ thuật |
| 7 | `elevate` | `6 · One Admin Isn't Enough` | "2. Tự cấp quyền" → `409 QUORUM_REQUIRED` (admin) | kỹ thuật |
| 8 | `out-of-scope` | `7 · Scope Is Enforced` | "3. Agent xin ngoài phạm vi" → `403 OUT_OF_SCOPE` (admin) | kỹ thuật |
| 9 | `quorum` | `8 · Quorum` | admin1 tạo CR + re-auth → admin2 duyệt → 2/2 executed (admin1, admin2) | kỹ thuật |
| 10 | `revoke` | `9 · Revocable` | thu hồi grant giữa chừng → `failed_revoked` (owner) | kỹ thuật |
| 11 | `audit` | `10 · Audit Chain` | /timeline → "Xác minh chuỗi" → "Chuỗi hợp lệ" (admin) | kỹ thuật |
| 12 | `outro` | *TrustGate* (kết) | end card: stack + ghi chú giới hạn | kết |

**Cảnh 9 (quorum) — quyết định**: cần **hai** admin. Quay bằng **hai context song song**
(admin1 tạo CR + re-auth ở cửa sổ trái, admin2 duyệt ở cửa sổ phải), rồi ghép bằng filter
`hstack` (mỗi cửa sổ 960×1080). Giữ cảm giác "hai admin cùng ký". Nếu sau khi xem thử thấy chữ
quá nhỏ để đọc thì chuyển sang quay **tuần tự** (admin1 → logout → admin2) — nhưng phải giữ
tổng độ dài slot, nên cảnh này dài hơn các cảnh khác ~4s.

### 5.1 Lời dẫn (bản nháp, chỉnh trong `storyboard.json`)

| id | Lời dẫn (EN) | ~s |
| --- | --- | --- |
| `login` | An AI agent wants your whole document folder. TrustGate gives it only what you approve — for one purpose, for a set time, and revocable at any moment. This is TrustGate. | 15 |
| `vault` | Your documents are encrypted in the browser with AES-256-GCM, using a key derived from your passphrase. The server only ever stores ciphertext — look at the titles. | 12 |
| `scope` | A playbook defines the allowed scope for a purpose. You describe the task, and the agent proposes the minimum set of documents it needs. | 11 |
| `consent` | Now you decide. Unlock the vault, uncheck the optional document, choose a fifteen-minute lifetime. Required documents cannot be removed — the server enforces the scope, not the browser. | 14 |
| `agent-run` | The agent opens the grant and receives only the approved documents, sealed to a grant key that expires with the grant. Every step is logged. | 12 |
| `db-dump` | Now the technical layer. As an administrator, dump the entire database. Every document is ciphertext. No plaintext, no keys, and your passphrase never reaches the server. | 13 |
| `elevate` | Try to grant yourself administrator. Direct changes are always rejected — four-oh-nine, quorum required. One admin can never escalate alone. | 11 |
| `out-of-scope` | And the agent cannot ask for anything outside its scope. Requesting medical records here returns four-oh-three, out of scope — and it is written to the audit log. | 12 |
| `quorum` | Legitimate changes go through a change request. Admin one re-authenticates and signs. Admin two signs as well. Two signatures reach the quorum threshold, and only then does the change execute. | 15 |
| `revoke` | Consent is revocable mid-flight. Revoking the grant deletes the grant key, so the agent's next step fails — failed, revoked. | 11 |
| `audit` | Every access is written to an append-only hash chain. Verify the chain and the system proves nothing was altered after the fact. | 11 |
| `outro` | TrustGate: a vault and consent broker for AI agents. Laravel, React, WebCrypto, AES-256-GCM, hash-chained audit. Prototype — encryption is browser-side fallback mode, not yet trustless. | 13 |

## 6. Selector cheat-sheet (đã đọc từ mã nguồn)

| Cảnh | Selector chính |
| --- | --- |
| login | `#email`, `#password`, `button[type="submit"]` (nhãn "Đăng nhập") → `page.url()` ≠ `/login` |
| home | `h1` chứa "Két dữ liệu và Consent Broker cho AI Agent"; link "Bắt đầu với Két tài liệu" |
| header | link "Két tài liệu", "Playbook", "Agent Console", "Nhật ký"; badge OWNER/ADMIN/AGENT; nút "Đăng xuất" |
| vault | bảng `role="grid"`; cột "Tên (mã hoá)"; nút "+ Tải lên tài liệu"; hàng có `doc_type` |
| playbooks | card playbook; nút "Giao việc cho agent" |
| task create | `#task-playbook` (select), `#task-instruction` (textarea), nút "Tạo task" |
| consent | `h1` "Duyệt chia sẻ dữ liệu"; input placeholder "Mật khẩu két"; nút "Mở két"; select TTL; nút "Duyệt"; nút "Thu hồi"; notice "Đã thu hồi grant và xoá khoá." |
| agent console | `h2` "Hoạt động agent"; nút "Xin tài liệu ngoài phạm vi"; danh sách event |
| admin evil | nút "1. Đổ database", "2. Tự cấp quyền", "3. Agent xin ngoài phạm vi"; `h2` "Kết quả: …" |
| quorum | `h1` "TrustGate · Quorum quản trị"; input placeholder "Mật khẩu admin"; nút "Xác thực"; `h2` "Tạo Change Request"; `#cr-type`; nút "Gửi Change Request"; nút "Duyệt"/"Từ chối" trên card; nút "Thử đổi ngưỡng trực tiếp" |
| timeline | `h1` "Nhật ký hoạt động"; nút "Xác minh chuỗi"; `role="status"` chứa "Chuỗi hợp lệ" |

**Cảnh báo selector**: `select` trên trang consent có **nhiều** phần tử → phải scope theo label
(`select#ttl` nếu có id, nếu không thì `page.locator('section').filter(...)`) chứ không dùng
`page.locator('select')` trần. Tương tự `button` có nhãn "Duyệt" trùng ở nhiều nơi → dùng
`.filter({ hasText })` hoặc `getByRole('button', { name: 'Duyệt', exact: true })`.

## 7. Xử lý lỗi và kiểm chứng

| Rủi ro | Phát hiện | Xử lý |
| --- | --- | --- |
| edge-tts fail | `NoAudioReceived` | retry 3× → fallback gTTS |
| Playwright "Channel closed" | file `.webm` 0 byte / thiếu | đóng context đúng cách, không kill browser giữa lúc; kiểm tra size > 20KB sau mỗi cảnh, fail sớm |
| Queue chạy chậm | proposal/task chưa sẵn sàng | `expect.poll` timeout 45s; tăng `DEMO_SLOW_RUN_SLEEP_SECONDS` nếu cần |
| DB bẩn từ lần trước | dữ liệu lệch | `migrate:fresh --seed` mỗi lần chạy |
| Tổng thời lượng > 3 phút | đo sau assemble | `assemble.py` fail khi > 180s, in scene vượt |
| Video hỏng/cắt | ffprobe duration ≠ kỳ vọng | verify duration từng clip trước khi ghép |

**Tiêu chí nghiệm thu** (kiểm tra tự động trước khi bàn giao):

1. `trustgate-demo.mp4` tồn tại, ≥ 1.5 MB, `ffprobe` báo codec `h264`, 1920×1080, duration 150–180s.
2. Có 1 audio stream (aac) và phụ đề đã burn (kiểm tra bằng cách xem frame giữa cảnh).
3. Cả 12 cảnh đều có file video khác 0 byte.
4. Chạy lại `scripts/demo-video/build.sh` từ sạch → sinh ra video mới không cần thao tác tay.
5. Không file nào trong `app/`, `resources/`, `routes/`, `database/` bị thay đổi.

## 8. Ngoài phạm vi

- **Không** sửa 4 test E2E đang đỏ (`01-auth` owner home, `02-consent-grant`, `04-timeline`,
  `06-task-deny`). Nguyên nhân: chúng còn mong UI cũ (`OwnerHome` — file này **không còn** được
  `App.jsx` route tới; `/` giờ render `Home.jsx` theo design system mới). Đây là tồn đọng có sẵn
  từ trước, không liên quan tới video; sẽ báo cáo riêng cho user, không sửa trong khuôn khổ này.
- **Không** bật `TIDECLOAK_QEA`, không đổi `AUTH_MODE=tide`, không bật `ENC_MODE=tide`.
  Video phải nói thẳng ở outro rằng đang chạy chế độ `fallback` (WebCrypto) + `session`.
- **Không** thêm nhạc nền (không có asset audio được cấp phép trong repo).
- **Không** sửa UI/app để "cho video đẹp hơn".

## 9. Rủi ro

| Rủi ro | Mức | Giảm thiểu |
| --- | --- | --- |
| Quay 12 cảnh dễ rớt (UI đổi) | Trung bình | Selector lấy từ mã nguồn hiện tại; mỗi cảnh độc lập; chạy lại được |
| TTS tiếng Anh nghe "robot" | Thấp | Chọn neural voice; có thể đổi `en-US-AvaNeural` |
| Encoding h264 1080p chậm | Thấp | ~3 phút video → vài chục giây với `-preset slow` |
| Demo server đụng port 8010 | Thấp | Kiểm tra port trước khi chạy; `APP_URL` khớp |

## 10. Chạy lại

```bash
bash scripts/demo-video/build.sh          # setup + quay + lời dẫn + ghép video
bash scripts/demo-video/build.sh --record-only   # chỉ quay lại, giữ audio cũ
bash scripts/demo-video/build.sh --assemble-only # chỉ ghép lại từ video + audio có sẵn
```

Đầu ra: `demo-video/out/trustgate-demo.mp4` + `demo-video/out/trustgate-demo.storyboard.md`.

## Phụ lục — hệ thống demo dùng trong video

| Email | Vai trò | Mật khẩu |
| --- | --- | --- |
| `lan@example.com` | owner | `password` |
| `admin1@example.com` | admin | `password` |
| `admin2@example.com` | admin | `password` |
| `agent@example.com` | agent | `password` |

Passphrase két: `password`. Playbook demo: `rental` (thuê nhà), `medical` (khám bệnh).
