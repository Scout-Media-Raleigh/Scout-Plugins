# Scout Chat

A chat assistant in the corner of the site. It answers the common questions,
sends people to book a call, takes change requests from existing clients, and
saves every other question, with the visitor's name and email, to an inbox.
Once it is connected to an AI server it answers free-text questions too.

Design: `Scout-Internal/content/design/scout-features/DESIGN.md` 5.1
(panel variant).

## Two modes

| | Collect (default) | AI connected |
|---|---|---|
| Cost, our work, book a call, existing clients | Answered right away | Answered right away |
| Any other question | Saved and emailed; the team replies by email | AI answers; a person is one tap away |
| AI server down or slow | n/a | Falls back to collecting |

Nothing is rebuilt between the modes. Fill in the AI connection and it switches.

## Settings: Scout Build (or Site Build) → Chat

- **Show the chat on the site**: off until ticked.
- **Team names** (`Sean and Joy`): the button reads "Ask Sean & Joy"; replies
  say who answers. Blank: "our team" and "Ask us".
- **Photos**, **greeting card** (and who it's from), **assistant name**,
  **button text**.
- **Email new questions to** (blank uses Send notices to in Business info), **booking page** (blank uses the theme's booking
  page or Contact), **our work page** (blank hides that button).
- **AI server address** and **connection key**. The key can instead be
  defined in `wp-config.php` as `SCOUT_CHAT_AI_KEY`, which wins.

## Outside chat widget: Chat → More options

For a site that runs its own chat service from an outside script. Site
Standard rule 7 keeps that script out of the theme, so Scout Chat loads it.
Off by default; a site that never ticks it sees no change.

| Setting | Default | What it does |
|---|---|---|
| Use an outside chat widget | Off | Prints the outside script in the footer in place of Scout Chat's own button. Show the chat on the site and the `scout_chat_show` filter still turn it on and off. |
| Script address | Blank | The service's script link. `https://` only, a real host, no user name or password. Checked on save and again before it prints; a bad address is not saved and the widget stays off. |
| How it loads | Neither | `async` and `defer`, as the service's snippet shows. |
| Script attributes | Blank | One `name=value` per line. Names become `data-` attributes; `id`, `crossorigin`, `referrerpolicy`, `integrity` and `charset` stay as typed. Nothing else (no `on...` handlers, no second `src`). |
| Settings (JSON) | Blank | A name, like `chatSettings`, and a JSON object. Printed as `window["chatSettings"] = {...}` just before the script, for services that read their settings that way. Must be valid JSON, 4 KB at most. |
| Launcher corner | Bottom right | Which corner the service puts its button in. Set the same corner in the service. |
| Clearance on phones | 86 px | How far the button moves up while Scout Call Buttons' bar shows (86 px clears the bar, the same as Scout Chat's own button). 0 to 400. |
| Launcher selector | Blank | The CSS id or class of the service's button. With it, Scout Chat moves that button up by the clearance while the call bar shows. Blank leaves the button where the service puts it. |

**Where the screen lives (0.4.0).** With an outside widget on, the Chat
screen is an integration setting, so the dashboard files it under **Scout
tools** (operators only) instead of Site features, and Site features ends with
a "Looking for chat settings?" line for operators. Chat messages stay in To do.
The screen keeps its address. Turn the widget off and it moves back.

**From the theme's setup file.** Scout Build 0.18.0+ fills these from
`scout-setup.json`, blanks and untouched defaults only, with the same checks as
the screen: `"chat": { "outside": true, "outside_src": "https://...",
"outside_async": true, "outside_defer": false, "outside_attrs": { "widget-id":
"abc123" }, "outside_config_var": "chatSettings", "outside_config": { "color":
"#0A78C2" }, "outside_position": "left", "outside_clearance": 96,
"outside_selector": "#chat-launcher" }`. `outside_attrs` takes the screen's
`name=value` lines as one string, a list of lines, or an object; `outside_config`
takes a JSON string or an object. `outside` only turns on with a script address
that passes the check.

