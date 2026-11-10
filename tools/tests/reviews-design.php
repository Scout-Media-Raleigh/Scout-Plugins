<?php
// Scout Reviews 0.8.0: the stars, the review font and the /review/ page look
// follow the theme's DESIGN.md ("### Feature: reviews" and "### Feature:
// review page"), and stay exactly as before without those sections. Runs
// without WordPress:
//   php tools/tests/reviews-design.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['theme_dir'] = sys_get_temp_dir() . '/scout-reviews-design-' . getmypid();
$GLOBALS['opts']  = array();
@mkdir( $GLOBALS['theme_dir'] );

function apply_filters( $tag, $value ) { return $value; }
function __( $s ) { return $s; }
function get_stylesheet() { return basename( $GLOBALS['theme_dir'] ); }
function get_template_directory() { return $GLOBALS['theme_dir']; }
function get_stylesheet_directory() { return $GLOBALS['theme_dir']; }
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function get_bloginfo( $k ) { return 'Test Hall'; }
function wp_specialchars_decode( $s ) { return $s; }

$root = dirname( __DIR__, 2 );
require $root . '/scout-core/includes/design.php';
require $root . '/scout-reviews/includes/class-scout-reviews-brand.php';
require $root . '/scout-reviews/includes/class-scout-reviews-render.php';
require $root . '/scout-reviews/includes/class-scout-reviews-requests.php';
$B = 'Scout_Reviews_Brand';
$R = 'Scout_Reviews_Render';

function design_md( string $text ): void {
	file_put_contents( $GLOBALS['theme_dir'] . '/DESIGN.md', $text );
	Scout_Core_Design::flush();
}

$front = "---\ncolors:\n  text: \"#23212A\"\n  text_2: \"#5A5560\"\n  accent: \"#B8955A\"\nfonts:\n  heading: { family: Marcellus, weight: 400, generic: serif }\n  body: { family: Mulish, weight: 400 }\n  label: { family: Mulish, weight: 600 }\n---\n# Design\n";

// What 0.7.1 printed, so "no section" can be held to the exact bytes.
$was_page = '--accent:#0E8FE6;--on-accent:#111111;--bg:#0B0B0B;--surface:color-mix(in srgb, #0B0B0B 92%, #FFFFFF);--text:#F5F5F7;--text-2:color-mix(in srgb, #F5F5F7 78%, #0B0B0B);--text-3:color-mix(in srgb, #F5F5F7 64%, #0B0B0B);--line:rgba(255,255,255,.10);--strong:rgba(255,255,255,.16);--link:#0E8FE6;--font-head:"Hanken Grotesk", -apple-system, "Segoe UI", system-ui, sans-serif;--font-body:"Hanken Grotesk", -apple-system, "Segoe UI", system-ui, sans-serif;--font-label:"Geist Mono", ui-monospace, Menlo, monospace;color-scheme:dark;';
$was_card = '--srk-accent:#0E8FE6;--srk-ink:#1D1D1F;--srk-font-head:"Hanken Grotesk", -apple-system, "Segoe UI", system-ui, sans-serif;--srk-font-body:"Hanken Grotesk", -apple-system, "Segoe UI", system-ui, sans-serif;--srk-font-label:"Geist Mono", ui-monospace, Menlo, monospace;';
$was_font = 'https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap';
$saved    = array( 'accent' => '#7A5C2C', 'background' => '#FBF9F6', 'text_color' => '#23212A', 'heading_font' => 'Lora', 'body_font' => 'Mulish', 'label_font' => 'same' );
$was_2    = '--accent:#7A5C2C;--on-accent:#FFFFFF;--bg:#FBF9F6;--surface:color-mix(in srgb, #FBF9F6 92%, #000000);--text:#23212A;--text-2:color-mix(in srgb, #23212A 78%, #FBF9F6);--text-3:color-mix(in srgb, #23212A 64%, #FBF9F6);--line:rgba(0,0,0,.10);--strong:rgba(0,0,0,.18);--link:#7A5C2C;--font-head:"Lora", Georgia, "Times New Roman", serif;--font-body:"Mulish", -apple-system, "Segoe UI", system-ui, sans-serif;--font-label:"Mulish", -apple-system, "Segoe UI", system-ui, sans-serif;color-scheme:light;';

// ---------- no Feature sections ----------
design_md( $front . "\n## Colors\nWords about the palette.\n" );
ok( array() === scout_design_feature( 'reviews' ) && array() === scout_design_feature( 'review page' ), 'fixture without sections: Core hands over nothing' );
ok( '' === $R::design_css(), 'no Feature: reviews: no extra CSS, the stars stay amber' );
ok( '' === $R::design_css( array() ), 'an empty section adds nothing' );
ok( array() === $B::design(), 'no Feature: review page: nothing overrides the settings' );
$d = $B::defaults();
ok( $was_page === $B::page_vars( $B::apply( $d ) ), 'review page vars are byte for byte what 0.7.1 printed (defaults)' );
ok( $was_2 === $B::page_vars( $B::apply( $saved ) ), 'review page vars are byte for byte what 0.7.1 printed (saved look)' );
ok( $was_card === $B::card_vars( $B::apply( $d ) ) && $was_font === $B::fonts_url( $B::apply( $d ) ), 'printed cards and the font request are unchanged' );
$GLOBALS['opts']['scout_reviews_requests'] = $saved;
ok( Scout_Reviews_Requests::get() === Scout_Reviews_Requests::saved(), 'settings in use are the saved settings' );

