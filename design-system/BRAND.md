# Scout Media: Brand Theme

Scout Media & Consulting builds websites and runs the systems behind them (Fuquay-Varina, NC; veteran-owned). Recon is Scout's reporting product. Tone: warm, direct, plain.

**Canon:** Scout Media Design System v1.3.0 (2026-11-04). The full system (components, UI kits, guideline cards, email templates, social kit) lives in Scout's internal repo under `content/design/scout-design-system/` and in the Scout Media Design System artifact. Tokens here are generated from the shared master.

## 1. Color

| Role | Dark (marketing default) | Light (Recon default) | Notes |
|---|---|---|---|
| Canvas `--bg` | `#0B0B0B` | `#F5F5F7` | Never pure `#000` or `#FFF` as canvas |
| Surface / surface-2 | `#161616` / `#1E1E1E` | `#FFFFFF` / `#EDEDF0` | |
| Text / text-2 / text-3 | `#F5F5F7` / `#B5B5B8` / `#86868B` | `#1D1D1F` / `#515154` / `#86868B` | text-3 is hints only (fails AA as body) |
| Accent (spark) | cerulean `#0E8FE6` | `#0E8FE6` | **Never carries text.** Bolt, links, dots, focus, borders |
| Accent fill | `#0A78C2`, hover `#08639F` | same | Anything with white text on cerulean (4.69:1) |
| Accent as text | `#5FB8F2` | `#0A6FB4` | |

- About one accent moment per marketing view.
- A gradient behind white text starts at `#0A78C2` or deeper.
- Powder tint `#BFE3FA` for pale fills.

**Data colors** (charts and categories only, **never decoration**): cerulean `#0E8FE6`, teal `#12B5A6`, coral `#FF6F61`, amber `#FBB024`, violet `#7B6CF0`. White text never sits on amber or teal.

**Status:** positive `#0E9E73` (light text `#0A7A59`), negative `#F2553D` (light text `#C4321C`), caution `#FBB024`.

## 2. Type

| Role | Face | Use |
|---|---|---|
| Headings + body | Hanken Grotesk 400/500/600 | Headings 600, **never heavier**. Display `-0.04em`, headings `-0.03em`. |
| Mono | Geist Mono 500/600 | Eyebrows (12px, 500, uppercase, 1.4px tracking, `--accent-text`), labels, numerals, "01 / 02" steps |

- No serif, no italics for emphasis. Sentence case; uppercase only for eyebrows, nav and buttons.
- Hero h1 `clamp(40px, 7vw, 72px)`, section h2 `clamp(30px, 4.4vw, 46px)`, body 17px / 1.6.
- Canva substitutes: HK Grotesk for Hanken Grotesk, Space Mono for Geist Mono.

## 3. Shape and layout

Radii: chip 8, control 12, card 18, pill 980px. Container 1160px (wide 1200), gutter 24px, sections 100px. Spacing: 4, 8, 12, 16, 24, 32, 40, 56, 72, 100. Buttons are pills (Hanken 500, 15px, padding 13px 26px). Cards 32px padding, 1px line, 4px hover lift. Focus: 2px accent outline, 2px offset.

## 4. Logo

- Lockup: cerulean bolt plus "Scout" in Hanken Grotesk 600 (live text; there is no vector wordmark file). For print or embroidery, set the word and outline it.
- Bolt shape: `--bolt` clip-path `polygon(58% 0, 0 58%, 42% 58%, 30% 100%, 100% 40%, 54% 40%)`. Use this one shape everywhere.
- Cerulean bolt on dark; ink `#0A6FB4` bolt on white; white bolt on a `#0E8FE6` rounded square for the chip. Clear space at least the bolt's height.
- The bolt is a small accent, bullet or watermark. Never a large hero graphic on the website (merch is the exception, see §6).
- Never: the old yellow bolt, recoloring, rotating, stretching or effects.
- Veteran Owned and LGBTQ Friendly badges are used as supplied, never recolored, never a selling point.

## 5. Voice

- Warm, direct, plain, "we" and "you"; name Sean and Joy.
- **No emoji. No em dashes. No exclamation points. Always contractions.**
- Headlines are full sentences. Plain word first, then the real term ("hidden labels, like schema markup").
- Never write: headcount, "boutique", "mobile-first", "five-star", "own the market", "black box", guarantees, unsourced stats.

