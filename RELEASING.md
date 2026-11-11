# Releasing a plugin

Client sites update themselves from this repo's GitHub releases. Shipping a fix
to every site is three steps: bump, tag, push.

---

## How the updating works

Each plugin carries an `Update URI:` header pointing at this repo. WordPress
6.5+ (the header landed in 5.8) sees that header, knows the plugin does not come
from wordpress.org, and hands the update check to the plugin itself. The updater
in `includes/class-scout-plugin-updater.php` answers by looking up this repo's
newest release for that plugin.

The update then appears on the client's **Plugins** screen like any other, and
**installs itself automatically** on WordPress's own twice-daily check: the
updater opts Scout plugins into core's automatic updates, so publishing a
release here is the whole job. A site opts out by defining
`SCOUT_DISABLE_AUTO_UPDATES` as `true` in `wp-config.php`. Nothing extra is
installed on the client site and no third-party updater service is involved.

Sites check for updates roughly twice a day, and the lookup is cached for six
hours, so a release reaches a site within a few hours rather than instantly.

---

## Before releasing Scout Core: check what depends on it

Other Scout plugins call into Scout Core (Scout Cards reads the business identity,
adds its screen under the Scout menu, and switches off Scout Core's head tags on
card pages). A Scout Core release reaches every client site automatically, so a
rename there can break a dependent plugin on every site at once.

- **Themes have their own gate.** `tools/theme-standard-check.sh <theme-dir>` (the Scout Site
  Standard: required files, nothing a theme never holds) and `php tools/design-build.php
  <theme-dir>` (theme.json tokens and `design-tokens.css` from DESIGN.md, with the contrast
  check) run in every client repo's `theme-release.yml`. See `tools/theme-release/README.md`.
- **CI guards it.** `tools/core-contract.sh` (Lint workflow, "Scout Core contract")
  fails when a Scout Core API a plugin depends on disappears. Fix the dependent
  plugin in the same change, release both, and update the contract list.
- **When a plugin starts using a new Scout Core API,** add a line to the contract in
  `tools/core-contract.sh` in that same change.
- **Release order when both change:** Scout Core first, then the dependent plugin,
  so no site runs the new plugin against an old core.
- **Smoke-test one site** (the first client site on the channel) after a Scout Core
  release: open a card page and the Scout menu before calling the release done.

## Shipping a release

**1. Bump the version.** In the plugin's main file, update the `Version:` header
and the matching `SCOUT_*_VERSION` constant where the plugin defines one. This
header is the source of truth for the release. Update the plugin's row in the
root `README.md` table too: the Lint workflow fails when a row differs from
the header.

**2. Write the changelog.** Add a dated entry at the top of that plugin's
`CHANGELOG.md`. The release notes are pulled from this entry automatically.

**3. Commit, push, and merge the pull request into `main`.** That is the
release. On every push to `main` the Release workflow reads each plugin's
`Version:` header, and any version without a release yet gets one: it creates
the `<slug>-v<version>` tag, builds the zip, and publishes the release. Scout
Core goes first, then the rest, one at a time. A plugin whose version did not
change is skipped, so merging never ships anything by accident. The version can
never disagree with the code because nobody retypes it.

**4. Update the fleet row.** The fleet matrix (every site, what it runs, how it
updates) is private: it lives in the Scout-Internal repo, `ops/fleet.md`, not
here (`fleet.md` in this repo is only a pointer). Once the release is out, ask a
Scout session to update it: the plugin's new version in the Versions table, and
each site's row as the sites pick the release up. Site names, install names and
versions per site never go in this public repo.

### By hand, as a backup

> **Actions → Release → Run workflow →** leave **Use workflow from** on `main` → pick the plugin → **Run**

Same build, for one plugin. It refuses a version that already shipped, and it
refuses to run from any branch but `main`, so a working branch never ships.

Each plugin is versioned and released on its own. Releasing one never touches
the others.

### Or from the command line

If you would rather tag by hand, that still works:

```bash
git tag scout-core-v1.0.2
git push origin scout-core-v1.0.2
```

Here the tag has to match the plugin's `Version:` header and sit on a commit
that is already on `main` (merge first, then tag). The build fails on purpose
if either is not true.

---

## Test builds for staging