**The "chat is here" signal.** Scout Call Buttons lifts Scout Chat's own
button on phones with `body.scc-on .sc-root`. That contract stays as it is.
With an outside widget on, Scout Chat also gives the page:

- body classes `scout-chat-on`, `scout-chat-outside`, and `scout-chat-right` or
  `scout-chat-left` (the theme calls `body_class()`);
- `--scout-chat-clearance: <px>` on `:root`;
- `window.scoutChatPresence = { source: "outside", position, clearance }`;
- with a launcher selector, `body.scc-on <selector> { bottom: <clearance> }`
  on screens up to 760 px wide.

**Allowlist.** An outside script runs with full access to the page, so only
load a service the site owner has agreed to, and list it in the site's
privacy policy. The `scout_chat_outside_hosts` filter limits which hosts the
script may come from (a listed host allows its subdomains); empty, the
default, allows any https host:

```php
add_filter( 'scout_chat_outside_hosts', fn() => array( 'widget.example.com' ) );
```

**Content Security Policy.** A site that sends a `Content-Security-Policy`
header must add the service's script host to `script-src`, and usually its
API and frame hosts to `connect-src`, `frame-src` and `img-src` (the service's
docs list them). Scout Chat prints a small inline script (the presence signal
and any settings object) through `wp_print_inline_script_tag()`, so a CSP
plugin that adds nonces to inline scripts covers it; a policy with no nonces
needs `'unsafe-inline'` or a hash for it.

## Messages: Scout Build → Chat messages

Each saved conversation shows who asked, the type (Question, Change request,
Wants a person), whether it's answered, the page it was asked on, and the
whole transcript. "Reply by email" opens a reply; tick Answered when done. The
sidebar shows the unanswered count. A change request that also went to Scout
Build's request list shows "In Requests" and is tracked there, so To do
lists it once.

## For Sean: the AI server contract

The AI provider's key lives on Sean's server only. WordPress never sees it.
The site holds a per-site connection key that the server checks.

**Request** (WordPress → server), `POST <AI server address>`:

```
Authorization: Bearer <connection key>
Content-Type: application/json

{
  "question": "Can you move my site from Squarespace?",
  "history":  [ { "from": "bot", "text": "..." }, { "from": "user", "text": "..." } ],
  "page":     "https://scoutraleigh.com/services/",
  "site":     "https://scoutraleigh.com/"
}
```

**Response** (200):

```
{ "answer": "Plain text, no HTML.", "handoff": false }
```

- `handoff: true` puts "Talk to a person" first under the answer.
- Any non-200, an empty answer, or no reply within 20 seconds makes the chat
  collect the question instead. Answers are trimmed to 2,000 characters.
- Must be `https://`.
- Answer rules the server must follow (from the Scout voice and DESIGN.md):
  no prices, no guarantees, no em dashes, no client names or results, and
  offer a person when unsure. Facts come from the Google Sheet facts source.

## Tracking

Through Scout Analytics: `chat_open`, `chip_click`, `handoff`,
`lead_submit`, plus `chat_book` and `chat_work` on the link buttons.

## Safety

- Both endpoints are public (visitors are not logged in), so each has a
  per-visitor rate limit (5 saves, 20 AI questions per 10 minutes) and the
  save endpoint has a honeypot field.
- Transcripts are trimmed (30 messages, 1,500 characters each) and stored as
  private posts that only editors can see.

## What this feature needs from the theme

