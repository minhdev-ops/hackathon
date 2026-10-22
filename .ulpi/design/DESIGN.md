---
project: TrustGate
register: product
aesthetic_direction: technical / utilitarian
color_strategy: restrained
design_system: bespoke (Tailwind v4 + CSS variables)
design_variance: 2
motion_intensity: 2
visual_density: 3
---

Every screen must read as the same product if placed side by side.

## Design Read

Quiet, monochrome, trust-first tool for handling sensitive documents with AI agents — precision over novelty, every pixel earns its place by communicating permission or state.

## Signature

A single thin vertical rule (1px, #d6d6d2) that splits the consent review into "documents on the left, vault actions on the right" — the visual metaphor for controlled, document-level sharing. Used once per screen, never repeated as decoration.

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
| accent | oklch(20% 0 0) | #1B1B18 | primary buttons, active states | 17.4:1 vs bg |
| accent-hover | oklch(25% 0 0) | #3A3A38 | button hover | 15.2:1 vs bg |
| success | oklch(75% -0.02 165) | #15803D | success notices, pass states | 4.6:1 vs bg |
| warning | oklch(72% 0.015 45) | #B45309 | warning banners | 4.7:1 vs bg |
| danger | oklch(62% 0.02 25) | #B91C1C | error text, delete actions | 4.9:1 vs bg |
| info | oklch(62% 0.01 240) | #2563EB | informational banners | 4.6:1 vs bg |

- Neutrals tinted slightly warm toward the brand near-black (no pure #FFFFFF/#000000).
- 60-30-10: background 60%, surface/elevated 30%, accent/border/text 10%.
- Every text/UI pair passes WCAG AA (4.5:1 body, 3:1 large text + UI).

## Type (locked)

| role | family | use | notes |
|------|--------|-----|-------|
| display | system-ui, -apple-system, Segoe UI, Roboto, sans-serif | headlines, page titles | tracking ≤ −0.02em at large sizes |
| body | system-ui, -apple-system, Segoe UI, Roboto, sans-serif | reading, form labels | measure 60–72ch |
| utility | ui-monospace, SFMono-Regular, Menlo, monospace | doc_type labels, hashes, IDs | 0.875em, letter-spacing 0.02em |

- One family in multiple weights (contrast axis via weight, not face).
- `text-wrap: balance` on headings; `text-wrap: pretty` on body > 2 lines.

## Scales (locked)

- spacing: 0, 2, 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80, 96, 128 (px), 4px base rhythm.
- radius: { sm: 6, md: 8, lg: 12, xl: 16, full: 9999 }. One radius language everywhere.
- shadow: sm (0 1px 2px oklch(0% 0 0 / 0.04)), default (0 4px 6px oklch(0% 0 0 / 0.06)), lg (0 10px 15px oklch(0% 0 0 / 0.08)).
- motion: fast 120ms · base 300ms · emphasis 500ms; cubic-bezier(0.16, 1, 0.3, 1); no bounce/elastic; honor prefers-reduced-motion; exit ≈ 75% of enter.
- breakpoints: sm 640 · md 768 · lg 1024 · xl 1280.

## Voice

- register: plain, confident, Vietnamese (product copy in user's language).
- action vocabulary: Tải lên / Tải về / Xoá / Duyệt / Từ chối / Thu hồi / Mở két — consistent through flows.
- No em-dashes, no buzzwords ("seamless", "next-gen"), no fake names, no fake-precise numbers.
