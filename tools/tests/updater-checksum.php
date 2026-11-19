<?php
// The plugin updater checks each download (Scout Core 1.15.1): a release zip
// whose sha256 is in the version list is downloaded and compared, and a
// mismatch stops the install; a release with no sha256 installs as before;
// an update of a Scout plugin from any other address is refused; other
// plugins, themes and hand uploads are untouched. No request leaves the machine.
// Runs without WordPress:
//   php tools/tests/updater-checksum.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
class WP_Error {
	private $code;
	public function __construct( $code = '', $msg = '' ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function add_filter() {}
function add_action() {}
function do_action() {}
function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
function wp_delete_file( $f ) { @unlink( $f ); }
function get_transient( $k ) { return $GLOBALS['t'][ $k ] ?? false; }
function set_transient( $k, $v ) { $GLOBALS['t'][ $k ] = $v; }
$GLOBALS['zip'] = tempnam( sys_get_temp_dir(), 'scoutzip' );
file_put_contents( $GLOBALS['zip'], 'the real zip' );
function download_url( $url ) { $c = tempnam( sys_get_temp_dir(), 'dl' ); copy( $GLOBALS['zip'], $c ); $GLOBALS['downloads'][] = $url; return $c; }
ini_set( 'error_log', '/dev/null' );

require dirname( __DIR__, 2 ) . '/scout-core/includes/class-scout-plugin-updater.php';

$base = 'https://github.com/Scout-Media-Raleigh/Scout-Plugins/releases/download/';
$good = hash( 'sha256', 'the real zip' );
$GLOBALS['t']['scout_updater_feed'] = array(
	'ok'       => true,
	'releases' => array(
		array( 'tag_name' => 'scout-chat-v0.4.1', 'assets' => array( array( 'name' => 'scout-chat.zip', 'browser_download_url' => $base . 'scout-chat-v0.4.1/scout-chat.zip', 'sha256' => $good ) ) ),
		array( 'tag_name' => 'scout-aeo-v0.2.15', 'assets' => array( array( 'name' => 'scout-aeo.zip', 'browser_download_url' => $base . 'scout-aeo-v0.2.15/scout-aeo.zip', 'sha256' => str_repeat( 'a', 64 ) ) ) ),
		array( 'tag_name' => 'scout-hours-v0.1.16', 'assets' => array( array( 'name' => 'scout-hours.zip', 'browser_download_url' => $base . 'scout-hours-v0.1.16/scout-hours.zip', 'sha256' => '' ) ) ),
	),
);
Scout_Plugin_Updater::boot( '/wp/plugins/scout-chat/scout-chat.php', 'scout-chat' );
$U = 'Scout_Plugin_Updater';

$r = $U::pre_download( false, $base . 'scout-chat-v0.4.1/scout-chat.zip', null, array( 'plugin' => 'scout-chat/scout-chat.php' ) );
ok( is_string( $r ) && file_exists( $r ), 'matching sha256: the checked file is handed to WordPress' );

$r = $U::pre_download( false, $base . 'scout-aeo-v0.2.15/scout-aeo.zip', null, array() );
ok( is_wp_error( $r ) && 'scout_updater_checksum' === $r->get_error_code(), 'wrong sha256: install stopped' );

$n = count( $GLOBALS['downloads'] );
$r = $U::pre_download( false, $base . 'scout-hours-v0.1.16/scout-hours.zip', null, array() );
ok( false === $r && count( $GLOBALS['downloads'] ) === $n, 'no sha256 listed: WordPress downloads as before' );

$r = $U::pre_download( false, 'https://example.com/scout-chat.zip', null, array( 'plugin' => 'scout-chat/scout-chat.php' ) );
ok( is_wp_error( $r ) && 'scout_updater_source' === $r->get_error_code(), 'a Scout plugin from another address: refused' );

ok( false === $U::pre_download( false, 'https://example.com/other.zip', null, array( 'plugin' => 'akismet/akismet.php' ) ), 'another plugin: untouched' );
ok( false === $U::pre_download( false, '/tmp/upload.zip', null, array( 'plugin' => 'scout-chat/scout-chat.php' ) ), 'a zip uploaded by hand: untouched' );
ok( 'kept' === $U::pre_download( 'kept', $base . 'scout-chat-v0.4.1/scout-chat.zip' ), 'an earlier filter answer passes through' );
ok( ! $U::is_release_url( $base . '../evil/x.zip' ) && ! $U::is_release_url( 'https://github.com/Other/Repo/releases/download/x.zip' ), 'only this repo\'s release addresses count' );
ok( $good === $U::expected_sha256( $base . 'scout-chat-v0.4.1/scout-chat.zip' ), 'sha256 read from the version list' );

echo $fail ? "$fail failed\n" : "All updater checks passed\n";
exit( $fail ? 1 : 0 );