- **DESIGN.md:** the panel reads the DESIGN.md roles page, surface,
  surface 2, text, muted text, line, accent, accent fill (link button hover),
  on accent (text on the accent) and the body and label fonts, as `--scout-bg`,
  `--scout-surface`, `--scout-surface-2`, `--scout-text`, `--scout-text-2`,
  `--scout-line`, `--scout-accent`, `--scout-accent-fill`, `--scout-on-accent`,
  `--scout-font-body` and `--scout-font-label` (links use `--scout-accent-text`
  when a DESIGN.md adds `accent_text`, else `--scout-accent`). These are the `--scout-*` variables `tools/design-build.php` writes to the theme's `assets/css/design-tokens.css` from DESIGN.md, so the theme loads that file. The names
  it read before still win when the theme sets them: `--bg`, `--surface`,
  `--surface-2`, `--text`, `--text-2`, `--text-3`, `--line`, `--accent`,
  `--accent-text`, `--font-mono`, `--font-sans`. With neither, Scout's dark
  palette. The green "online" dot and the red error line stay fixed.
- **Where it lands:** a launcher fixed to the bottom right of every public
  page (20px from the edges, `z-index: 9998`), added at the end of `<body>` by
  its script, with the panel opening above it. On phones it moves up to clear
  Scout Call Buttons' bar. The theme calls `wp_footer()` and keeps the
  bottom-right corner clear. With an outside chat widget on, the
  outside script prints in `wp_footer()` instead, and the theme never loads
  it itself (Site Standard rule 7).
- **scout-setup.json:** the `chat` section, filled in by Scout Build where the
  setting is blank: `name`, `team`, `notify`, `book_url`, `work_url`,
  `photo_1`, `photo_2` (photos as a theme path or an https address).
- **Booking link:** `book_url`, else `scout_booking_url()`, else `/contact/`.
- The `scout_chat_show` filter turns it off for a page.

<!-- visitor-data:start -->
## Visitor data

| What | Where it is kept | How long | Shared with |
|---|---|---|---|
| Name, email, phone, the chat conversation (up to the last 30 messages), the page the chat was on, and what kind of message it is (only when the visitor leaves their name and a phone number or email in the chat) | This website's database, as private Chat message posts | Until someone deletes it. Deleting the plugin keeps these posts. The site's privacy tools export and delete a person's messages by the email they gave; a message left with only a phone number is deleted by hand. | Emailed to the address set on the Chat screen, or when that is blank, to Send notices to in Business info (the site admin email by default), plus a short text alert to the alert address if one is set |
| For a change request: the request, with the visitor's name, email and phone, and the page (only when Scout Build is on and the visitor asks for a change in the chat) | This website's database, as a change request in Scout Build's request list | Until someone deletes it | Nobody |
| Questions typed in the chat, the earlier messages in the chat, and the page address (only when an AI server is connected) | Sent from this website to the AI server. This website does not store them unless the visitor later leaves contact details. | Not kept by this website | The AI server set on the Chat screen |
| A scrambled (hashed) copy of the visitor's IP address with a count, used to limit how many messages one visitor can send | A temporary record in this website's database | 10 minutes after the visitor's last chat message | Nobody |
| Chat opens, button taps and sent messages (the button words or message type, never names or messages) (only when Scout Analytics is on) | Passed to Scout Analytics on the page | Not kept by this website | Google Analytics (Google), through Scout Analytics |
| Whatever the outside chat service collects: usually the messages typed into it, the page address, and the visitor's browser and device details (only when Use an outside chat widget is on, in place of this chat) | On the outside chat service's own servers. This website does not store it. | As the outside chat service's own policy says | The outside chat service set on the Chat screen |

| Cookie | Why | How long |
|---|---|---|
| `scoutChat (browser storage)` | Keeps the chat so far (up to 40 messages) so it stays in place when the visitor moves to another page | Until the browser tab is closed |
| `scoutChatTeaser (browser storage)` | Remembers that the visitor opened the chat or closed the greeting, so the greeting does not show again | Until the browser tab is closed |

Nothing is collected while the chat is switched off on the Chat screen. The chat sends no cookies with its messages. With an outside chat widget on, that service may set its own cookies; list them from the service's own docs in the site's cookie notice and privacy policy.

Source: `visitor-data.json`. Edit that file, then run `php tools/visitor-data.php`.
<!-- visitor-data:end -->