A zip built from an unmerged branch with a normal version number causes two
problems. If its number is already released, or lower than the next release,
that release overwrites it and the branch's unreleased work disappears. If its
number is higher than any release, it never updates at all. Both happened on
a client site in October 2026 (Scout Build 0.6.7 and Forms Guard 0.3.0 test zips).

Build staging zips with the script instead:

```bash
bash tools/test-build.sh scout-build      # scout-build-0.7.8-test.1.zip
bash tools/test-build.sh scout-build 2    # pick the test number yourself
```

The zip's `Version:` header (and the `SCOUT_*_VERSION` constant) reads
`<version>-test.<n>`. Only the copy in the zip changes; never commit a `-test`
version. PHP's `version_compare()` sorts `0.7.8-test.1` below `0.7.8`, so the
real release of that version replaces the test build on the next update check.
Until then Scout Build's **Site health** lists it under **Test builds**, along
with any Scout plugin running a version newer than its newest release.

Install test zips on staging only.

---

## Staging first, then live (the approval hold)

A release installs by itself on every staging copy. A live site waits for
Scout's OK (Scout Build 0.12.0, Joy 2026-11-04):

1. The release lands on staging. Scout, and the client where it changes what
   they see, check it there.
2. On the live site: **Scout tools → Updates → Waiting for your OK**. Type who
   said yes ("Client OK by text, Nov 5"), then **Approve** or **Approve all**.
   The Site log records it, and the client sees it under Your site.
3. The live site installs it on its next update run, or at once with Update now.

This covers Scout plugins and any theme that updates from GitHub. Update now
always works. A site can switch the hold off on the same screen;
`define( 'SCOUT_HUB_APPROVAL', false )` in wp-config.php does the same.

**Security fixes skip the wait.** Add the release to `"security"` in
`catalog.json` (and its copy in `scout-build/catalog.json`):

```json
"security": [ { "slug": "scout-forms-guard", "version": "0.3.5", "note": "Upload fix" } ]
```

Any site still below that version installs it (or anything newer) as soon as
it is out, with no OK and no ring wait. Once a site has the fix, later releases
wait for an OK as usual. A theme is listed by its folder name. A `"hold"`
entry still stops it, and so does a pause on one site.

---

## Checking it worked

The release shows up at
https://github.com/Scout-Media-Raleigh/Scout-Plugins/releases with a `<slug>.zip` asset
attached. That asset is what client sites download, so a release without it
offers nothing and sites stay on their current version.

To see the update on a site without waiting for the cache, visit
**Dashboard → Updates** and click **Check again**.

---

## Things that will bite you

**The repo has to stay public.** The updater calls the GitHub API without any
credentials, which is what keeps client sites free of tokens. Making this repo
private silently stops every site from finding updates.

**The zip has to contain the plugin folder.** The workflow zips the folder
itself so it unpacks to `wp-content/plugins/<slug>/`. Hand-built zips of the
loose files install to the wrong place and orphan the old copy.

**Never re-tag a released version.** Sites cache by version number. Ship
`0.2.1` instead. The build refuses to publish over an existing release, so this
one is enforced rather than remembered.

**Skipping the changelog entry** just means the release notes fall back to a
pointer at `CHANGELOG.md`. Nothing breaks, but the client-facing "View details"
screen is where those notes show up.

---

## The updater file itself

`includes/class-scout-plugin-updater.php` is identical in every plugin (all
25 today), so each one can update itself when the others are inactive. No
script or CI check copies or compares it: you do both by hand. Change it in
Scout Core's copy, then copy that over every other plugin's, from the repo root:

```bash
for dir in scout-*/; do
  p="${dir%/}"
  [ "$p" = "scout-core" ] && continue
  [ -f "$p/includes/class-scout-plugin-updater.php" ] || continue
  cp scout-core/includes/class-scout-plugin-updater.php "$p/includes/"
done
md5sum scout-*/includes/class-scout-plugin-updater.php | awk '{print $1}' | sort -u | wc -l   # 1 means every copy matches
```

The loop skips a folder without its own copy, so a new plugin needs the file
added once by hand (and the `require_once` in its main file) before the loop
reaches it. The class guards itself with `class_exists`, so whichever copy
loads first is the one that runs. Bump and release every plugin you copied it
into, in one pull request; the Release workflow ships Scout Core first.
