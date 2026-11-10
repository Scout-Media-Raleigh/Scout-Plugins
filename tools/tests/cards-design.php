<?php
// Scout Cards 1.2.0: the card pages follow DESIGN.md's "### Feature: cards"
// section (Scout Core 1.11.0), and print exactly what they printed before
// without one, or without Scout Core. Runs without WordPress:
//   php tools/tests/cards-design.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
define( 'ABSPATH', __DIR__ . '/' );
function apply_filters( $tag, $value ) { return $value; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function sanitize_hex_color( $c ) { return preg_match( '/^#([0-9a-fA-F]{3}){1,2}$/', (string) $c ) ? $c : ''; }
require __DIR__ . '/../../scout-cards/includes/render.php';
$R = 'Scout_Cards_Render';

// Today's block, as render.php 1.1.0 printed it.
function before( array $s ) {
	$accent = sanitize_hex_color( $s['accent'] ) ? sanitize_hex_color( $s['accent'] ) : '#0E8FE6';
	$font   = $s['font'] ? $s['font'] : 'inherit';
	return '<style>.scc{--scc-accent:' . esc_html( $accent ) . ';--scc-font:' . esc_html( $font ) . '}</style>';
}
$plain = array( 'mode' => 'dark', 'accent' => '#0E8FE6', 'font' => '' );
$saved = array( 'mode' => 'light', 'accent' => '#B5121B', 'font' => '"Hanken Grotesk", sans-serif' );
$odd   = array( 'mode' => 'sepia', 'accent' => 'red', 'font' => '' );

// 1. Without Scout Core (no scout_design_feature): unchanged.
ok( ! function_exists( 'scout_design_feature' ), 'setup: Scout Core is not loaded yet' );
foreach ( array( $plain, $saved, $odd ) as $i => $s ) {
	$look = $R::look( $s );
	ok( before( $s ) === $look['style'], "no Scout Core: the style block is byte for byte today's (settings $i)" );
	ok( ( 'light' === $s['mode'] ? 'light' : 'dark' ) === $look['mode'], "no Scout Core: the saved mode (settings $i)" );
	ok( ( 'light' === $s['mode'] ? '#F5F5F7' : '#0B0B0B' ) === $look['bg'], "no Scout Core: today's page color (settings $i)" );
}

// 2. Scout Core loaded, the theme has DESIGN.md with no cards section: unchanged.
$dir = sys_get_temp_dir() . '/scout-cards-design-' . getmypid();
@mkdir( $dir );
function get_template_directory() { return $GLOBALS['theme_dir']; }
function get_stylesheet_directory() { return $GLOBALS['theme_dir']; }
function get_stylesheet() { return $GLOBALS['theme_sheet']; }
$GLOBALS['theme_dir']   = $dir;
$GLOBALS['theme_sheet'] = 'no-section';
$front = <<<'MD'
---
design: 1
mode: light
colors:
  bg: "#FBF9F6"
  surface: "#FFFFFF"
  surface_2: "#F3EEE7"
  text: "#23212A"
  text_2: "#5E5A64"
  line: "rgba(35,33,42,.12)"
  accent: "#7A5C2C"
  accent_fill: "#23212A"
  on_accent: "#FBF9F6"
fonts:
  heading: { family: "Cormorant Garamond", weight: 500 }
  body: { family: "Mulish", weight: 400 }
radius: { pill: "0", card: "0", control: "0", chip: "0" }
---
# Design

MD;
file_put_contents( $dir . '/DESIGN.md', $front . "### Feature: call buttons\n- bar: surface\n" );
require __DIR__ . '/../../scout-core/includes/design.php';
foreach ( array( $plain, $saved, $odd ) as $i => $s ) {
	ok( before( $s ) === $R::look( $s )['style'], "no cards section: the style block is byte for byte today's (settings $i)" );
}
ok( before( $plain ) === $R::look( $plain, array(), array() )['style'], 'an empty section passed in: today\'s block' );

// 3. The fixture section, read through scout_design_feature() and scout_design().
file_put_contents( $dir . '/DESIGN.md', $front . "### Feature: cards\n- mode: light\n- accent: \"#7A5C2C\"\n- font: Mulish\n- photo: assets/photos/film/example.webp\n" );
Scout_Core_Design::flush();
$GLOBALS['theme_sheet'] = 'with-section';
$look = $R::look( $plain );
$css  = $look['style'];
ok( 'light' === $look['mode'], 'mode from the section wins over the saved dark' );
ok( '#FBF9F6' === $look['bg'], 'the page color (theme-color and manifest) is the theme bg' );
ok( 0 === strpos( $css, '<style>.scc{--scc-accent:#7A5C2C;--scc-font:"Mulish", sans-serif}' ), 'accent and font from the section, the font as a real stack (no &quot;)' );
ok( false !== strpos( $css, 'html .scc[data-mode]{--scc-bg:#FBF9F6;--scc-surface:#FFFFFF;--scc-surface-2:#F3EEE7;--scc-text:#23212A;--scc-text-2:#5E5A64;--scc-line:rgba(35,33,42,.12);--scc-text-3:#5E5A64;--scc-on-accent:#FBF9F6;' ), 'light colors and on-accent from the tokens, text-3 from text_2' );
ok( false !== strpos( $css, '--scc-radius-card:0;--scc-radius-control:0;--scc-radius-pill:0;--scc-radius-chip:0' ), 'square corners from the radius map' );
ok( false === strpos( $css, '--scc-font-heading' ), 'a section font sets the headings too (they inherit it)' );
ok( false !== strpos( $css, 'html body.scc-page{background:#FBF9F6}html body.scc-page:has(.scc[data-mode]){background:#FBF9F6}' ), 'the page background behind the card' );
ok( false === strpos( $css, 'example.webp' ), 'photo is not read (cards has no cover photo setting)' );
ok( '</style>' === substr( $css, -8 ) && 1 === substr_count( $css, '<' ) - 1, 'one style block, nothing else' );

// 4. Values passed in, as scout_design_feature() hands them over.
$tokens = scout_design( 'cards' );
$no_font = $R::look( $plain, array( 'accent' => '#7A5C2C' ), $tokens );
ok( 'dark' === $no_font['mode'], 'no mode line: the saved mode stays' );
ok( false !== strpos( $no_font['style'], '--scc-font:"Mulish", sans-serif}' ) && false !== strpos( $no_font['style'], '--scc-font-heading:"Cormorant Garamond", sans-serif' ), 'no font line, no saved font: body and heading roles' );
$keep = $R::look( $saved, array( 'mode' => 'light' ), $tokens );
ok( 0 === strpos( $keep['style'], '<style>.scc{--scc-accent:#B5121B;--scc-font:"Hanken Grotesk", sans-serif}' ), 'keys the section leaves out keep the saved accent and font' );
$dark = $R::look( $plain, array( 'mode' => 'dark' ), $tokens );
ok( false === strpos( $dark['style'], '--scc-bg' ) && '#0B0B0B' === $dark['bg'] && false === strpos( $dark['style'], 'body.scc-page' ), 'dark with no night tokens keeps today\'s dark colors' );
$night = $tokens;
$night['colors'] += array( 'night' => '#1B191F', 'on_night' => '#FBF9F6', 'on_night_2' => '#C9C3BA' );
$nd = $R::look( $plain, array( 'mode' => 'dark' ), $night );
ok( '#1B191F' === $nd['bg'] && false !== strpos( $nd['style'], '--scc-bg:#1B191F;--scc-surface:color-mix(in srgb, #FBF9F6 6%, #1B191F)' ) && false !== strpos( $nd['style'], '--scc-text:#FBF9F6;--scc-text-2:#C9C3BA;--scc-text-3:#C9C3BA' ), 'dark uses night, on_night and on_night_2 when the theme has them' );
$scout = Scout_Core_Design::merge( Scout_Core_Design::defaults(), array() );
$same  = $R::look( $plain, array( 'mode' => 'light' ), $scout );
ok( false === strpos( $same['style'], '--scc-radius' ), 'radius equal to the Scout defaults is left to the stylesheet (the hero card keeps 22px)' );

// 5. Nothing can break out of the style tag.
$evil = $R::look( $plain, array( 'accent' => 'red}</style><script>alert(1)</script>', 'font' => 'x;}</style><b>' ), $tokens );
ok( 1 === substr_count( $evil['style'], '<' ) - 1 && false === strpos( $evil['style'], '<script' ) && false === strpos( $evil['style'], '</style><' ), 'a </style> value is stripped to CSS characters' );
ok( false === strpos( $evil['style'], ';}' ) && false === strpos( $evil['style'], 'red}' ), 'no braces or semicolons from a value' );
$evil_tokens = $tokens;
$evil_tokens['colors']['surface'] = '#fff}</style><script>x</script>';
ok( false === strpos( $R::look( $plain, array( 'mode' => 'light' ), $evil_tokens )['style'], '<script' ), 'a token value is stripped too' );

@unlink( $dir . '/DESIGN.md' );
@rmdir( $dir );
echo $fail ? "\n$fail failed\n" : "\nAll passed\n";
exit( $fail ? 1 : 0 );
