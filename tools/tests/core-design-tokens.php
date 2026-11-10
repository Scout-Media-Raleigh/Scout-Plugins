<?php
// Scout Core 1.11.0 design tokens: Feature sections with role names, the font
// "generic" key, type/space/motion scales, the new contrast pairs, and the
// design-build output. Runs without WordPress:
//   php tools/tests/core-design-tokens.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['theme_dir'] = '';
function apply_filters( $tag, $value ) { return $value; }
function get_stylesheet() { return basename( $GLOBALS['theme_dir'] ); }
function get_template_directory() { return $GLOBALS['theme_dir']; }
function get_stylesheet_directory() { return $GLOBALS['theme_dir']; }
require __DIR__ . '/../../scout-core/includes/design.php';
$D = 'Scout_Core_Design';

$md = <<<'MD'
---
design: 9
mode: light
colors:
  bg: "#FBF9F6"
  surface: "#FFFFFF"
  text: "#23212A"
  text_2: "#5E5A64"
  line: "rgba(35,33,42,.12)"
  accent: "#B8955A"
  accent_text: "#7A5C2C"
  accent_fill: "#23212A"
  on_accent: "#FBF9F6"
  night: "#1B191F"
  on_night: "#F3EEE7"
  on_night_2: "#C9C3BC"
  good: "#2F6B4F"
  warn: "#8A5A00"
  bad: "#A3342B"
fonts:
  heading: { family: "Marcellus", weight: 400, generic: serif }
  body:    { family: "Mulish", weight: 400 }
  label:   { family: "Mulish", weight: 600, case: upper, tracking: ".3em" }
type_scale: { hero: "38px 72px", h2: "32px 4.2vw 54px", body: "17px" }
space: { wrap: "1240px", section: "72px 128px" }
motion: { fast: ".2s", ease: "cubic-bezier(.2,.7,.2,1)" }
radius: { pill: "0", card: "0", control: "0", chip: "0" }
---
# Avanté-style fixture

### Feature: call buttons
- bar: rgba(251,249,246,.97)
- bar_line: line
- primary_fill: accent_fill
- primary_text: on_accent
- label_font: label
- primary_label: Check your date

### Feature: review page
- background: "#FBF9F6"
- heading_font: Marcellus
- body_font: Mulish

### Feature: events
- line: line
- inset: surface_2
- date_label: accent_text
- ticket_frame: line gold, inset 8px

### Feature: people
- accent: accent_text
MD;

$theme = sys_get_temp_dir() . '/scout-design-' . getmypid();
@mkdir( $theme );
file_put_contents( $theme . '/DESIGN.md', $md );
$GLOBALS['theme_dir'] = $theme;
$D::flush();

// Feature sections: role names resolve, raw values stay.
$bar = $D::feature( 'Call buttons' );
ok( 'rgba(251,249,246,.97)' === $bar['bar'], 'a literal color passes through' );
ok( 'rgba(35,33,42,.12)' === $bar['bar_line'] && '#23212A' === $bar['primary_fill'] && '#FBF9F6' === $bar['primary_text'], 'color role names become the colors' );
ok( '"Mulish", sans-serif' === $bar['label_font'], 'a font role name becomes that font\'s stack' );
ok( 'Check your date' === $bar['primary_label'], 'plain text stays text' );
ok( array() === $D::feature( 'cards' ), 'a feature the theme does not describe is empty, so the plugin keeps its own look' );
$page = $D::feature( 'review page' );
ok( '"Marcellus", serif' === $page['heading_font'], 'a family DESIGN.md uses keeps its generic key' );
ok( '"Mulish", sans-serif' === $page['body_font'], 'a sans family gets sans-serif' );
$events = $D::feature( 'events' );
ok( '#EDEDF0' === $events['inset'], 'a role DESIGN.md leaves out resolves to Scout\'s default' );
ok( 'line gold, inset 8px' === $events['ticket_frame'], 'a sentence that starts with a role name stays as written' );
ok( '#7A5C2C' === $events['date_label'], 'accent_text resolves' );

// scout_design( feature ) no longer writes role names over tokens.
$t = $D::get( 'events' );
ok( 'rgba(35,33,42,.12)' === $t['colors']['line'], 'a "line: line" feature line keeps the real line color (it used to become the word "line")' );
$t = $D::get( 'people' );
ok( '#7A5C2C' === $t['colors']['accent'], 'a feature accent naming a role overrides with the color' );

// Generic families.
ok( 'serif' === $D::generic( array( 'family' => 'Marcellus', 'generic' => 'serif' ) ), 'the generic key wins' );
ok( 'serif' === $D::generic( array( 'family' => 'Noto Serif' ) ), 'a family named Serif is serif' );
ok( 'sans-serif' === $D::generic( array( 'family' => 'IBM Plex Sans' ) ) && 'sans-serif' === $D::generic( array( 'family' => 'PT Sans Serif' ) ), 'Sans Serif names stay sans-serif' );
ok( 'monospace' === $D::generic( array( 'family' => 'Geist Mono' ) ), 'a Mono family is monospace' );
ok( 'sans-serif' === $D::generic( array( 'family' => 'Marcellus' ) ), 'with no key and no hint, sans-serif (unchanged behavior)' );
ok( 'sans-serif' === $D::generic( array( 'family' => 'Mulish', 'generic' => 'fantasy;}' ) ), 'an unknown generic value is ignored' );

