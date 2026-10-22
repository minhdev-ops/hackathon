# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: demo-video.spec.js >> 🎬 TrustGate Demo Video - Full Feature Walkthrough >> 01 - Login & Dashboard Overview
- Location: tests/e2e/demo-video.spec.js:15:3

# Error details

```
Error: Channel closed
```

```
Error: expect(page).toHaveURL(expected) failed

Expected pattern: /.*login/
Received string:  "http://127.0.0.1:8001/"

Call log:
  - Expect "toHaveURL" with timeout 15000ms
    26 × locator resolved to <html lang="vi">…</html>
       - unexpected value "http://127.0.0.1:8001/"
  - Target page, context or browser has been closed

```

```yaml
- banner:
  - link "Trang chủ TrustGate":
    - /url: /
    - text: TG TrustGate
  - text: AGENT ·
  - navigation "Chính đầu điều hướng":
    - link "Trang chủ":
      - /url: /
    - link "Agent Console":
      - /url: /agent-console
  - button "Đăng xuất"
- main:
  - text: TrustGate · Consent Broker
  - heading "Két dữ liệu và Consent Broker cho AI Agent" [level=1]
  - paragraph: Agent chỉ nhận đúng phần được duyệt, cho đúng mục đích, trong thời hạn nhất định, và quyền này thu hồi được. Mọi thay đổi quản trị đều cần quorum admin duyệt.
  - link "Bắt đầu với Két tài liệu":
    - /url: /vault
  - link "Xem Playbook":
    - /url: /playbooks
  - heading "Bắt đầu từ" [level=2]
  - link "Két tài liệu":
    - /url: /vault
    - img
    - heading "Két tài liệu" [level=3]
    - paragraph: Tải lên, mã hoá và quản lý giấy tờ nhạy cảm trong két.
    - text: Bắt đầu từ đây
  - link "Chọn playbook":
    - /url: /playbooks
    - img
    - heading "Chọn playbook" [level=3]
    - paragraph: Xem lĩnh vực và giao việc cho agent thực hiện.
  - link "Agent Console":
    - /url: /agent-console
    - img
    - heading "Agent Console" [level=3]
    - paragraph: Theo dõi log agent và kiểm tra quy tắc phạm vi.
  - link "Nhật ký hoạt động":
    - /url: /timeline
    - img
    - heading "Nhật ký hoạt động" [level=3]
    - paragraph: Xem chuỗi sự kiện append-only và xác minh hash-chain.
  - link "Demo «Làm admin xấu»":
    - /url: /admin/evil
    - img
    - heading "Demo «Làm admin xấu»" [level=3]
    - paragraph: Kịch bản thử thách bảo mật (chỉ khi bật demo).
  - paragraph: "Prototype hackathon · 2 playbook: Thuê nhà, Khám bệnh · Chế độ mã hoá fallback (WebCrypto)"
```

```
Error: browserContext._wrapApiCall: Target page, context or browser has been closed
```