## 6. Channels

### Website, WordPress theme and plugins
- Dark default for marketing, light default for Recon, with a toggle.
- Plugins read the bare roles (`--bg`, `--surface`, `--text-2`, `--accent-text`, `--r-card`) with fallbacks. Plugin-private prefixes (`--sr-*`, `--scc-*`) alias to roles; don't hard-code the ladder.
- Review stars use `--caution` (amber), not a hard-coded hex.

### Recon (product UI)
- Light default. Charts use `--data-1..5` in order. Rates, never marketing-attributed dollars; dollars are "tracked through the platform", never "caused by marketing". Never quote a coverage %.

### Email blasts (Mailchimp)
- Table layout, `role="presentation"`, **600px card**, outer bg `#F5F5F7`, card `#FFFFFF`, border `1px solid #ECECEF`, radius 16px, divider `#F0F0F3`, footer `#FAFAFC`.
- **All styles inline.** Head `<style>` holds only resets and `@media (max-width: 620px)` (card 100%, 24px side padding, stack columns).
- Load Hanken Grotesk and Geist Mono with a Google Fonts `<link>`, then fall back to `-apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif`.
- Logo: hosted bolt PNG plus live "Scout" 19px/600. Eyebrow mono 11px/1.5px `#0A6FB4`. H1 30px/600/`-0.03em` `#1D1D1F`. Body 16px/1.62 `#515154`.
- **Buttons and bands use `#0A78C2`** with white text (pill, radius 980px, padding 14px 30px). Never white text on `#0E8FE6`, teal or coral.
- Footer grays must pass 4.5:1 (proposed, pending review): use `#515154` for details and legal, not `#A0A0A6` or `#9A9AA0`.
- Brief: one primary action, three sections, under 400 words, a 12-word headline, every fact sourced. Mailchimp merge tags and `mc:edit` regions as in the templates.

### Holiday cards
- 7 × 5in folded, printed at Moo, handwritten inside. Front 672 × 480px at 96px/in for layout; export at 300 dpi (2100 × 1500px) with 0.125in bleed (final 7.25 × 5.25in). Bleed is a proposed default; confirm against Moo's template.
- Layout: mono eyebrow top left, 50px/600 headline in a 270px column, 15px body, a right-side "UI mock" card (surface, 18px radius), footer rule with bolt + "Scout" + mono sign-off ("From Sean and Joy").
- Grounds: `#0B0B0B` default; `#0A78C2` allowed as a full ground (white text passes).
- **Seasonal colors** (proposed, pending review) (Thanksgiving rust and cream, Valentine's reds, Pride and Veterans Day flag colors) are allowed **on holiday cards only**, as a named seasonal set. They never move to the website, email or Recon.
- One shared card back: scoutraleigh.com, Scout Media & Consulting, Fuquay-Varina, NC, veteran-owned, phone.

### Other print
| Piece | Trim | Notes |
|---|---|---|
| Business card, review card | 3.5 × 2in | Dark front, light back |
| Postcard | 6 × 4in | |
| Launch box lid | 6 × 6in | Inside `#0A78C2` "Ship it." |
| Notebook cover | A5 | |
| Backstage pass | 3 × 4.5in | |
| Encore ticket | 5.5 × 2in | |
| Thank-you card | 7 × 5in | |
All print: 0.125in bleed, 0.125in safe area, 300 dpi.

### Merch
- **Mugs:** standard is the 11oz wrap, 8.5 × 3.5in at 300 dpi (2550 × 1050px) with a safe margin top and bottom; left half prints on the front, right half on the back. 15oz (8.5 × 3.75in) only when ordered as 15oz.
- **Stickers:** Sticker Mule, matte vinyl, bolt in holographic.
- Merch may use the bolt large. In-house-only sayings (like the "Get shit done" mug) never go to clients or public channels.

### Social
- Story 1080 × 1920 (keep the top 250px and bottom 340px clear); feed 1080 × 1350 (headline inside the center 1080 × 1080). UI text at least 28px at 1080 wide. Grounds `#0B0B0B` / `#161616`, one cerulean moment per slide. OG image 1200 × 630.
