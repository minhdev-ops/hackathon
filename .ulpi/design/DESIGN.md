---
project: TrustGate
register: product
aesthetic_direction: technical / utilitarian
color_strategy: committed
design_system: bespoke (Tailwind v4 + CSS variables)
design_variance: 3
motion_intensity: 3
visual_density: 3
---

Every screen must read as the same product if placed side by side.

## Design Read

Công cụ quản lý consent và két dữ liệu cho AI agent — tin cậy, chuyên nghiệp, có một màu accent đậm nổi bật để hướng hành động, không bao giờ làm người dùng bối rối về bước tiếp theo.

## Signature

Một thanh accent dọc (4px) bên trái mỗi hành động chính + nút chính dùng màu accent đậm (#0f6f9e). Signature xuất hiện chỉ ở nút gọi hành động và ranh giới phân vùng, không được dùng trải nền.

## Color (locked)

| role | OKLCH | hex | use | contrast |
|------|-------|-----|-----|----------|
| background | oklch(99.2% 0 0) | #FDFDFC | page body | — |
| surface | oklch(98.4% 0 0) | #FFFFFF | cards, panels, inputs | 1.03:1 vs bg |
| elevated | oklch(96.8% 0 0) | #F3F3F2 | hover states, section headers | 1.06:1 vs bg |
| border | oklch(89.5% 0 0) | #D6D6D2 | dividers, input borders | 2.1:1 vs bg |
| text | oklch(11.2% 0 0) | #1B1B18 | primary text, headings | 17.4:1 vs bg |
| muted | oklch(46% 0 0) | #706F6C | captions, secondary text | 7.3:1 vs bg |
| subtle | oklch(84% 0 0) | #B9B8B5 | disabled text, placeholders | 3.1:1 vs bg |
| accent | oklch(55% 0.16 220) | #0F6F9E | primary buttons, active states, signature rule | 5.2:1 vs bg |
| accent-hover | oklch(50% 0.18 220) | #0C5F8A | button hover | 5.8:1 vs bg |
| accent-inverse | oklch(100% 0 0) | #FFFFFF | text on accent button | — |
| success | oklch(75% -0.02 165) | #15803D | success notices, pass states | 4.6:1 vs bg |
| warning | oklch(72% 0.015 45) | #B45309 | warning banners | 4.7:1 vs bg |
| danger | oklch(62% 0.02 25) | #B91C1C | error text, delete actions | 4.9:1 vs bg |
| info | oklch(62% 0.01 240) | #2563EB | informational banners | 4.6:1 vs bg |

- Neutrals tinted nhẹ về 따뜻 (không pure #000/#FFF).
- 60-30-10: background 60%, surface/elevated 30%, accent/border/text 10%.
- Every text/UI pair passes WCAG AA.

## Type (locked)

| role | family | use | notes |
|------|--------|-----|-------|
| display | system-ui, -apple-system, Segoe UI, Roboto, sans-serif | headlines | tracking ≤ −0.02em |
| body | system-ui, -apple-system, Segoe UI, Roboto, sans-serif | reading, labels | measure 60–72ch |
| utility | ui-monospace, SFMono-Regular, Menlo, monospace | doc_type, hashes | 0.875em |

- `text-wrap: balance` headings; `text-wrap: pretty` body > 2 lines.

## Scales (locked)

- spacing: 0, 2, 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80, 96, 128 (4px base).
- radius: { sm: 6, md: 8, lg: 12, xl: 16, full: 9999 }.
- shadow: sm (0 1px 2px / 0.04), default (0 4px 6px / 0.06), lg (0 10px 15px / 0.08).
- motion: fast 120ms · base 300ms · emphasis 500ms; cubic-bezier(0.16, 1, 0.3, 1); no bounce/elastic; honor prefers-reduced-motion.
- breakpoints: sm 640 · md 768 · lg 1024 · xl 1280.

## Voice

- register: plain, confident, tiếng Việt.
- action vocabulary: Tải lên / Tải về / Xoá / Duyệt / Từ chối / Thu hồi / Mở két — nhất quán qua các flow.
- Không em-dash, không buzzword, không tên giả, không số chính xác ảo.
