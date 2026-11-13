<?php
// Scout Build 0.18.0: the owner's view. The simple menu (groups, screens and
// WordPress menus hidden from people who are not operators, "Show everything"
// on the profile, Today's technical items for operators only), Home cards,
// shortcuts and the "Your other tools" links, landing on Home after login,
// Scout Chat's Chat screen under Scout tools with an outside widget on, and
// the new setup file fills (contact sets, booking, chat outside widget, events).
// Runs without WordPress:
//   php tools/tests/owner-view.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }

define( 'ABSPATH', __DIR__ . '/' );
define( 'SCOUT_HUB_OPERATORS', 'ops@example.com' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opt']     = array();
$GLOBALS['umeta']   = array();
$GLOBALS['filters'] = array();
$GLOBALS['users']   = array();
$GLOBALS['me']      = 0;
$GLOBALS['theme']   = sys_get_temp_dir() . '/scout-owner-view-' . getmypid();
$GLOBALS['pages']   = array( 'bodywork' => 11, 'request' => 12 );
$GLOBALS['removed'] = array();
@mkdir( $GLOBALS['theme'] );

class WP_User {
	public $ID = 0; public $user_email = ''; public $caps = array(); public $first_name = ''; public $display_name = '';
	function __construct( $id = 0, $email = '', $caps = array() ) { $this->ID = $id; $this->user_email = $email; $this->caps = $caps; }
	function exists() { return $this->ID > 0; }
}
class WP_Error {}

function add_filter( $tag, $cb, $p = 10, $n = 1 ) { $GLOBALS['filters'][ $tag ][ $p ][] = $cb; }
function add_action( $tag, $cb = null, $p = 10, $n = 1 ) {}
function apply_filters( $tag, $value, ...$args ) {
	$all = $GLOBALS['filters'][ $tag ] ?? array();
	ksort( $all );
	foreach ( $all as $cbs ) { foreach ( $cbs as $cb ) { $value = $cb( $value, ...$args ); } }
	return $value;
}
function do_action( ...$a ) {}
function __( $s ) { return $s; }
function esc_html__( $s ) { return $s; }
function esc_html_e( $s ) { echo htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function esc_url_raw( $u, $p = null ) { $u = trim( (string) $u ); $p = $p ?: array( 'http', 'https' ); foreach ( $p as $s ) { if ( 0 === stripos( $u, $s . '://' ) ) { return $u; } } return ''; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $s ) { return filter_var( $s, FILTER_VALIDATE_EMAIL ) ? $s : ''; }
function is_email( $s ) { return (bool) filter_var( $s, FILTER_VALIDATE_EMAIL ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function wp_unslash( $v ) { return $v; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_kses_post( $s ) { return (string) $s; }
function wp_generate_password( $n = 12, $s = true ) { return substr( str_repeat( 'abcdefgh', 4 ), 0, $n ); }
function wp_doing_ajax() { return false; }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function admin_url( $p = '' ) { return 'https://site.test/wp-admin/' . ltrim( $p, '/' ); }
function home_url( $p = '' ) { return 'https://site.test' . $p; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opt'] ) ? $GLOBALS['opt'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opt'][ $k ] = $v; return true; }
function get_user_meta( $u, $k, $s = false ) { return $GLOBALS['umeta'][ $u ][ $k ] ?? ''; }
function update_user_meta( $u, $k, $v ) { $GLOBALS['umeta'][ $u ][ $k ] = $v; }
function delete_user_meta( $u, $k ) { unset( $GLOBALS['umeta'][ $u ][ $k ] ); }
function get_current_user_id() { return $GLOBALS['me']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['me'] ] ?? new WP_User(); }
function get_userdata( $id ) { return $GLOBALS['users'][ $id ] ?? false; }
function user_can( $u, $cap ) { return ! empty( $u->caps[ $cap ] ); }
function current_user_can( $cap, ...$a ) { return 'edit_user' === $cap ? true : user_can( wp_get_current_user(), $cap ); }
function get_stylesheet_directory() { return $GLOBALS['theme']; }
function get_template_directory() { return $GLOBALS['theme']; }
function get_page_by_path( $p ) { $id = $GLOBALS['pages'][ $p ] ?? 0; return $id ? (object) array( 'ID' => $id ) : null; }
function post_type_exists( $t ) { return false; }
function remove_menu_page( $s ) { $GLOBALS['removed'][] = $s; }
function remove_submenu_page( $p, $c ) { $GLOBALS['removed'][] = $p . '>' . $c; }
function checked( $a, $b, $e = true ) { return $a === $b ? ' checked' : ''; }
function plugin_basename( $f ) { return basename( $f ); }

require __DIR__ . '/../admin-kit/class-scout-admin-ui.php';
require __DIR__ . '/../../scout-build/includes/class-scout-hub-access.php';
require __DIR__ . '/../../scout-build/includes/class-scout-hub-setup.php';
require __DIR__ . '/../../scout-build/includes/class-scout-hub-owner-view.php';
require __DIR__ . '/../../scout-contact/includes/class-scout-contact.php';
require __DIR__ . '/../../scout-booking/includes/class-scout-booking.php';
define( 'SCOUT_CHAT_FILE', __FILE__ );
require __DIR__ . '/../../scout-chat/includes/class-scout-chat.php';
require __DIR__ . '/../../scout-chat/includes/class-scout-chat-admin.php';

$V = 'Scout_Hub_Owner_View';
$V::boot();
Scout_Chat_Admin::boot();

function setup_file( array $data ) {
	file_put_contents( $GLOBALS['theme'] . '/scout-setup.json', json_encode( $data ) );
	Scout_Hub_Owner_View::reset();
}

$GLOBALS['users'][1] = new WP_User( 1, 'ops@example.com', array( 'manage_options' => true, 'activate_plugins' => true, 'edit_posts' => true ) );
$GLOBALS['users'][2] = new WP_User( 2, 'owner@example.com', array( 'manage_options' => true, 'activate_plugins' => true, 'edit_posts' => true ) );
$GLOBALS['users'][3] = new WP_User( 3, 'shop@example.com', array( 'read' => true ) );

/* ---- Defaults: nothing changes ---- */

setup_file( array( 'features' => array( 'use' => array( 'scout-core' ) ) ) );
$GLOBALS['me'] = 2;
$s = $V::settings();
ok( ! $s['simple'] && ! $s['land_on_home'] && ! $s['links'] && ! $s['shortcuts'], 'a setup file without a dashboard section turns nothing on' );
ok( array( 'build' ) === Scout_Admin_UI::hidden(), 'by default an owner who is not an operator loses only Scout tools' );
$today = array( array( 'id' => 'health-secure-connection' ), array( 'id' => 'inbox-chat' ) );
ok( 2 === count( $V::today( $today ) ), 'Today keeps its technical items while the simple view is off' );

/* ---- The simple view ---- */

setup_file(
	array(
		'dashboard' => array(
			'simple' => array(
				'hide_groups'  => array( 'search', 'policies', 'health', 'overview', 'inbox', 'nonsense' ),
				'hide_screens' => array( 'scout-redirects', 'scout-contact', 'edit.php?post_type=scout_review', '../x' ),
				'hide_menus'   => array( 'tools.php', 'options-general.php>options-privacy.php' ),
			),
		),
	)
);
$s = $V::settings();
ok( $s['simple'], 'a simple section with lists turns the simple view on' );
ok( array( 'search', 'policies', 'health' ) === $s['hide_groups'], 'Home and To do never hide, and unknown groups are dropped' );
ok( ! in_array( '../x', $s['hide_screens'], true ), 'a screen slug that climbs folders is dropped' );
$hidden = Scout_Admin_UI::hidden();
ok( in_array( 'search', $hidden, true ) && in_array( 'health', $hidden, true ) && in_array( 'build', $hidden, true ), 'the owner loses the listed groups through the admin kit' );
ok( 1 === count( $V::today( $today ) ) && 'inbox-chat' === $V::today( $today )[0]['id'], "the owner's Today leaves out failed health checks" );
ok( array() === $V::today( array( array( 'id' => 'hidden-from-google' ), array( 'id' => 'law', 'technical' => true ) ) ), 'a site hidden from Google and items marked technical go to operators only' );

$GLOBALS['me'] = 1;
ok( array() === Scout_Admin_UI::hidden(), 'an operator still sees every group' );
ok( 2 === count( $V::today( $today ) ), "an operator's Today keeps the technical items" );

// Screens leave the sidebar, except the one being viewed and Home.
$GLOBALS['me'] = 2;
$GLOBALS['pagenow'] = 'admin.php';
$_GET = array( 'page' => 'scout-contact' );
$GLOBALS['submenu'] = array(
	'scout' => array(
		array( 'Home', 'edit_posts', 'site-build-features' ),
		array( 'Redirects', 'manage_options', 'scout-redirects' ),
		array( 'Redirects log', 'manage_options', 'scout-redirects&tab=log' ),
		array( 'Call buttons', 'manage_options', 'scout-contact' ),
		array( 'Reviews', 'manage_options', 'edit.php?post_type=scout_review' ),
		array( 'Hours', 'manage_options', 'scout-hours' ),
	),
);
$V::hide_screens();
$left = array_column( $GLOBALS['submenu']['scout'], 2 );
ok( array( 'site-build-features', 'scout-contact', 'scout-hours' ) === array_values( $left ), 'listed screens and their tabs leave the menu; the screen being viewed stays, so its address keeps working' );
ok( $V::matches( 'scout-contact&tab=sets', array( 'scout-contact' ) ) && ! $V::matches( 'scout-contact-x', array( 'scout-contact' ) ) && ! $V::matches( 'edit.php?post_type=other', array( 'edit.php?post_type=scout_review' ) ), 'a plain slug matches its tabs only; a slug with a query matches only itself' );
$_GET = array();
$GLOBALS['pagenow'] = 'index.php';
$V::hide_menus();
ok( array( 'tools.php', 'options-general.php>options-privacy.php' ) === $GLOBALS['removed'], 'the listed WordPress menus are hidden' );

// "Show everything" brings it back for that person only.
ob_start();
$V::profile_field( $GLOBALS['users'][2] );
$field = ob_get_clean();
ok( false !== strpos( $field, 'name="scout_show_everything"' ), 'the owner gets a Show everything switch on their own profile' );
ob_start();
$GLOBALS['me'] = 1;
$V::profile_field( $GLOBALS['users'][1] );
ok( '' === ob_get_clean(), 'operators never see the switch' );
$GLOBALS['me'] = 2;
$_POST = array( 'scout_show_everything' => '1' );
$V::save_profile( 2 );
ok( $V::shows_everything( 2 ) && array( 'build' ) === Scout_Admin_UI::hidden(), 'ticked, the owner sees every group again (Scout tools stays for operators)' );
ok( 2 === count( $V::today( $today ) ), 'ticked, Today shows the technical items again' );
$GLOBALS['removed'] = array();
$V::hide_menus();
ok( array() === $GLOBALS['removed'], 'ticked, no WordPress menu is hidden' );
$_POST = array();
$V::save_profile( 2 );
ok( ! $V::shows_everything( 2 ), 'unticked, the switch is off again' );
$V::save_profile( 3 );
ok( ! isset( $GLOBALS['umeta'][3] ), 'nobody saves the switch on someone else\'s profile' );

setup_file( array( 'dashboard' => array( 'simple' => array( 'on' => false, 'hide_groups' => array( 'search' ) ) ) ) );
ok( ! $V::settings()['simple'] && array( 'build' ) === Scout_Admin_UI::hidden(), '"on": false keeps the lists and turns the simple view off' );

/* ---- Home: links, shortcuts, cards ---- */

setup_file(
	array(
		'dashboard' => array(
			'links'     => array(
				array( 'label' => 'Booking calendar', 'url' => 'https://booking.example.com/', 'note' => 'Massage bookings.' ),
				array( 'label' => 'Not https', 'url' => 'http://example.com/' ),
				array( 'label' => 'Script', 'url' => 'javascript:alert(1)' ),
				array( 'url' => 'https://no-label.example.com/' ),
				array( 'label' => 'Newsletter', 'url' => 'https://news.example.com/dashboard' ),
			),
			'shortcuts' => array(
				array( 'label' => 'Add an event', 'url' => 'post-new.php?post_type=scout_event', 'note' => 'A new date.' ),
				array( 'label' => 'Outside', 'url' => 'https://example.com/wp-admin/x.php' ),
				array( 'label' => 'Climb', 'url' => '../wp-config.php' ),
				array( 'label' => 'Block time off', 'url' => '/wp-admin/admin.php?page=scout-booking&view=blocks' ),
			),
		),
	)
);
$s = $V::settings();
ok( 2 === count( $s['links'] ) && 'https://booking.example.com/' === $s['links'][0]['url'], 'outside links take https only, with a label' );
ok( 2 === count( $s['shortcuts'] ) && 'https://site.test/wp-admin/admin.php?page=scout-booking&view=blocks' === $s['shortcuts'][1]['url'], 'shortcuts are screens inside wp-admin only' );
ob_start();
$V::render_links();
$html = ob_get_clean();
ok( false !== strpos( $html, 'Your other tools' ) && 2 === substr_count( $html, 'target="_blank" rel="noopener noreferrer"' ), 'the "Your other tools" card opens each link in a new tab' );
ok( false !== strpos( $html, '(opens in a new tab)' ), 'screen readers hear that the link opens a new tab' );
setup_file( array() );
ob_start();
$V::render_links();
ok( '' === ob_get_clean(), 'no links, no card' );

$rows = $V::shortcuts( array( array( 'url' => '/a', 'label' => 'Change a page', 'note' => '' ) ) );
ok( 1 === count( $rows ), 'without a setup file the hub\'s own shortcuts are unchanged' );
add_filter( 'scout_hub_home_shortcuts', function ( $rows ) { $rows[] = array( 'url' => '/b', 'label' => 'Ask for a review' ); $rows[] = array( 'label' => 'broken' ); return $rows; } );
$rows = $V::shortcuts( array() );
ok( 1 === count( $rows ) && '' === $rows[0]['note'], 'a plugin adds a shortcut with scout_hub_home_shortcuts; a row without a link is dropped' );

$printed = array();
add_filter(
	'scout_hub_home_cards',
	function ( $cards ) use ( &$printed ) {
		$cards[] = array( 'id' => 'late', 'priority' => 80, 'render' => function () use ( &$printed ) { $printed[] = 'late'; } );
		$cards[] = array( 'id' => 'week', 'priority' => 10, 'render' => function () use ( &$printed ) { $printed[] = 'week'; } );
		$cards[] = array( 'id' => 'ops', 'cap' => 'activate_plugins', 'render' => function () use ( &$printed ) { $printed[] = 'ops'; } );
		$cards[] = array( 'id' => 'broken', 'render' => 'no_such_function' );
		return $cards;
	}
);
$GLOBALS['me'] = 3;
$V::render_cards();
ok( array() === $printed, 'a person without the card\'s capability sees no card' );
$GLOBALS['me'] = 2;
$V::render_cards();
ok( array( 'week', 'ops', 'late' ) === $printed, 'Home cards print in priority order, and a card that cannot render is skipped' );

/* ---- Land on Home ---- */

$dash = 'https://site.test/wp-admin/';
$GLOBALS['opt']['scout_hub_page'] = 'site-build-features';
setup_file( array() );
ok( $dash === $V::login_redirect( $dash, '', $GLOBALS['users'][2] ), 'off by default: login still opens the WordPress Dashboard' );
setup_file( array( 'dashboard' => array( 'land_on_home' => true ) ) );
$home = 'https://site.test/wp-admin/admin.php?page=site-build-features';
ok( $home === $V::login_redirect( $dash, '', $GLOBALS['users'][2] ), 'on: an owner lands on Scout Home after login' );
ok( $home === $V::login_redirect( 'https://site.test/wp-admin/index.php', 'https://site.test/wp-admin/index.php', $GLOBALS['users'][2] ), 'a login that asked for the Dashboard itself lands on Home too' );
ok( 'https://site.test/wp-admin/edit.php' === $V::login_redirect( 'https://site.test/wp-admin/edit.php', 'https://site.test/wp-admin/edit.php', $GLOBALS['users'][2] ), 'a login link that asked for a particular screen still goes there' );
ok( $dash === $V::login_redirect( $dash, '', $GLOBALS['users'][1] ), 'operators keep the WordPress Dashboard' );
ok( $dash === $V::login_redirect( $dash, '', $GLOBALS['users'][3] ), 'someone who cannot open Home keeps the usual landing' );
ok( $dash === $V::login_redirect( $dash, '', new WP_Error() ), 'a failed login is left alone' );
setup_file( array( 'dashboard' => array( 'land_on_home' => 'yes' ) ) );
ok( ! $V::settings()['land_on_home'], 'only true turns landing on' );

/* ---- P11: Chat files under Scout tools with an outside widget on ---- */

$GLOBALS['opt']['scout_chat'] = array( 'enabled' => 1 );
ok( 'website' === Scout_Admin_UI::group( 'scout-chat', 'Chat' ), 'the Chat screen lives in Site features by default' );
$GLOBALS['opt']['scout_chat'] = array( 'enabled' => 1, 'outside' => 1, 'outside_src' => 'https://widget.example.com/chat.js' );
ok( 'build' === Scout_Admin_UI::group( 'scout-chat', 'Chat' ), 'with an outside widget on, the Chat screen files under Scout tools' );
ok( 'inbox' === Scout_Admin_UI::group( 'edit.php?post_type=scout_chat_q', 'Chat messages' ), 'Chat messages stay in To do' );
$lines = apply_filters( 'scout_admin_looking_for', array(), 'website' );
ok( 1 === count( $lines ) && 'scout-chat' === $lines[0][0] && true === $lines[0][2], 'Site features gets a "Looking for chat settings?" line for operators' );
$GLOBALS['opt']['scout_chat'] = array( 'enabled' => 1, 'outside' => 1, 'outside_src' => 'http://widget.example.com/chat.js' );
ok( 'website' === Scout_Admin_UI::group( 'scout-chat', 'Chat' ), 'an outside widget without a safe address keeps Chat in Site features' );

/* ---- Setup file fills ---- */

$GLOBALS['me']  = 2;
$GLOBALS['opt'] = array();
$types          = array(
	array( 'id' => 'deep', 'name' => 'Deep tissue', 'active' => 1 ),
	array( 'id' => 'reqone', 'name' => 'By request session', 'active' => 1, 'request' => 1 ),
	array( 'id' => 'asked', 'name' => 'Has questions', 'active' => 1, 'questions' => array( array( 'label' => 'Anything else?' ) ) ),
);
$GLOBALS['opt'][ Scout_Booking::TYPES ] = $types;
setup_file(
	array(
		'contact' => array(
			'sets' => array(
				array(
					'name'    => 'Bodywork pages',
					'pages'   => array( 'bodywork', 'missing-page', 7 ),
					'paths'   => array( '/Bodywork' ),
					'buttons' => array( array( 'type' => 'book', 'url' => 'https://outside-booking.example/' ), array( 'type' => 'text' ) ),
				),
				array( 'pages' => array( 'request' ), 'buttons' => array( array( 'type' => 'link', 'label' => 'Request', 'url' => 'https://site.test/request/' ) ), 'no_text' => true ),
				'not a set',
			),
		),
		'booking' => array(
			'decline_reasons' => array( 'Not a fit', "Time doesn't work", 'Not offered right now' ),
			'link_mode'       => 'url',
			'link_url'        => '/appointments/',
			'link_skip'       => array( 'By request session', 'nobody' ),
			'schema'          => true,
			'types'           => array(
				'By request session' => array(
					'schema'    => 'on',
					'questions' => array( array( 'label' => 'I have read what happens in a session and agree to it.', 'required' => true, 'kind' => 'agree', 'link' => '/what-happens/' ) ),
				),
				'asked'              => array( 'questions' => array( array( 'label' => 'Replaced?' ) ) ),
			),
		),
		'chat'    => array(
			'name'               => 'Studio chat',
			'outside'            => true,
			'outside_src'        => 'https://widget.example.com/chat.js',
			'outside_async'      => true,
			'outside_attrs'      => array( 'widget-id' => 'abc123', 'onload' => 'alert(1)' ),
			'outside_config_var' => 'chatSettings',
			'outside_config'     => array( 'color' => '#0A78C2' ),
			'outside_position'   => 'left',
			'outside_clearance'  => 96,
			'outside_selector'   => '#chat-launcher',
		),
	)
);
Scout_Hub_Setup::run();

$sets = $GLOBALS['opt']['scout_contact']['sets'] ?? array();
ok( 2 === count( $sets ), 'contact sets fill from the setup file; an entry that is not a set is skipped' );
ok( array( 11, 7 ) === ( $sets[0]['pages'] ?? null ), 'a set\'s pages take page paths or IDs; a path that names no page is left out' );
ok( array( '/bodywork/' ) === ( $sets[0]['paths'] ?? null ) && 'book' === ( $sets[0]['buttons'][0]['type'] ?? '' ) && 'https://outside-booking.example/' === ( $sets[0]['buttons'][0]['url'] ?? '' ), 'set paths and buttons go through Call Buttons\' own cleaning' );
ok( 'Button set 2' === ( $sets[1]['name'] ?? '' ) && 1 === ( $sets[1]['no_text'] ?? 0 ), 'a set without a name gets one, and no_text carries over' );

$b = Scout_Booking::settings();
ok( array( 'Not a fit', "Time doesn't work", 'Not offered right now' ) === $b['decline_reasons'], 'decline reasons replace the starting list' );
ok( 'url' === $b['link_mode'] && 'https://site.test/appointments/' === $b['link_url'], 'Book buttons across the site take the address from the file' );
ok( array( 'reqone' ) === $b['link_skip'], 'link_skip names session types by name; an unknown one is dropped' );
ok( true === $b['schema'], 'the Service schema switch turns on' );
$t = Scout_Booking::types();
ok( 'on' === $t['reqone']['schema'], 'a session type\'s schema choice fills while it is auto' );
$q = $t['reqone']['questions'][0] ?? array();
ok( ! empty( $q['agree'] ) && ! empty( $q['required'] ) && 'https://site.test/what-happens/' === ( $q['link'] ?? '' ), 'an agree question becomes the tick box to agree, with its link' );
ok( 'Anything else?' === $t['asked']['questions'][0]['label'] && 1 === count( $t['asked']['questions'] ), 'a session type that has questions keeps them' );

$c = $GLOBALS['opt']['scout_chat'];
ok( 'Studio chat' === $c['name'] && '1' === $c['outside'] && 'https://widget.example.com/chat.js' === $c['outside_src'], 'the outside chat widget turns on from the file with a safe address' );
$attr_lines = explode( "\n", $c['outside_attrs'] );
ok( in_array( 'data-widget-id=abc123', $attr_lines, true ) && ! preg_grep( '/^on/', $attr_lines ), 'script attributes go through Chat\'s own check (a bare name becomes data-, never an on... handler)' );
ok( '{"color":"#0A78C2"}' === $c['outside_config'] && 'chatSettings' === $c['outside_config_var'] && 'left' === $c['outside_position'] && '96' === $c['outside_clearance'] && '#chat-launcher' === $c['outside_selector'], 'settings object, corner, clearance and launcher selector fill' );

// What someone set in WordPress stays.
$GLOBALS['opt']['scout_contact']['sets'] = array( array( 'name' => 'Mine', 'pages' => array(), 'children' => 0, 'paths' => array( '/mine/' ), 'no_text' => 0, 'label' => '', 'buttons' => array() ) );
$GLOBALS['opt']['scout_booking']['decline_reasons'] = array( 'My own reason' );
$GLOBALS['opt']['scout_booking']['link_mode']       = 'page';
$GLOBALS['opt']['scout_booking']['link_url']        = '';
$GLOBALS['opt']['scout_chat']['outside']            = 0;
$GLOBALS['opt']['scout_chat']['outside_src']        = 'https://mine.example.com/x.js';
unset( $GLOBALS['opt'][ Scout_Hub_Setup::DONE ] );
Scout_Hub_Setup::run();
ok( 'Mine' === $GLOBALS['opt']['scout_contact']['sets'][0]['name'] && 1 === count( $GLOBALS['opt']['scout_contact']['sets'] ), 'sets made in WordPress are never replaced' );
ok( array( 'My own reason' ) === Scout_Booking::settings()['decline_reasons'], 'decline reasons someone edited stay' );
ok( 'https://mine.example.com/x.js' === $GLOBALS['opt']['scout_chat']['outside_src'], 'a script address someone typed stays' );

// A session type the file names, made later, fills once it exists.
$GLOBALS['opt'] = array( Scout_Booking::TYPES => array( $types[0] ) );
setup_file( array( 'booking' => array( 'types' => array( 'Later one' => array( 'questions' => array( array( 'label' => 'Ok?', 'kind' => 'agree' ) ) ) ) ) ) );
Scout_Hub_Setup::run();
$GLOBALS['opt'][ Scout_Booking::TYPES ][] = array( 'id' => 'later', 'name' => 'Later one', 'active' => 1 );
Scout_Hub_Setup::run();
ok( ! empty( Scout_Booking::types()['later']['questions'][0]['agree'] ), 'the booking section runs again when a session type it names appears' );

// Events: the Door helper role and members-only events (Scout Events stubbed).
final class Scout_Events {
	const OPTION = 'scout_events';
	const EVENT  = 'scout_event';
	static function empty_event() { return array( 'members_only' => 0 ); }
	static function sanitize_event( $in ) { return array( 'members_only' => empty( $in['members_only'] ) ? 0 : 1 ) + $in; }
}
final class Scout_Events_People { static function sync_role( $on ) { $GLOBALS['door'] = $on; } }
function get_posts( $q ) { return ( 'scout_event' === $q['post_type'] && 'members-night' === ( $q['name'] ?? '' ) ) ? array( 41 ) : array(); }
function get_post_meta( $id, $k, $s = false ) { return $GLOBALS['pmeta'][ $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ $id ][ $k ] = $v; }
$GLOBALS['opt'] = array();
setup_file( array( 'events' => array( 'door_role' => true, 'members_only' => array( 'members-night', 'no-such-event' ) ) ) );
Scout_Hub_Setup::run();
ok( 1 === ( $GLOBALS['opt']['scout_events']['door_role'] ?? 0 ) && true === ( $GLOBALS['door'] ?? null ), 'door_role turns the Door helper role on' );
ok( 1 === ( $GLOBALS['pmeta'][41]['_scout_event']['members_only'] ?? 0 ), 'members_only ticks the events the file names by slug' );

@unlink( $GLOBALS['theme'] . '/scout-setup.json' );
@rmdir( $GLOBALS['theme'] );
echo $fail ? "\n$fail FAILED\n" : "\nAll owner view checks passed\n";
exit( $fail ? 1 : 0 );
