# Scout Media Design System

Version 1.0.0 (2026-11-09). **Do not edit these files here.** Generated from Scout's private design master. To change a value, ask Joy or Sean; the master rebuilds and re-syncs this folder.

| File | What it is |
|---|---|
| `tokens.css` | All tokens as CSS variables. Load it before any other CSS. |
| `tokens.json` | The same values for email, print, Canva and scripts. |
| `CORE.md` | What each token means, and shared rules for web, plugins, dashboards, email and print. |
| `BRAND.md` | Colors, type, logo, voice and per-channel rules for Scout Media. |

Quick start:

```html
<link rel="stylesheet" href="design-system/tokens.css">
<html data-theme="light"> <!-- or "dark"; omit for the brand default -->
```

```css
.card { background: var(--surface); color: var(--text); border: 1px solid var(--line); border-radius: var(--r-card); }
.button { background: var(--accent-fill); color: var(--on-accent); border-radius: var(--r-pill); }
```