// ---------- the fixture sections ----------
design_md(
	$front . "
### Feature: reviews
- layout_home: one voice at a time
- layout_page: list
- star: accent
- star_on_dark: accent
- star_label: \"5 out of 5\" in text_2 beside the stars, which are aria-hidden
- quote_font: heading
- quote_mark: accent
- name_style: label
- photo_frame: album page
- rotate_seconds: 9

### Feature: review page
- background: \"#FBF9F6\"
- text_color: \"#23212A\"
- accent: \"#7A5C2C\"
- heading_font: Marcellus
- body_font: Mulish
- label_font: Mulish
"
);
$css = $R::design_css();
ok( false !== strpos( $css, '.scout-reviews,.scout-reviews-summary{--sr-star:#B8955A}' ), 'star: accent arrives as the accent color' );
ok( false !== strpos( $css, '.scout-review__text{font-family:"Marcellus", serif}' ), 'quote_font: heading arrives as the heading stack' );
ok( 2 === substr_count( $css, '{' ), 'only star and quote_font are read: no layout, mark or photo rules' );
ok( false === strpos( $css, 'one voice' ) && false === strpos( $css, 'album' ) && false === strpos( $css, 'out of 5' ), 'unread keys never reach the CSS' );

$theme = $B::design();
ok( array( 'background' => '#FBF9F6', 'text_color' => '#23212A', 'accent' => '#7A5C2C', 'heading_font' => '"Marcellus", serif', 'body_font' => '"Mulish", sans-serif', 'label_font' => '"Mulish", sans-serif' ) === $theme, 'the six review page keys come from DESIGN.md: ' . json_encode( $theme ) );
$on = $B::apply( $d );
$pv = $B::page_vars( $on );
ok( false !== strpos( $pv, '--bg:#FBF9F6;' ) && false !== strpos( $pv, '--text:#23212A;' ) && false !== strpos( $pv, '--accent:#7A5C2C;' ), 'page vars use the DESIGN.md colors' );
ok( false !== strpos( $pv, 'color-scheme:light;' ), 'a light DESIGN.md background makes a light page' );
ok( false !== strpos( $pv, '--font-head:"Marcellus", serif;' ) && false !== strpos( $pv, '--font-body:"Mulish", sans-serif;' ) && false !== strpos( $pv, '--font-label:"Mulish", sans-serif;' ), 'font stacks are used as written, not swapped for the fallback' );
ok( 'https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700&display=swap' === $B::fonts_url( $on ), 'a listed family from DESIGN.md still loads from Google Fonts; an unlisted one (Marcellus) is left to the theme' );
ok( false !== strpos( $B::card_vars( $on ), '--srk-font-head:"Marcellus", serif;' ), 'the printed cards follow the same look' );
ok( Scout_Reviews_Requests::get()['background'] === '#FBF9F6' && Scout_Reviews_Requests::saved()['background'] === '#FBF9F6' && Scout_Reviews_Requests::get()['heading_font'] === '"Marcellus", serif' && Scout_Reviews_Requests::saved()['heading_font'] === 'Lora', 'settings in use take DESIGN.md; the saved value is kept for the form' );

// Only the keys present win.
$part = $B::apply( $saved, $B::design( array( 'accent' => '#000' ) ) );
ok( '#000000' === $part['accent'] && 'Lora' === $part['heading_font'] && '#FBF9F6' === $part['background'], 'only the keys the section has win (and #000 becomes #000000)' );

// ---------- nothing breaks out of the style tag ----------
$evil = $R::design_css( array( 'star' => 'red}</style><script>alert(1)</script>', 'quote_font' => 'x;}body{display:none' ) );
ok( false === strpos( $evil, '<' ) && false === strpos( $evil, '>' ) && false === strpos( $evil, ';}' ) && 2 === substr_count( $evil, '}' ), 'star and quote_font are stripped to safe characters: ' . str_replace( "\n", ' ', $evil ) );
$bad = $B::design( array( 'background' => 'red}</style>', 'text_color' => 'rgb(0,0,0)', 'accent' => '#12345', 'heading_font' => '"A"}</style><script>', 'body_font' => '' ) );
ok( array( 'heading_font' ) === array_keys( $bad ), 'colors that are not hex are dropped' );
ok( false === strpos( $bad['heading_font'], '<' ) && false === strpos( $bad['heading_font'], '}' ), 'a font with </style> is stripped: ' . $bad['heading_font'] );
ok( false === strpos( $B::page_vars( $B::apply( $d, $bad ) ), '</style>' ), 'page vars never carry a closing style tag' );
ok( 'Hanken Grotesk' === $B::family( 'Hanken Grotesk' ) && 'Marcellus' === $B::family( '"Marcellus", serif' ), 'family() reads a saved name and a stack' );

@unlink( $GLOBALS['theme_dir'] . '/DESIGN.md' );
@rmdir( $GLOBALS['theme_dir'] );

echo $fail ? "\n$fail failed\n" : "\nAll passed\n";
exit( $fail ? 1 : 0 );
