<?php
// Scout Call Buttons 0.2.0: the bar follows DESIGN.md's "### Feature: call buttons"
// section, and stays dark glass without one. Runs without WordPress:
//   php tools/tests/contact-design.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
define( 'ABSPATH', __DIR__ . '/' );
function apply_filters( $tag, $value ) { return $value; }
require __DIR__ . '/../../scout-core/includes/design.php';
require __DIR__ . '/../../scout-contact/includes/class-scout-contact.php';
$C = 'Scout_Contact';

$tokens = Scout_Core_Design::merge(
	Scout_Core_Design::defaults(),
	array(
		'colors' => array( 'text' => '#23212A', 'line' => 'rgba(35,33,42,.12)', 'accent_fill' => '#23212A', 'on_accent' => '#FBF9F6' ),
		'fonts'  => array( 'label' => array( 'family' => 'Mulish', 'weight' => 600, 'case' => 'upper', 'tracking' => '.3em' ) ),
	)
);

ok( '' === $C::design_css( array(), $tokens ), 'no Feature section: nothing is added, the bar stays dark glass' );

// A porcelain bar, values as scout_design_feature() hands them over (roles resolved).
$css = $C::design_css(
	array(
		'bar'              => 'rgba(251,249,246,.97)',
		'bar_blur'         => '10px',
		'bar_line'         => 'rgba(35,33,42,.12)',
		'primary_fill'     => '#23212A',
		'primary_text'     => '#FBF9F6',
		'secondary_border' => 'rgba(184,149,90,.55)',
		'label_font'       => '"Mulish", sans-serif',
		'label_size'       => '11px',
		'label_tracking'   => '.2em',
		'primary_label'    => 'Check your date',
	),
	$tokens
);
ok( false !== strpos( $css, '.scc-bar{background:rgba(251,249,246,.97);box-shadow:0 8px 24px rgba(0,0,0,.12)}' ), 'a light bar, with a softer shadow' );
ok( false !== strpos( $css, '.scc-btn,.scc-btn:hover,.scc-btn:focus-visible{color:#23212A}' ), 'a light bar gets the ink text color, not light text' );
ok( false !== strpos( $css, 'background:rgba(0,0,0,.05)' ), 'hover darkens on a light bar' );
ok( false !== strpos( $css, 'backdrop-filter:blur(10px)' ) && false !== strpos( $css, '.scc-bar{border-color:rgba(35,33,42,.12)}' ), 'blur and hairline' );
ok( false !== strpos( $css, '.scc-main{background:#23212A;color:#FBF9F6}' ), 'the primary button fill and text' );
ok( false !== strpos( $css, 'box-shadow:inset 0 0 0 1px rgba(184,149,90,.55)' ), 'the secondary border without changing the button size' );
ok( false !== strpos( $css, '.scc-btn span,.scc-main{font-family:"Mulish", sans-serif;font-size:11px;letter-spacing:.2em;text-transform:uppercase}' ), 'label font from the label role, with its case and the section\'s tracking' );
ok( false === strpos( $css, 'Check your date' ), 'copy (primary_label) is not design: the button label stays in Call buttons settings' );

// A dark bar keeps light text.
$dark = $C::design_css( array( 'bar' => '#1B191F' ), $tokens );
ok( false !== strpos( $dark, 'color:#F5F5F7' ) && false !== strpos( $dark, 'rgba(255,255,255,.08)' ) && false === strpos( $dark, 'box-shadow' ), 'a dark bar keeps light text and the stylesheet shadow' );
ok( false !== strpos( $C::design_css( array( 'bar' => '#FFFFFF', 'bar_text' => '#0A0A0A' ), $tokens ), 'color:#0A0A0A' ), 'bar_text wins over the guess' );

// Nothing can break out of the style tag.
$evil = $C::design_css( array( 'bar' => 'red}</style><script>alert(1)</script>' ), $tokens );
ok( false === strpos( $evil, '<' ) && false === strpos( $evil, '}<' ) && 1 === substr_count( $evil, '.scc-bar{' ), 'values are stripped to color characters' );

echo $fail ? "\n$fail failed\n" : "\nAll passed\n";
exit( $fail ? 1 : 0 );