// Scales.
ok( 'clamp(38px, 5.6vw, 72px)' === $D::fluid( '38px 72px' ), 'a min max pair becomes a clamp that reaches max at 1280px' );
ok( 'clamp(32px, 4.2vw, 54px)' === $D::fluid( '32px 4.2vw 54px' ), 'three values are used as written' );
ok( '17px' === $D::fluid( '17px' ), 'one value passes through' );
$css = $D::css();
ok( false !== strpos( $css, '--scout-font-heading:"Marcellus", serif;' ), 'css(): the serif heading ends in serif' );
ok( false !== strpos( $css, '--scout-type-hero:clamp(38px, 5.6vw, 72px);' ) && false !== strpos( $css, '--scout-space-section:clamp(72px, 10vw, 128px);' ), 'css(): type and space scales' );
ok( false !== strpos( $css, '--scout-motion-ease:cubic-bezier(.2,.7,.2,1);' ) && false !== strpos( $css, '--scout-motion-fast:.2s;' ), 'css(): motion passes through' );
ok( false !== strpos( $css, '--scout-night:#1B191F;' ) && false !== strpos( $css, '--scout-on-night-2:#C9C3BC;' ), 'css(): night colors' );

// Contrast pairs for the new keys, only when present.
$pairs = array_column( $D::issues(), 'level', 'pair' );
$labels = implode( ' | ', array_keys( $pairs ) );
ok( false !== strpos( $labels, 'Text on night bands' ) && false !== strpos( $labels, 'Errors on the page' ), 'night and status colors are checked' );
ok( false === strpos( $labels, 'highlight' ), 'a pair whose colors are missing is skipped' );
$bad = $D::issues( array( 'colors' => array( 'night' => '#1B191F', 'on_night' => '#2A2830' ) ) );
ok( 1 === count( $bad ) && ! $bad[0]['ok'] && 'fail' === $bad[0]['level'], 'unreadable text on night fails the build' );
ok( 4 === count( $D::issues( $D::defaults() ) ), 'a theme without the new keys gets the same four checks as before' );

// design-build writes the same, and the new keys only when present.
$build = sys_get_temp_dir() . '/scout-design-build-' . getmypid();
@mkdir( $build );
file_put_contents( $build . '/DESIGN.md', $md );
file_put_contents( $build . '/theme.json', '{}' );
exec( 'php ' . escapeshellarg( __DIR__ . '/../design-build.php' ) . ' ' . escapeshellarg( $build ) . ' 2>&1', $out, $code );
$tokens_css = (string) @file_get_contents( $build . '/assets/css/design-tokens.css' );
$tj         = json_decode( (string) @file_get_contents( $build . '/theme.json' ), true );
ok( 0 === $code, 'design-build runs on the fixture' );
ok( false !== strpos( $tokens_css, '--scout-font-heading: "Marcellus", serif;' ), 'design-tokens.css: serif heading' );
ok( false !== strpos( $tokens_css, '--scout-type-hero: clamp(38px, 5.6vw, 72px);' ) && false !== strpos( $tokens_css, '--scout-motion-fast: .2s;' ), 'design-tokens.css: scales' );
$slugs = array_column( (array) ( $tj['settings']['color']['palette'] ?? array() ), 'slug' );
ok( in_array( 'night', $slugs, true ) && in_array( 'bad', $slugs, true ) && ! in_array( 'highlight', $slugs, true ), 'theme.json: night and status colors join the palette when present' );
ok( '"Marcellus", serif' === ( $tj['settings']['typography']['fontFamilies'][0]['fontFamily'] ?? '' ), 'theme.json: serif heading' );
ok( '.3s' !== ( $tj['settings']['custom']['scout']['motion']['fast'] ?? '' ) && '.2s' === ( $tj['settings']['custom']['scout']['motion']['fast'] ?? '' ), 'theme.json: scales under custom.scout' );
exec( 'php ' . escapeshellarg( __DIR__ . '/../design-build.php' ) . ' ' . escapeshellarg( $build ) . ' --check 2>&1', $out2, $code2 );
ok( 0 === $code2, 'a fresh build passes --check' );

foreach ( array( $theme . '/DESIGN.md', $build . '/DESIGN.md', $build . '/theme.json', $build . '/assets/css/design-tokens.css' ) as $f ) {
	@unlink( $f );
}
@rmdir( $build . '/assets/css' );
@rmdir( $build . '/assets' );
@rmdir( $build );
@rmdir( $theme );

echo $fail ? "\n$fail failed\n" : "\nAll passed\n";
exit( $fail ? 1 : 0 );
