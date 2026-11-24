# Social Review: spec (draft, 2026-11-24)

A private page on each client's own website where the client looks over the social posts we made
for them and approves them, or asks for changes, before they go out. Built for busy owners on a
phone: one link, no password, plain words, two buttons.

Status: draft for Joy and Sean. Nothing built yet.

## Why

- Vista Social's own approval pages are hard for clients to read: they can't tell what they're
  looking at, where it posts, or when (Joy, 2026-11-24).
- Sean wants it client facing, easy to reach, and on the client's own site, with each brand kept
  separate for owners with more than one business (Sean, 2026-11-24).
- Review requests now live in texts and emails, so nobody can see at a glance what is waiting.

## Decisions made (Joy and Sean, 2026-11-24)

| Question | Answer |
|---|---|
| Where posts are made | **Vista Social is the source.** Every post is drafted and scheduled there. Nothing is typed twice. |
| What the client can do | **Approve only.** Approve, or Request changes with a note. Clients never edit captions. |
| How posts reach the site | **Scout's tools sync on a schedule.** No Vista API plan needed. The API is a later upgrade. |
| Plan | **Comes with The Whole System** (catalog tier `whole`). |
| Campaigns | **Posts are approved one by one.** A campaign is a folder, with an "Approve all" button. |
| Pilot | **The first client to pilot it.** One site per brand, so a multi-brand owner gets one page per brand. |

## The plugin

- Slug `scout-social`, name **Social review**. Its own plugin and release, self-updating like the
  others. Requires `scout-build` (for To do, the Site log and the keys).
- Catalog: tier `whole`, summary "See the social posts we made for you, and approve them before
  they go out."
- Dashboard: lives in **To do** (it is waiting on a person). Home shows "3 posts waiting on you"
  when there are any. Operators get a **Social review** screen under Scout tools for settings and
  reviewers.

## What the client sees

Address: `/social-review/` on their own site. Opens from a private link (see Access).

**Top of the page:** "4 posts are waiting on you. Please look them over by Friday, December 5."
A toggle for **List · Calendar · Campaigns** and a filter that starts on **Waiting on you**
(other choices: Approved, Changes asked, Posted).

**Each post is a card that reads like a sentence:**

- "Goes on **Instagram** on **Friday, December 12 at 6:00 pm**."
- The picture or video, shown the way that network shows it (square for an Instagram post, tall
  for a story or reel, wide for Facebook and Google).
- The caption in full, with hashtags and links as they will appear.
- "Please review by **Friday, December 5**." Red when it's late, with the word "Late" beside it.
- The campaign it belongs to.
- Two buttons: **Approve** and **Ask for changes**. Ask for changes opens a box: "What should we
  change?" A note is required.
- After a choice, the card says what happened in words: "You approved this on Dec 3. It will
  post Dec 12." or "You asked for changes on Dec 3. We'll send a new version."

**Views:**

- **List (default):** sorted by review-by date, most urgent first. This is where approving happens.
- **Calendar:** a month grid with each post on its go-live day, a small picture and the network.
  Tapping one opens its card. For spotting gaps and clashes.
- **Campaigns:** one folder per campaign with its dates, a one-line goal ("Fill December
  Saturdays"), and "4 of 6 approved". **Approve all** asks first: "Approve all 4 waiting posts in
  Holiday parties?" Posts with no campaign go in **Everyday posts**, so nothing is ever lost.

**Plain-language rules for the page:** no Vista words (no "workflow", "publication", "queue").
Status is a word plus a shape, never color alone. Built for every screen, fast especially on a
phone. Follows the Dashboard design branch (`tools/admin-kit/DESIGN.md`) for spacing and type,
with the client's own logo at the top.

## Access

- **Private link per reviewer:** `/social-review/?k=<token>`. The first visit swaps the token for
  a cookie, so the address bar stops showing it. No password, no WordPress login.
- Each reviewer has a name and email, set by operators. Every answer records who gave it and when.
- Operators can make a new link (the old one stops working) or remove a reviewer.
- Logged-in owners and staff with the right role see the same page from To do.
- The page is `noindex`, sends `X-Robots-Tag: noindex`, is left out of the sitemap and search,
  and shows nothing at all without a valid link (a plain "This link has expired. Ask Scout for a
  new one." page).
- Images are stored in `uploads/scout-social/` under random names and deleted 30 days after the
  post goes out.

## How posts get there (sync)

Each site is matched to its own Vista profiles (set on the operator screen, never in this repo).

1. **To the site.** A Scout routine reads that site's Vista posts that are in review or scheduled,
   from today forward, and sends them to `scout-social/v1/sync` with the site's key (Bearer, made
   on the operator screen, stored encrypted like the hub keys). Per post: Vista id, network,
   type, caption, media, post date and time zone, review-by date, campaign tag, campaign note.
2. **Back to Vista.** The same routine reads `scout-social/v1/answers` and applies each one in
   Vista: Approve approves the post; Ask for changes rejects it with the client's note as the
   comment and leaves an internal note for whoever made it.
3. **Edits after approval.** If a post changes in Vista after the client approved it, the site
   sees a different caption or media and puts it back to **Waiting on you**, with "We updated this
   post. Please look again."
4. **Timing.** Every 3 hours from 8 am to 8 pm in the site's time zone, plus on demand ("Sync
   now"). The page says "Last updated 2:00 pm" so nobody wonders.

**Campaigns come from Vista tags:** a tag that starts with `Campaign:` is the campaign name
("Campaign: Holiday parties"). The campaign note is a Vista calendar note or idea on the
campaign's first day. **Review-by** comes from a `Review by: 2026-12-05` line in the post's
internal note; if it is missing, it is 5 days before the post date.

## Safe defaults

- **A post nobody answered does not go out.** It stays in review in Vista. Two days before the
  review-by date and on the day, the reviewer gets one reminder email. Once it's late, the post
  shows on Scout Desk as overdue so Joy or Sean decide what to do.
- **Nothing reaches a client until Joy turns it on for that site.** Reviewer emails, reminders and
  links stay off per site until an operator switches them on (CLAUDE.md, "Client contact waits
  for Joy's go").
- Every approval and change request writes a Site log line (kind `social`), so the monthly
  update and the Sheet see it.
- The routine never publishes, deletes or reschedules anything in Vista. It only approves or
  rejects what is already in review.

## Build order

1. **Page with sample posts** (no sync): the three views, the card, the buttons, the access link.
   Joy reviews it with real holiday posts loaded by hand.
2. **Sync endpoints and keys**, then the Scout routine (listed in `Scout-Internal/ops/routines.md`).
3. **Reminders, To do and Home counts, Site log lines.**
4. **Pilot** on one client's staging site, then live.
5. **Later:** Vista API instead of the routine, if the plan is worth it.

## Open

- The pilot site needs a WordPress install (staging is enough) before step 4.
- Reminder emails: from team@, in the client's site branding, or Scout's?
- Does a reviewer ever need to see past months, or only the next 60 days?
