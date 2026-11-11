<?php
// Scout Build 0.16.0: the Outside plugins and WPMU DEV rows in Site status (Site Standard
// rules 8 and 34). Sorts plugins into Scout, Gravity Forms, WPMU DEV and outside, reads
// approved_plugins from scout-setup.json, and checks the rows. No WordPress needed:
//   php tools/tests/outside-plugins.php
define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }

$GLOBALS['plugins'] = array();
$GLOBALS['active']  = array();
$GLOBALS['setup']   = array();
$GLOBALS['wp_version'] = '6.8';
function __( $s, $d = '' ) { return $s; }
function _n( $a, $b, $n, $d = '' ) { return 1 === $n ? $a : $b; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_get_environment_type() { return 'production'; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function get_option( $k, $d = false ) { return 'blog_public' === $k ? '1' : $d; }
function get_transient( $k ) { return false; }
function get_site_transient( $k ) { return false; }
function apply_filters( $h, $v ) { return $v; }
function untrailingslashit( $s ) { return rtrim( $s, '/\\' ); }
function get_plugins() { return $GLOBALS['plugins']; }
function is_plugin_active( $f ) { return in_array( $f, $GLOBALS['active'], true ); }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
$GLOBALS['theme_dir'] = __DIR__ . '/no-such-theme';
function get_stylesheet_directory() { return $GLOBALS['theme_dir']; }
function get_template_directory() { return $GLOBALS['theme_dir']; }
final class Scout_Hub_WP_Health { public static function row() { return array( 'label' => 'WordPress checks', 'value' => 'Fine', 'state' => 'good', 'note' => '' ); } }
require dirname( __DIR__, 2 ) . '/scout-build/includes/class-scout-hub-health.php';
require dirname( __DIR__, 2 ) . '/scout-build/includes/class-scout-hub-setup.php';
require dirname( __DIR__, 2 ) . '/scout-build/includes/class-scout-hub-plugins.php';
$P = 'Scout_Hub_Plugins';

function add( $file, array $data, $active = true ) {
	$GLOBALS['plugins'][ $file ] = $data + array( 'Name' => $file, 'Version' => '1.0.0' );
	if ( $active ) {
		$GLOBALS['active'][] = $file;
	}
}
function row( array $rows, string $label ) {
	foreach ( $rows as $r ) {
		if ( $label === $r['label'] ) {
			return $r;
		}
	}
	return null;
}

// 1. Sorting.
$scout   = array( 'Name' => 'Scout Core', 'Author' => 'Scout Media & Consulting', 'UpdateURI' => 'https://github.com/Scout-Media-Raleigh/Scout-Plugins/scout-core' );
$old     = array( 'Name' => 'Scout Reviews', 'UpdateURI' => 'https://github.com/joyrmac/Scout-Plugins/scout-reviews' );
$partner = array( 'Name' => 'Example companion', 'Author' => 'Scout Media & Consulting', 'AuthorURI' => 'https://scoutraleigh.com' );
$by_uri  = array( 'Name' => 'Example tool', 'AuthorURI' => 'https://www.scoutraleigh.com/' );
ok( 'scout' === $P::classify( 'scout-core/scout-core.php', $scout ), 'a plugin that updates from Scout-Plugins is a Scout plugin' );
ok( 'scout' === $P::classify( 'scout-reviews/scout-reviews.php', $old ), 'the old repo owner still counts as Scout' );
ok( 'scout' === $P::classify( 'scout-example/scout-example.php', $partner ), 'a client companion plugin by Scout Media counts as Scout' );
ok( 'scout' === $P::classify( 'tool/tool.php', $by_uri ), 'an author address on scoutraleigh.com counts as Scout' );

$gf      = array( 'Name' => 'Gravity Forms', 'Author' => 'Gravity Forms', 'AuthorURI' => 'https://gravityforms.com', 'PluginURI' => 'https://gravityforms.com' );
$gf_mc   = array( 'Name' => 'Gravity Forms Mailchimp Add-On', 'Author' => 'Gravity Forms', 'AuthorURI' => 'https://gravityforms.com' );
$gf_bare = array( 'Name' => 'Gravity Forms' );
$gf_3rd  = array( 'Name' => 'Gravity Forms Extra Fields', 'Author' => 'Someone Else', 'AuthorURI' => 'https://example.org' );
ok( 'gravity' === $P::classify( 'gravityforms/gravityforms.php', $gf ), 'Gravity Forms is Gravity Forms' );
ok( 'gravity' === $P::classify( 'gravityformsmailchimp/mailchimp.php', $gf_mc ), 'an official Gravity Forms add-on counts as Gravity Forms' );
ok( 'gravity' === $P::classify( 'gravityforms/gravityforms.php', $gf_bare ), 'the gravityforms folder alone is enough' );
ok( 'outside' === $P::classify( 'gf-extra-fields/gf-extra-fields.php', $gf_3rd ), 'another company\'s Gravity Forms add-on is an outside plugin' );

$wpmu = array(
	'wpmudev-updates/update-notifications.php' => array( 'Name' => 'WPMU DEV Dashboard', 'Author' => 'WPMU DEV', 'AuthorURI' => 'https://wpmudev.com' ),
	'wp-defender/wp-defender.php'              => array( 'Name' => 'Defender Pro' ),
	'defender-security/wp-defender.php'        => array( 'Name' => 'Defender' ),
	'wp-smushit/wp-smush.php'                  => array( 'Name' => 'Smush' ),
	'wp-smush-pro/wp-smush.php'                => array( 'Name' => 'Smush Pro' ),
	'hummingbird-performance/wp-hummingbird.php' => array( 'Name' => 'Hummingbird' ),
	'wp-hummingbird/wp-hummingbird.php'        => array( 'Name' => 'Hummingbird Pro' ),
	'branda-white-labeling/ultimate-branding.php' => array( 'Name' => 'Branda' ),
	'ultimate-branding/ultimate-branding.php'  => array( 'Name' => 'Branda Pro' ),
	'smartcrawl-seo/wpmu-dev-seo.php'          => array( 'Name' => 'SmartCrawl' ),
	'wpmu-dev-seo/wpmu-dev-seo.php'            => array( 'Name' => 'SmartCrawl Pro' ),
	'forminator/forminator.php'                => array( 'Name' => 'Forminator' ),
	'wordpress-popup/popover.php'              => array( 'Name' => 'Hustle' ),
	'beehive-analytics/beehive-analytics.php'  => array( 'Name' => 'Beehive' ),
	'snapshot/snapshot.php'                    => array( 'Name' => 'Snapshot Pro' ),
	'shipper/shipper.php'                      => array( 'Name' => 'Shipper Pro' ),
	'renamed-folder/x.php'                     => array( 'Name' => 'Something', 'Author' => 'WPMU DEV' ),
	'other-folder/y.php'                       => array( 'Name' => 'Something else', 'Author' => 'Incsub' ),
	'third-folder/z.php'                       => array( 'Name' => 'Third', 'PluginURI' => 'https://wpmudev.com/project/third/' ),
	'fourth-folder/z.php'                      => array( 'Name' => 'Fourth', 'AuthorURI' => 'https://premium.wpmudev.org/' ),
);
foreach ( $wpmu as $file => $data ) {
	ok( 'wpmudev' === $P::classify( $file, $data ), 'WPMU DEV: ' . $data['Name'] . ' (' . $file . ')' );
}
ok( 'outside' === $P::classify( 'wp-rocket/wp-rocket.php', array( 'Name' => 'WP Rocket', 'Author' => 'WP Media', 'AuthorURI' => 'https://wp-media.me' ) ), 'an ordinary plugin is an outside plugin' );
ok( 'outside' === $P::classify( 'hello.php', array( 'Name' => 'Hello Dolly' ) ), 'a single-file plugin is an outside plugin' );
ok( 'hello' === $P::slug( 'hello.php' ) && 'wp-rocket' === $P::slug( 'wp-rocket/wp-rocket.php' ), 'the folder name is the slug (the file name for single-file plugins)' );

// 2. The approved list from scout-setup.json.
$approved = Scout_Hub_Setup::approved_plugins(
	array(
		'approved_plugins' => array(
			array( 'plugin' => 'WP-Rocket', 'job' => 'Page caching', 'owner' => 'Scout', 'decided' => '2026-11-05' ),
			'hello',
			array( 'job' => 'no plugin named' ),
			array( 'plugin' => array( 'not', 'a', 'name' ) ),
			'',
		),
	)
);
ok( array( 'wp-rocket', 'hello' ) === array_keys( $approved ), 'approved_plugins takes folder names and objects, lowercased, and skips entries with no plugin' );
ok( 'Page caching' === $approved['wp-rocket']['job'] && '2026-11-05' === $approved['wp-rocket']['decided'] && 'Scout' === $approved['wp-rocket']['owner'], 'an object keeps its job, owner and date' );
ok( array() === Scout_Hub_Setup::approved_plugins( array() ), 'no approved_plugins means an empty list' );
ok( array( 'wp-rocket' ) === array_keys( Scout_Hub_Setup::approved_plugins( array( 'approved_plugins' => 'wp-rocket' ) ) ), 'a single name instead of a list still counts' );
ok( array() === Scout_Hub_Setup::approved_plugins(), 'a theme with no setup file approves nothing' );

// 3. The rows.
$only_ours = array(
	'scout-core/scout-core.php'     => $scout,
	'gravityforms/gravityforms.php' => $gf,
	'gravityformsmailchimp/mc.php'  => $gf_mc,
);
$rows = $P::rows( $only_ours, array_keys( $only_ours ), array() );
ok( 1 === count( $rows ), 'Scout plugins and Gravity Forms alone give one row' );
ok( 'Outside plugins' === $rows[0]['label'] && 'None' === $rows[0]['value'] && 'good' === $rows[0]['state'], 'it reads None and is all set' );

$mixed = $only_ours + array(
	'wp-rocket/wp-rocket.php' => array( 'Name' => 'WP Rocket' ),
	'hello.php'               => array( 'Name' => 'Hello Dolly' ),
	'akismet/akismet.php'     => array( 'Name' => 'Akismet' ),
	'off-plugin/off.php'      => array( 'Name' => 'Turned off' ),
);
$on   = array_diff( array_keys( $mixed ), array( 'off-plugin/off.php' ) );
$rows = $P::rows( $mixed, $on, $approved );
$r    = row( $rows, 'Outside plugins' );
ok( 'warn' === $r['state'] && '1 needs a decision' === $r['value'], 'one unapproved plugin: a look, "1 needs a decision"' );
ok( in_array( 'Akismet: needs a decision', $r['items'], true ) && in_array( 'WP Rocket: approved', $r['items'], true ) && in_array( 'Hello Dolly: approved', $r['items'], true ), 'Show which names each plugin with needs a decision or approved' );
ok( ! preg_grep( '/Turned off/', $r['items'] ), 'an inactive plugin is not listed' );
ok( false !== strpos( $r['details'], 'Needs a decision: Akismet.' ) && false !== strpos( $r['details'], 'approved_plugins' ), 'the copied report says which plugin and how to approve it' );
ok( 'https://example.test/wp-admin/plugins.php' === $r['link'], 'the row links to Plugins' );
ok( null === row( $rows, 'WPMU DEV' ), 'no WPMU DEV row when none is on' );

$two  = $P::rows( $mixed + array( 'jetpack/jetpack.php' => array( 'Name' => 'Jetpack' ) ), array_merge( $on, array( 'jetpack/jetpack.php' ) ), array() );
ok( '4 need a decision' === row( $two, 'Outside plugins' )['value'], 'with no approved list every outside plugin needs a decision' );

$all_ok = $P::rows( $mixed, $on, $approved + array( 'akismet' => array( 'job' => '', 'owner' => '', 'decided' => '' ) ) );
$r      = row( $all_ok, 'Outside plugins' );
ok( 'good' === $r['state'] && '3 approved' === $r['value'] && ! isset( $r['link'] ), 'every outside plugin approved: all set, "3 approved"' );

$with_wpmu = $mixed + array(
	'wpmudev-updates/update-notifications.php' => $wpmu['wpmudev-updates/update-notifications.php'],
	'wp-smushit/wp-smush.php'                  => $wpmu['wp-smushit/wp-smush.php'],
	'forminator/forminator.php'                => $wpmu['forminator/forminator.php'],
);
$rows = $P::rows( $with_wpmu, array_merge( $on, array( 'wpmudev-updates/update-notifications.php', 'wp-smushit/wp-smush.php' ) ), $approved + array( 'wp-smushit' => array( 'job' => '', 'owner' => '', 'decided' => '' ) ) );
$w    = row( $rows, 'WPMU DEV' );
ok( null !== $w && 'bad' === $w['state'] && '2 plugins on' === $w['value'], 'active WPMU DEV plugins give a red row with the count' );
ok( array( 'WPMU DEV Dashboard', 'Smush' ) === $w['items'], 'it names each active WPMU DEV plugin, and not one that is off (Forminator)' );
ok( false !== strpos( $w['note'], 'Nov 2, 2026' ), 'the note says when WPMU DEV was retired' );
ok( '1 needs a decision' === row( $rows, 'Outside plugins' )['value'], 'WPMU DEV plugins are not counted again as outside plugins' );
ok( 'WPMU DEV' === $rows[1]['label'] && 'Outside plugins' === $rows[0]['label'], 'Outside plugins comes first, then WPMU DEV' );

// 4. The site itself: checks() reads WordPress's list and the theme, and Site status carries both rows.
$GLOBALS['plugins'] = array();
$GLOBALS['active']  = array();
add( 'scout-build/scout-build.php', array( 'Name' => 'Scout Build', 'Author' => 'Scout Media & Consulting', 'UpdateURI' => 'https://github.com/Scout-Media-Raleigh/Scout-Plugins/scout-build' ) );
add( 'gravityforms/gravityforms.php', $gf );
add( 'wp-rocket/wp-rocket.php', array( 'Name' => 'WP Rocket' ) );
add( 'wp-defender/wp-defender.php', array( 'Name' => 'Defender Pro', 'Author' => 'WPMU DEV' ) );
$rows = $P::checks();
ok( '1 needs a decision' === row( $rows, 'Outside plugins' )['value'] && 'bad' === row( $rows, 'WPMU DEV' )['state'], 'checks() reads the active plugins (no setup file: nothing approved)' );
$theme = sys_get_temp_dir() . '/scout-outside-plugins-' . getmypid();
@mkdir( $theme );
file_put_contents( $theme . '/scout-setup.json', json_encode( array( 'approved_plugins' => array( array( 'plugin' => 'wp-rocket', 'job' => 'Page caching', 'owner' => 'Scout', 'decided' => '2026-11-05' ), 'wp-defender' ) ) ) );
$GLOBALS['theme_dir'] = $theme;
$rows = $P::checks();
unlink( $theme . '/scout-setup.json' );
rmdir( $theme );
ok( '1 approved' === row( $rows, 'Outside plugins' )['value'], 'the theme\'s scout-setup.json approves WP Rocket' );
ok( 'bad' === row( $rows, 'WPMU DEV' )['state'], 'approving a WPMU DEV plugin in the setup file does not clear its red row' );
$health = Scout_Hub_Health::checks();
ok( null !== row( $health, 'Outside plugins' ) && null !== row( $health, 'WPMU DEV' ), 'Site status (Scout_Hub_Health::checks) carries both rows' );
$labels = array_column( $health, 'label' );
ok( array_search( 'Outside plugins', $labels, true ) === array_search( 'Test builds', $labels, true ) + 1, 'they sit right after Test builds' );
ok( array( 'Outside plugins', 'WPMU DEV' ) === $P::labels(), 'labels() lists both, so Site status files them under Plugins' );
$admin = (string) file_get_contents( dirname( __DIR__, 2 ) . '/scout-build/includes/class-scout-hub-admin.php' );
ok( false !== strpos( $admin, 'Scout_Hub_Plugins::labels()' ), 'the Site status screen files the rows under Plugins' );
$main = (string) file_get_contents( dirname( __DIR__, 2 ) . '/scout-build/scout-build.php' );
ok( false !== strpos( $main, "includes/class-scout-hub-plugins.php'" ), 'scout-build.php loads the class' );

// 5. Plain words: no em dashes in what a person reads.
$text = '';
foreach ( array_merge( $rows, $all_ok ) as $r ) {
	$text .= $r['value'] . $r['note'] . ( $r['details'] ?? '' ) . implode( '', $r['items'] ?? array() );
}
ok( false === strpos( $text, "\u{2014}" ), 'no em dashes in the rows' );

echo $fail ? "\n$fail FAILED\n" : "\nAll passed.\n";
exit( $fail ? 1 : 0 );
