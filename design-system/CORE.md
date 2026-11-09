# Design System Core

The core is the shared layer under the brand theme. It defines **what the tokens are called and what they mean**, plus a few values every surface shares. The brand theme (`BRAND.md`, `tokens.css`) fills in the colors, fonts and shapes.

> This copy is generated from a private master. Do not edit `tokens.css` or `tokens.json` here. Ask for the change in the master, rebuild, and copy the new bundle in.

## 1. Files

| File | Use it for |
|---|---|
| `tokens.css` | Websites, WordPress themes and plugins, dashboards. Load once, before other CSS. |
| `tokens.json` | Email, print, Canva, scripts: anywhere CSS variables don't work. Copy the hex values. |
| `CORE.md` | This file: token meanings and shared rules. |
| `BRAND.md` | The brand: colors, type, logo, voice, and rules per channel (web, email, print, merch). |

## 2. Modes

- Set `data-theme="light"` or `data-theme="dark"` on `<html>`. With no attribute, the brand's default mode applies.
- Every color role below has a light and a dark value. Never hard-code a mode's hex in a component; use the role.

## 3. Color roles

| Role | Meaning | Rule |
|---|---|---|
| `--bg` | Page canvas | |
| `--surface` | Cards, panels | |
| `--surface-2` | Insets, subtle fills | |
| `--hover` | Row and control hover fill | |
| `--text` | Primary text | 4.5:1 on `--bg` and `--surface` |
| `--text-2` | Secondary text | 4.5:1 on `--bg` and `--surface` |
| `--text-3` | Hints, placeholders, disabled, icons | **Not for body text.** Large text or icons only (3:1) |
| `--line` | Borders and dividers | |
| `--accent` | Brand spark: links, dots, borders, focus, marks | Never put text on it unless the brand says `--accent-fill` is the same color |
| `--accent-fill` | Any accent surface that carries text (buttons, bands, chips) | `--on-accent` passes 4.5:1 on it |
| `--accent-fill-hover` | Hover and pressed state of `--accent-fill` | |
| `--accent-text` | Accent color used **as text** | 4.5:1 on `--bg` and `--surface` |
| `--on-accent` | Text on `--accent-fill` | |
| `--focus-ring` | Keyboard focus outline | |
| `--glow`, `--nav-bg`, `--shadow-sm`, `--shadow-lg` | Effects | |
| `--positive-text`, `--negative-text` | Status as text | |
| `--data-1` to `--data-5` | Chart and category colors, in this order | **Data only, never decoration** |
| `--positive`, `--negative`, `--caution` | Status fills, deltas, alerts | |

Retired name: `--accent-ink`. It meant "accent as text" in one place and "text on accent" in another. Use `--accent-text` or `--on-accent`.

## 4. Type, shape and motion roles

| Role | Meaning |
|---|---|
| `--font-display`, `--font-body`, `--font-mono` | Font stacks. Mono is for eyebrows, labels, dates and sources. |
| `--fw-body` 400, `--fw-medium` 500, `--fw-heading` | Weights. Never set a heading heavier than `--fw-heading` (display sizes may use `--fw-display` where the brand defines it). |
| `--ls-display`, `--ls-heading`, `--ls-label` | Letter-spacing for big headlines, headings and uppercase labels. |
| `--r-chip` 8px, `--r-control` 12px, `--r-card`, `--r-pill` | Radii. |
| `--space-1` to `--space-6` (4 to 32px, shared), `--space-7` to `--space-10` (brand) | Spacing scale on a 4px grid. |
| `--container`, `--gutter` 24px, `--section-y` | Layout. |
| `--ease`, `--t-fast`, `--t-base`, `--t-slow` | Motion. |

## 5. Shared rules (every surface)

1. **Contrast:** body text 4.5:1, large text and icons 3:1. `tokens.css` is built with these checks; don't override a text role with a lighter color.
2. **Focus:** every interactive element shows `--focus-ring` on `:focus-visible`. Never remove an outline without replacing it.
3. **Touch:** hit targets are at least 44 × 44px. Nothing depends on hover.
4. **Motion:** every animation and transform stops under `prefers-reduced-motion: reduce`.
5. **Color is never the only signal.** Status pairs a color with a word or icon.
6. **No new colors.** If a design needs a color the tokens don't have, add it to the master first. One-off hex values are bugs.
7. **No emoji** in brand surfaces.

## 6. Using it by channel

### Websites and WordPress themes
- Load `tokens.css` first (theme `functions.php` enqueue, or `@import` at the top of the main stylesheet).
- Components read roles: `background: var(--surface); color: var(--text);`.

### WordPress plugins
- Plugins **read** roles and never define them. Keep a private prefix for the plugin's own knobs and point each one at a role with a safe fallback:
  ```css
  .my-plugin {
    --mp-surface: var(--surface, #ffffff);
    --mp-text:    var(--text, #1a1a1a);
    --mp-radius:  var(--r-card, 16px);
  }
  ```
- A plugin that ships on sites without these tokens still renders from its fallbacks.

### Dashboards and product UI
- Use roles for every surface, text and border. Charts use `--data-1`… in order; status uses `--positive` / `--negative` / `--caution` plus a label.
- Each mode must pass the same contrast checks. Test both.

### Email
- Email clients ignore CSS variables. Copy hex values from `tokens.json` into **inline styles**.
- Card width `600px` (`--email-width`), one `@media (max-width: 620px)` block for mobile, table layout, `role="presentation"`.
- Webfonts are optional in email: always list the system fallback stack from `tokens.json`.
- Buttons use `--accent-fill` with `--on-accent` text, never `--accent`.

### Print (cards, postcards, merch)
- No print spec existed before this system. Defaults, until the printer's template says otherwise:
  - **Bleed** 0.125in on every side; **safe area** 0.125in inside trim (0.25in for text near a fold).
  - 300 dpi at final size; export PDF/X or high-res PNG as the printer asks.
  - Colors come from `tokens.json`. Ask the printer for a proof, since screen hex values shift in CMYK.
- Sizes for specific pieces are in `BRAND.md`.

### Canva and slides
- Add the brand colors and fonts to the Brand Kit from `tokens.json` and `BRAND.md`. Use the substitutes listed in `BRAND.md` where the real font isn't available.
