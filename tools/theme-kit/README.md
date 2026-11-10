# Scout theme kit

Files every Scout theme copies in, so every site has the same back end.

| File | Copy to | What it does |
|---|---|---|
| `scout-requires-build.php` | `inc/scout-requires-build.php`, required from `functions.php` | When Scout Build is missing, admins get one "Install Scout Build" button. It installs the newest release from the release feed (or turns on a copy already there). Scout Build updates itself after that. Also carries a stand-in `scout_core_owns()` that answers false while Scout Core is off, so a theme's rule 6 fallback (`if ( ! scout_core_owns( 'hours' ) )`) never fatals. |
| `../admin-kit/class-scout-admin-ui.php` + `scout-admin.css` | `inc/` and `assets/css/` | The Scout look for the theme's own screen, which sits at Scout → Website → Theme. |
| `page-style-guide.php` | The theme root, as `page-style-guide.php` | The `/style-guide/` page (Client Design Playbook deliverable 7). It draws itself from DESIGN.md through `scout_design()`: every color with its contrast, the font roles and type scale, space, corners and motion, buttons and a form field, each Feature section as Scout reads it, the logo and mark, and the contrast checks. It uses only `--scout-*` variables, so it takes the site's own look. A theme adds its own samples (photo frames, testimonials, icons) with the `scout_style_guide_section` action; the docblock lists the section ids. |

Change a file here first, then copy it into each theme at that theme's next
update. Tested 2026-10-13 in WordPress 6.5 (Playground): newest version picked
from the feed, installed, activated, notice gone.

## The style guide page

1. Copy `page-style-guide.php` into the theme root.
2. Ship the page through Site Sync with `"noindex": true`, so it stays out of
   search and the sitemap (Scout Core 1.11.0+):

   ```json
   { "slug": "style-guide", "title": "Style guide", "create": "publish", "noindex": true }
   ```

   Without Site Sync, tick Hide from search in the page's Scout SEO box. The
   template prints no robots tag of its own, because Scout Core owns it (rule 7).
3. Add the theme's own samples with `scout_style_guide_section`, in the
   section order the playbook uses: tokens, feel, color, type, space,
   components, features, voice, access. The page should follow the playbook's
   section order, not copy another site's look.
