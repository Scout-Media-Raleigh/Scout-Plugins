# Scout Build

The Site Build hub: one dashboard screen showing which Scout features a site
runs, with the controls to turn them on, off, or back a version, a way for the
client to request a change, and a quick health check. Each feature stays its
own plugin with its own release. Spec: `../docs/site-build-hub-spec.md`.

Standalone: does not need Scout Core.

## Where it shows up

One menu, **Scout**, on every site (the Scout Site Standard §7, v1.2:
`Scout-Internal/ops/playbook/site-standard.md`). Inside the site's existing
Build menu when there is one (Scout Core's **Scout** menu, or a theme's own
menu, which the hub relabels Scout until that theme's conversion), otherwise
as its own top-level section with the cerulean bolt. **Site Build** instead
of Scout when `SCOUT_HUB_BRAND` is false.

The same words in the sidebar and on the screen, built around who is looking
(dashboard redesign, Joy 2026-11-04). The full map, and how to file a new
screen or feature, is in `../docs/dashboard-map.md`.

| Item | Who | What it holds |
|---|---|---|
| **Home** | everyone, the first screen | Is the site all right, what needs you, what Scout is doing, the things a client does here, and one line to ask for more. Clients also find **Site history** here (what Scout has done on the site, with Approve). |
| **To do** (key `inbox`) | everyone | One list of everything waiting on a person, newest first, with filter chips: chat questions, new reviews, change requests and their status, site check requests, booking requests. Plugins add items with `scout_hub_inbox_items` (or a count row with `scout_hub_inbox`), each with the capability its screen needs (see Permissions). Each person picks Inbox emails: never, once a day, or every new item. |
| **Reports** | owner, operator | Reserved and empty. Recon's numbers are never shown in WordPress (Joy, 2026-11-05). Empty groups never show to clients. |
| **Business** | everyone | Facts typed once: Business info, Hours, Team, Case results, a companion plugin's own types. |
| **Site features** | owner, operator | What visitors see and use: Call buttons, Chat, Reviews and Review settings, Cards & links, Newsletter, Redirects, Free site check. |
| **Search & analytics** (key `search`) | owner, operator | SEO, AI search, Analytics and the cookie notice, Search engine rules (robots.txt). |
| **Legal pages** | owner, operator | Accessibility statement now; privacy policy, terms and law-firm disclaimers as they arrive. |
| **Site status** | owner, operator | The whole report (the Site status screen), Search health, Spam protection, the image optimizer. |
| **Scout tools** | operators only | Features, Updates, Site history, Email, Chat, Customer list, Site Sync (Page copy on Updates), the Change log, a theme's own screen. Reads **Build tools** when `SCOUT_HUB_BRAND` is false. Every action on it still checks for an operator. |

Plus modules (Bookings, Events, Members, Partners, Writing room) keep their own
sidebar sections and only appear on sites that have them.

The shared admin kit (`tools/admin-kit/`) files every Scout screen into these
groups by its slug, and `tools/tests/admin-kit-groups.php` fails when a screen
drifts from the map. A screen never moves its address; only where it is filed.

## Permissions: who sees what in To do (0.18.0+)

Every To do item carries the capability its own screen needs, and the To do list, the count on
the sidebar bubble and the All chip, Today on Home and the To do emails leave out what the reader
cannot open. A staff login never sees a booking request's name, email or note.

| Item | Capability |
|---|---|
| Change requests, chat questions, new reviews, site check requests | `edit_posts` (the To do screen's own, as before) |
| Booking requests | the Bookings screen's own (`Scout_Booking_Admin::CAP`, `list_users`) |
| A plugin's item or count row without `cap` | `edit_posts`, as before |

A plugin passes the capability with the item:

```php
add_filter( 'scout_hub_inbox_items', function ( $items ) {
	$items[] = array(
		'id'    => 'myplugin-12',
		'kind'  => 'other',
		'label' => 'New application',
		'from'  => 'A name',
		'url'   => admin_url( 'admin.php?page=myplugin' ),
		'cap'   => 'list_users', // The capability the item's own screen needs.
	);
	return $items;
} );
```

Count rows from `scout_hub_inbox` take the same `cap` key. Items on Today (`scout_hub_today`)
already had `cap` and still default to `edit_posts`. The email digest switches to each recipient
before it builds the list, so each email only lists what that person can open.

## Settings in `wp-config.php`

| Constant | Default | What it does |
|---|---|---|
| `SCOUT_HUB_OPERATORS` | `sean@scoutraleigh.com, joy@scoutraleigh.com` | Who can turn features on and off, go back a version, set the plan, and change request statuses. Must also be a WordPress admin. |
| `SCOUT_HUB_REQUEST_EMAIL` | `team@scoutraleigh.com` | Where change requests and the "hidden from Google" alert are emailed (the alert can also be changed with the `scout_build_alert_email` filter). **Set this on partner-side sites** so nothing routes to a Scout address. |
| `SCOUT_HUB_BRAND` | `true` | Labels the menu **Scout** with the bolt and shows the "Built and looked after by Scout Media" sign-off. **Set to `false` on partner-side or white-label sites**: the menu then says Site Build and nothing mentions Scout. |
| `SCOUT_HUB_READ_TOKEN` | (none) | Optional. A key of 20+ characters that lets the Scout Client Hub read `scout-build/v1/log`, `/requests` and `/site` (Bearer). Normally operators make the keys on Scout → Site history instead; a constant here wins over those. |
| `SCOUT_HUB_WRITE_TOKEN` | (none) | Same, plus `POST /log` for note lines (how Scout's tools log work as it ships). Cannot change anything else. |
| `SCOUT_BRAND_OFF` | `false` | Turns the admin white-label layer off even when the setup file has a `brand` section. |
| `SCOUT_HUB_LOG_API` | `false` | On a white-label site (`SCOUT_HUB_BRAND` false) the endpoint stays off unless this is true. |
| `SCOUT_THEME_TOKEN` | (none) | The read-only GitHub key for the theme's own repo, instead of saving it on Scout → Updates. See "Theme updates" below. |
| `DISALLOW_FILE_MODS` | (WordPress) | When true, features that are not installed must be uploaded by hand; on and off still work. |

## Release rings (update order) and the pause switch (0.11.1+)

Site Standard rule 19: a Scout plugin release installs itself on
scoutraleigh.com and staging first (ring 1), Recon a day later (ring 2),
client sites two days after it is out (ring 3). Screens call this the
**update order** (first, a day later, two days later). Until then the automatic
update waits and Build, Updates says when it installs; Update now always
works. Set the ring with `SCOUT_HUB_RING` (1, 2 or 3) in wp-config.php or on
the Updates screen; otherwise the address decides. Two brakes:

- **One site:** Build, Updates, Pause automatic updates for 7 days.
- **Every site:** add `{"slug": "...", "version": "..." or "*", "note": "..."}`
  to `hold` in `catalog.json` (both copies). Sites read it within 12 hours,
  or at once with Check for new versions. Remove the line to release it.

## Theme updates

A client theme can update itself like the Scout plugins, from the client's
own private repo. The theme adds `Update URI: https://github.com/OWNER/REPO`
and `Scout Release: <folder name>` to its `style.css` header, and its repo
publishes each version with the workflow in `tools/theme-release/`. On the
site, an operator pastes a read-only key (a fine-grained GitHub token with
Contents read access to that one repo) into the **Theme** box on Scout →
Updates. The key is stored encrypted and sent to api.github.com only; the zip
itself is fetched from GitHub's signed address without it.

The Theme box then shows the installed version, "Up to date" or the version
ready, **Update the theme now**, and the theme auto-updates within a few hours
of each release. **Check for new versions** checks the theme too. A theme
without `Update URI` keeps updating by upload. Steps for each client:
`tools/theme-release/README.md`.

## The Site log (Site history on screen)

**Scout, Site history** keeps one line for every change to the site, written by
the plugin that made it: Scout plugin and theme updates (with the changelog
headline), features turned on, off or sent back, Site Sync runs, the setup
file, change requests coming in and moving, and the hidden-from-Google alert.
Operators add notes for work done by hand. Each line has a kind, a topic, a
status (done, planned, waiting on you), a version, a measure and a "client
sees" flag with its own plain wording, which is what the client's monthly
update is built from. A done line never changes; corrections are a new line.

**The client's view (0.11.0):** everyone who is not an operator sees the log as Scout → Site history: client
lines only, in client wording, with Waiting on you (Approve button), This month, Your requests and
Coming up. To put something up for the client's OK, add a note with status "Waiting on you" and
Client sees on.

**Hub keys:** operators press Make keys on the Site history screen; the read key goes in the Scout
Client Hub Sheet, the write key is for Scout's tools. Shown once, stored encrypted.

One-time import: upload the site's history file, which Scout keeps in its
private ops folder, on the Site history screen. Spec and the hub it feeds: `../docs/site-log-spec.md`.

## The monthly update to the client

Off until an operator turns it on, in the **Monthly update to the client** box
on Scout tools, Site history (operators only). Monthly, or quarterly for The
Partnership. Early in the month the site drafts last month's update from the
lines marked Client sees (client wording, else the title, plus the measure),
then **Waiting on your OK** and **Coming up**. Nothing sends by itself: an
operator presses **Send** or **Skip**. The team address
(`SCOUT_HUB_REQUEST_EMAIL`) hears when a draft is ready, and once more after
three days. For text-first clients, **Text, sent by hand** shows a short text to
copy and an **I texted it** button. **Make a draft** builds one for any of the
last six months. Sending writes a Site history line.

The email goes to the address typed in the box's Settings, else Business info's
email (Scout Core), in the site's branded card through Scout Email, with replies
to the team address. On white-label sites (`SCOUT_HUB_BRAND` false) it never
names Scout, and the draft warns about any line that does.

## White-label for the WordPress admin (Scout Brand)

Replaces Branda Pro. **Off on every site until the site turns it on**, so a
client's admin looks the same as before:

- the theme's `scout-setup.json` has a `brand` section (even `"brand": {}`), or
- the `scout_brand` option is saved with `"on": true` (sites without a setup file).

`"on": false` in the section, or `define( 'SCOUT_BRAND_OFF', true );` in
wp-config.php, turns it off. Operators see whether it is on, and from where, on
Scout tools, Features.

```json
"brand": {
  "logo":       "assets/img/logo.svg",
  "logo_width": 220,
  "color":      "#1A2E5A",
  "background": "#F6F8FB",
  "text":       "#1B2150",
  "message":    "Need a change? Use Request a change in the top bar.",
  "hide_menus": [ "tools.php", "options-general.php>options-privacy.php" ],
  "off":        [ "admin_bar" ]
}
```

| Part (`off` name) | What it does |
|---|---|
| Login screen (`login`) | Logo from `brand.logo`, else the Customizer logo, else the Site Icon; brand color on the button; the logo links to the site; the tab title is the site name. |
| Admin bar (`admin_bar`) | No WordPress logo menu, "Hi, Name", and the updates counter only for operators. |
| Footer (`footer`) | "Built and looked after by Scout Media." with Request a change; "your web team" on white-label sites. The WordPress version only for operators. |
| Hidden menus (`menu`) | Menus in `hide_menus` hidden from everyone but operators. `parent.php>child.php` hides one submenu item. |
| Dashboard message (`message`) | `message` at the top of the Dashboard until each person dismisses it. |

To switch a site from Branda: add the section, update the theme, check the
login screen, admin bar and footer, then deactivate Branda. Branda's settings
are not imported.

## Site status: the Plugins list (0.16.0+)

Site status files four rows under **Plugins**. Each also goes into the copied
report, the update report's site status text, and the Hub's `/site` read.

| Row | When it shows | What it says |
|---|---|---|
| Updates waiting | always | How many plugins have an update ready. |
| Test builds | always | Any Scout plugin on a test build or a version with no release. |
| Outside plugins | always | Every active plugin that is not a Scout plugin and not Gravity Forms (Site Standard rules 8 and 34). One on the site's approved list reads "approved"; any other reads "needs a decision" and turns the row yellow. None at all reads "None". |
| WPMU DEV | only when one is on | Red. WPMU DEV was retired from every Scout site on 2026-11-02, so any active WPMU DEV plugin (Dashboard, Defender, Smush, Hummingbird, Branda, SmartCrawl, Forminator, Hustle, Beehive, Snapshot, Shipper, or any plugin by WPMU DEV or Incsub) is a fix. The approved list never clears it. |

How a plugin is sorted (`includes/class-scout-hub-plugins.php`):

- **Scout:** its `Update URI` is this repo, or its author is Scout Media, or
  its author or plugin address is on scoutraleigh.com (client companion plugins).
- **Gravity Forms:** the `gravityforms` folder, or any plugin by Gravity Forms
  (gravityforms.com or rocketgenius.com), so its official add-ons count too.
- **WPMU DEV:** a known WPMU DEV folder name, or an author or address of WPMU DEV
  (wpmudev.com) or Incsub.
- **Outside:** everything else.

Only active plugins count. Must-use plugins and drop-ins (the host's own) are
not in WordPress's plugin list and are not checked. The rows are on admin
screens only; nothing here reaches visitors.

**The approved list.** Rule 34 keeps the decision in the site's
`docs/decisions.md` (the job, the owner and the version watch). Build cannot
read that file, so the theme's `scout-setup.json` carries the same list as
`approved_plugins`, and it arrives with each theme release.

## Site status: the nightly form test (0.17.0+)

Site Standard rule 31: a form with no received test submission is a blocker.
The nightly form test sends each picked Gravity Forms form once a night and
checks that its emails go out. Code: `includes/class-scout-hub-form-test.php`.

**Off until switched on.** Nothing happens after an update. An operator opens
**Scout tools, Features, More options: nightly form test**, ticks **Run the
test every night**, ticks the forms, and saves. **Save and run it now** runs
it at once (for a ring 1 check). The theme's `scout-setup.json` can suggest
forms, but it can never switch the test on:

```json
"form_test": { "forms": [ 3, "Contact" ] }
```

Forms are named by id or exact title. With no saved list and no `form_test`
section, the box pre-ticks the forms placed on a published page or post (by
shortcode or block) that send email. Nothing runs until an operator saves.

**What one test does.** Around 2 am in the site's time zone (each site gets
its own minute between 2:00 and 3:59, so they don't all run at once), each
ticked form gets one submission through `GFAPI::submit_form`:

- Every required field gets a made-up answer ("Nightly form test", a 555
  phone number, the first choice). Every email field gets the notice address
  (`scout_core_notice_email()`), so an auto-reply meant for "the visitor"
  goes to the business, never to a stranger. Boxes that are not required stay
  unticked.
- The form's own notifications go to their real addresses, with `[Test]` at
  the start of the subject, a first line that says it is a test, and an
  `X-Form-Test: nightly` header for mail filters.
- No add-on feed runs (Mailchimp, a CRM, Zapier, webhooks): feeds are emptied
  for the test with `gform_addon_pre_process_feeds`. Scout Newsletter's form
  listener is paused. Other plugins can check `scout_build_form_test_running()`.
  An outside plugin that acts on every submission without a feed is not
  stopped, so check the site's outside plugins before switching it on.
- The entry is deleted right after the check. Its id is saved before the
  emails go out, and the entry carries a `scout_form_test` mark, so a run that
  stops halfway is cleaned up the next night. Only marked entries are deleted.
- It never counts in analytics: it runs on the server, so no page loads and
  no browser event fires, and the deleted entry leaves Gravity Forms' own
  counts. It never counts in the Form emails sent row either.
- Scout Forms Guard is not loosened. The test carries the same signed token a
  real page gives a visitor, made with the site's own secret inside the run.
  `tools/tests/form-test.php` checks the token still matches the guard.
- It is skipped on staging copies (the staging email guard would drop the
  client's emails) and on copies that are not the live site.
- Forms it can't send by itself are skipped with the reason: payments, a
  CAPTCHA, a required file upload or signature, a login, a form that makes a
  post, a password field, an input mask, or a required field type it doesn't
  know. Those need a test by hand.

**How sending is confirmed.** Gravity Forms reports each notification's
result, which is WordPress's mailer answer: the mailer, or the mail server it
signs in to, took the email. The site can't see anyone's inbox, so the row
says the emails were "handed to the mailer", never "delivered". Check that a
`[Test]` email arrives now and then. The test's notifications are kept in the same
request (`gform_is_asynchronous_notifications_enabled` returns false for it),
so a site that sends notifications in the background still gets a reading.

**The Site status row** (Basics, under Form emails sent; only while the test
is on):

| Value | State | When |
|---|---|---|
| Sent last night | green | Every picked form sent all its emails. |
| Trying again tonight | green | A form failed once. One miss can be a blip. |
| Failed: Contact did not send | red | A form failed two nights in a row. The notice address gets one email, then one a week while it keeps failing. Notice emails go out on failure only. |
| Could not test Contact | yellow | A form was skipped or refused (a spam filter, or Gravity Forms turned down the answers). The reason is in the note. |
| No test since Nov 9 | yellow | No run in 36 hours: WordPress's scheduled tasks may be off on the host. |
| No forms picked | yellow | On, with nothing ticked. |
| First test tonight | green | Just switched on. |
| Off on staging | green | A staging copy. |

**What is kept** (options, nothing about visitors): `scout_hub_form_test`
(the switch, the ticked form ids, who saved it and when) and
`scout_hub_form_test_state` (each form's last result, how many emails went,
the error with every address taken out, the failures in a row, and test
entry ids waiting to be deleted). Uninstall deletes both.

## The feature list

`catalog.json` at the root of this repo, mirrored in `scout-build/catalog.json`
(CI fails if the two differ). Each entry: `slug`, `name`, `summary` (one plain
sentence), `tier` (`build`, `partnership`, `whole` or `addon`), `law`,
`requires` (other slugs), and optional `client_specific` (shown only where
installed). Adding a feature to the list needs no hub release: sites pick it up
within 12 hours, or at once with **Check for new versions**.

## Installing from releases

Turn on and Go back download the `<slug>.zip` asset from this repo's GitHub
releases (`<slug>-v<version>` tags), the same releases the updater uses. The
repo's releases must stay public for this to work without a login.

## What this feature needs from the theme

Scout Build has no visitor-facing output. It reads these from the theme:

- **`scout-setup.json`** (child theme first): `features.use` and
  `features.skip`, `scout_own_site`, `approved_plugins` (the outside plugins
  the site has said yes to, read by Site status; see above), `form_test.forms`
  (forms the nightly form test suggests; it never switches the test on), and the sections it fills into other
  plugins (`business`, `team`, `reviews`, `chat`, `hours`, `analytics`,
  `cards`). Most fill blanks only. In `reviews`, `totals` (each platform's
  rating, count and link, with the `checked` date) replace the saved totals
  when that date is newer than the last check, and `items` (single reviews)
  are only ever added, never edited or removed. `google_place_id` fills
  the place ID for Scout Reviews' daily Google check when it is blank.
  Details: Scout Reviews' README. The file carries facts only (Site Standard
  §5): Build no longer copies look keys (`reviews`: `background`,
  `text_color`, `accent`, `heading_font`, `body_font`, `label_font`;
  `cards`: `mode`, `accent`, `font`). The look comes from the theme's
  DESIGN.md, which each plugin reads itself. Values already saved on a site
  stay, and Fill from theme names any look keys a theme still carries. The `brand` section dresses the login screen and the admin: `logo`,
  `logo_width`, `color`, `background`, `text`, `message`, `hide_menus`, `off`.
  Format: `includes/class-scout-hub-setup.php`. `approved_plugins` takes
  plugin folder names or objects, one per dated entry in `docs/decisions.md`:

  ```json
  "approved_plugins": [
    { "plugin": "wp-rocket", "job": "Page caching", "owner": "Scout", "decided": "2026-11-05" },
    "another-plugin-folder"
  ]
  ```
- **DESIGN.md** through `scout_design()`: `colors.accent_fill` as the
  detected color on the Email screen, and the pairs `scout_design_issues()`
  checks (`on_accent` on `accent_fill`, `text` on `bg` and `surface`, `text_2`
  on `bg`), shown as Design contrast in Site status.
- **`style.css`:** `Update URI` and `Scout Release` for theme updates (Theme
  updates, above), and the theme's `CHANGELOG.md` line for the Site log.
- **`add_theme_support( '<feature slug>' )`** lets a theme named in a
  feature's `not_with_themes` offer that feature anyway.

<!-- visitor-data:start -->
## Visitor data

| What | Where it is kept | How long | Shared with |
|---|---|---|---|
| Change requests visitors send through Scout Chat: the request in their words, their name, email and phone, and the page (only when Scout Chat is on and a visitor asks for a change there) | This website's database, as private change request posts (the Requests screen) | Until someone deletes it. Deleting the plugin keeps the request list. The site's privacy tools export a request by the email the visitor gave; the eraser tool keeps the request and takes out their name, email and phone. | Scout's Client Hub reads the request and the visitor's name through the site's Hub key, with email addresses and phone numbers blanked first. Only when a Hub key is set. |
| The visitor's name and the first words of a chat change request, as a line in the Site log (only when Scout Chat is on and a visitor asks for a change there) | This website's database, as a private Site log line | Until someone deletes it. Deleting the plugin keeps the Site log. The site's privacy eraser tool replaces the name with Scout Chat. | Scout's Client Hub, which reads the Site log through the site's Hub key, only when a Hub key is set |
| Names or emails and short previews of waiting visitor messages (chat questions, booking requests, new reviews and similar) (only for staff who chose daily or every-item Inbox emails) | Read from the plugins that hold them and listed in Inbox emails. This plugin keeps no copy. | Not kept by this plugin | Emailed to site staff who turned on Inbox emails |
| Every email the website sends, including form notices, booking and chat emails, and replies to visitors, which can carry what visitors typed (only when the Email screen has a Google address and app password) | Sent through the Google mailbox set on the Email screen. This plugin keeps no copy. | Not kept by this plugin | Google (Gmail), which sends the site's email |

This plugin sets no cookies and collects nothing from visitors itself. The items above come from other Scout plugins, mainly Scout Chat, and from how the site's email is sent. Site status counts whether form emails went out, keeping only which form and which email, sent or failed, and the time, never who it went to or what it said. The monthly update to the client (off until an operator turns it on) emails the site's owner, not visitors: it goes to the address an operator types in its Settings, kept with the first name for the greeting in the option scout_hub_report_settings, or else to Business info's email.

Source: `visitor-data.json`. Edit that file, then run `php tools/visitor-data.php`.
<!-- visitor-data:end -->
