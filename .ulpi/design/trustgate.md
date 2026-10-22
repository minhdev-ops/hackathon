# Feature Spec: TrustGate UI

## Overview

Product register, technical/utilitarian direction. All screens bind to `.ulpi/design/DESIGN.md`.

Routes:
- `/` — Home (landing + quick actions)
- `/vault` — Két tài liệu (upload, list, download, delete)
- `/playbooks` — Danh sách playbook + nút giao việc
- `/tasks/new` — Tạo task (chọn playbook + chỉ dẫn)
- `/tasks/:id` — Chi tiết task (status, proposal, grant, embedded ConsentReview)
- `/agent-console` — Agent Console (log real-time, nút xin tài liệu ngoài phạm vi)
- `/grants/:grantId` — Consent Review (standalone, existing)

## Component Specs

### PageLayout

Purpose: Shell wrapper for every content page. Provides max-width container, page title, breadcrumb, and "← Trang chủ" link.

Props: { title?: string, subtitle?: string, children }

Variants: none.

States: default only.

Accessibility: `<header>` with `<h1>`, skip-to-content link.

### PrimaryButton / SecondaryButton / DangerButton

Purpose: Action buttons bound to locked palette.

Props: { children, onClick, disabled?, loading?, variant?: 'primary'|'secondary'|'danger', size?: 'sm'|'md' }

- primary: bg-accent text-white, hover accent-hover
- secondary: border border-[#d6d6d2] text-[#1b1b18] hover:bg-[#f3f3f2]
- danger: border border-[#fecaca] text-[#b91c1c] hover:bg-[#fef2f2]

### DocCard (Vault row)

Purpose: Single document row in vault table.

Variants: default.

States: default, hover.

### VaultEmpty / VaultError / VaultLoading

Purpose: Empty / error / loading states for vault list.

### PlaybookCard

Purpose: Playbook tile with slug/name/description + "Giao việc cho agent" button.

States: default, hover, disabled (inactive playbook).

### TaskStatusBadge

Purpose: Render task status with locked color mapping.

Mapping:
- proposing/proposal_ready/awaiting_approval → muted (#706f6c)
- running → info (#2563eb)
- done → success (#15803d)
- failed_revoked/denied → danger (#b91c1c)

### CountdownTimer

Purpose: Grant remaining time countdown (MM:SS or H:MM:SS).

## Flows & States

### Flow: Upload document (Vault)

Entry: `/vault`, click "Tải lên tài liệu".

Steps:
1. Open upload dialog → select file + doc_type + title
2. Client encrypts (CryptoProvider) → POST /documents
3. Success → reload list, show toast "Đã mã hoá và tải lên"
4. Error → inline error message

States: idle, uploading, success, error.

### Flow: Create task

Entry: `/playbooks` → click playbook card → `/tasks/new`.

Steps:
1. Select playbook (pre-filled from link), enter instruction
2. POST /tasks → 201 with task + proposal
3. Redirect `/tasks/:id`

States: idle, submitting, success, error.

### Flow: Task detail → Consent Review

Entry: `/tasks/:id`.

Steps:
1. Fetch task → show status, proposal, agent, playbook
2. If grant exists → render ConsentReview (embedded)
3. If proposing → poll every 1s until proposal_ready or error
4. User unlocks vault → selects documents → chooses TTL → Duyệt/Từ chối

States: proposing, awaiting_approval, running, done, failed_revoked, denied, error.

### Flow: Agent Console

Entry: `/agent-console`.

Steps:
1. Poll agent task logs (mock via audit or task log endpoint)
2. Show "Xin tài liệu ngoài phạm vi" → POST /agent/documents/request → 403 OUT_OF_SCOPE (expected)
3. Show real-time log entries

States: idle, polling, error.

## Edge Cases

| Scenario | Handling |
|----------|----------|
| No documents in vault | VaultEmpty state with CTA |
| Upload fails | Inline error, retry |
| Session expired | 401 → redirect to login |
| Grant expired | Show "Grant đã hết hạn", disable actions |
| Task not found | 404 → back to /playbooks |
| Offline | Network error toast, retry button |

## Build Handoff

- Target agent: Kilo (react-vite-tailwind-engineer equivalent)
- Framework: React 19 + React Router 7 + Tailwind v4 + Vite
- File locations:
  - `resources/js/components/layout/PageLayout.jsx`
  - `resources/js/components/ui/Button.jsx` (primary/secondary/danger)
  - `resources/js/components/ui/StatusBadge.jsx`
  - `resources/js/components/ui/CountdownTimer.jsx`
  - `resources/js/pages/Home.jsx`
  - `resources/js/pages/Vault.jsx` (rewrite per spec)
  - `resources/js/pages/Playbooks.jsx`
  - `resources/js/pages/TaskCreate.jsx`
  - `resources/js/pages/TaskDetail.jsx`
  - `resources/js/pages/AgentConsole.jsx`
  - `resources/js/components/ConsentReview.jsx` (refactor to accept grantId prop)
- Instruction: Implement exactly this spec. Theme with locked tokens from DESIGN.md; do NOT redesign.

## Acceptance Criteria

- [ ] Every page uses ONLY values from DESIGN.md (palette, type, radius, spacing, motion)
- [ ] One accent (#1b1b18), one radius scale, one type pairing across all screens
- [ ] All interactive elements have loading/empty/error states
- [ ] WCAG AA contrast on all text/UI pairs
- [ ] Visible focus states; full keyboard path
- [ ] prefers-reduced-motion honored
- [ ] No banned AI-slop patterns (no 3 equal cards, no purple glow, no em-dashes, no gradient text)
- [ ] All screens read as the same product side by side
