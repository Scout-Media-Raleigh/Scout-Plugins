<?php
// The daily Google check (Scout Reviews 0.7.0): with a Google API key and a place
// ID, the site asks the Places API (New) for the rating and review count. The
// key is stored encrypted and never shown; a good answer fills the Google row and
// stamps the totals now; a failure changes nothing and keeps a plain-words error
// without the key; three days of failures make Site status ask for a look.
// The HTTP call is stubbed: no request leaves the machine.
// Runs without WordPress:
//   php tools/tests/reviews-places.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['opts']     = array();
$GLOBALS['cron']     = array();
$GLOBALS['requests'] = array();
$GLOBALS['answer']   = null; // What the stubbed Google sends back.
$GLOBALS['ready']    = false;
$GLOBALS['salt']     = 'salt-one';

class WP_Error {
	private $code; private $msg;
	public function __construct( $code = '', $msg = '' ) { $this->code = $code; $this->msg = $msg; }
	public function get_error_message() { return $this->msg; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function __( $s, $d = '' ) { return $s; }
function _n( $a, $b, $n, $d = '' ) { return 1 === $n ? $a : $b; }
function apply_filters( $t, $v ) { return $v; }
function add_action() { return true; }
function admin_url( $p = '' ) { return 'https://site.test/wp-admin/' . $p; }
function wp_salt( $s = 'auth' ) { return $GLOBALS['salt']; }
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_next_scheduled( $h ) { return $GLOBALS['cron'][ $h ] ?? false; }
function wp_schedule_event( $t, $r, $h ) { $GLOBALS['cron'][ $h ] = $t; return true; }
function wp_clear_scheduled_hook( $h ) { unset( $GLOBALS['cron'][ $h ] ); return true; }
function wp_remote_get( $url, $args ) {
	$GLOBALS['requests'][] = array( 'url' => $url, 'args' => $args );
	return $GLOBALS['answer'];
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? $r['response']['code'] : ''; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? $r['body'] : ''; }
function answer( $code, $body ) { $GLOBALS['answer'] = array( 'response' => array( 'code' => $code ), 'body' => is_array( $body ) ? json_encode( $body ) : $body ); }

final class Scout_Reviews_Query { public static function flush() {} }
final class Scout_Reviews_Google { public static function is_ready() { return $GLOBALS['ready']; } }

$root = dirname( __DIR__, 2 );
require $root . '/scout-reviews/includes/class-scout-reviews-sources.php';
require $root . '/scout-reviews/includes/class-scout-reviews-settings.php';
require $root . '/scout-reviews/includes/class-scout-reviews-places.php';
require $root . '/scout-build/includes/class-scout-hub-health.php';

$KEY   = 'AIzaSyTESTtestTEST1234567890abcdefghi';
$PLACE = 'ChIJN1t_tDeuEmsRUsoyG83frY4';
$all   = static fn() => json_encode( $GLOBALS['opts'] );

// ---------- off until both are set ----------
ok( 'none' === Scout_Reviews_Places::key_state() && ! Scout_Reviews_Places::is_on(), 'nothing set: off' );
ok( is_wp_error( Scout_Reviews_Places::run() ) && ! $GLOBALS['requests'], 'off: no request to Google' );
ok( '' === Scout_Reviews_Places::clean_place_id( 'not a place' ) && $PLACE === Scout_Reviews_Places::clean_place_id( 'places/' . $PLACE ), 'place IDs are checked, and a places/ prefix comes off' );
ok( ! Scout_Reviews_Places::looks_like_key( 'short' ) && Scout_Reviews_Places::looks_like_key( $KEY ), 'API key shape is checked' );

// ---------- the key is stored encrypted ----------
Scout_Reviews_Places::save_key( $KEY );
ok( 'saved' === Scout_Reviews_Places::key_state(), 'saved key reads as saved' );
ok( false === strpos( $all(), $KEY ), 'the key is nowhere in the options as plain text' );
ok( 0 === strpos( (string) get_option( Scout_Reviews_Places::KEY_OPTION ), 'e:' ), 'stored encrypted' );
ok( false === strpos( json_encode( Scout_Reviews_Places::get() ), 'e:' ) && false === strpos( json_encode( Scout_Reviews_Places::status() ), $KEY ), 'state and status never carry the key' );
ok( ! Scout_Reviews_Places::is_on() && empty( $GLOBALS['cron'] ), 'a key alone is not enough: no daily check yet' );

// ---------- the theme fills the place ID (blank only) ----------
ok( true === Scout_Reviews_Places::fill_place_id( $PLACE ), 'theme fills a blank place ID' );
ok( false === Scout_Reviews_Places::fill_place_id( 'ChIJsomethingElse12345' ) && $PLACE === Scout_Reviews_Places::place_id(), 'theme never replaces a place ID already set' );
ok( Scout_Reviews_Places::is_on() && isset( $GLOBALS['cron'][ Scout_Reviews_Places::CRON ] ), 'both set: on, and the daily check is scheduled' );

// ---------- a good answer ----------
$GLOBALS['opts'][ Scout_Reviews_Settings::OPTION ] = array( 'profiles' => array( 'google' => array( 'rating' => 4.5, 'count' => 40, 'url' => 'https://maps.test/p', 'write_url' => 'https://g.page/r/x/review' ), 'yelp' => array( 'rating' => 4.0, 'count' => 4, 'url' => '', 'write_url' => '', 'checked' => time() - 20 * DAY_IN_SECONDS ) ) );
answer( 200, array( 'rating' => 4.86, 'userRatingCount' => 52 ) );
$before = time();
$r      = Scout_Reviews_Places::run();
$req    = end( $GLOBALS['requests'] );
ok( 'https://places.googleapis.com/v1/places/' . $PLACE === $req['url'], 'asks the Places API (New) for the place' );
ok( $KEY === $req['args']['headers']['X-Goog-Api-Key'] && 'rating,userRatingCount' === $req['args']['headers']['X-Goog-FieldMask'] && 10 === $req['args']['timeout'], 'key and field mask in the headers, timeout 10' );
ok( is_array( $r ) && 4.9 === $r['rating'] && 52 === $r['count'], 'reads rating 4.9 and 52 reviews' );
$g = Scout_Reviews_Settings::get()['profiles']['google'];
ok( 4.9 === $g['rating'] && 52 === $g['count'], 'the Google row on Review settings is updated' );
ok( 'https://maps.test/p' === $g['url'] && 'https://g.page/r/x/review' === $g['write_url'], 'its links stay' );
ok( (int) get_option( Scout_Reviews_Settings::STAMP ) >= $before, 'the totals are stamped as checked now' );
ok( 4 === Scout_Reviews_Settings::get()['profiles']['yelp']['count'] && Scout_Reviews_Settings::checked_at( 'yelp' ) < $before - 19 * DAY_IN_SECONDS, 'Yelp keeps its own numbers and its own check date' );
$s = Scout_Reviews_Places::status();
ok( $s['on'] && $s['working'] && '' === $s['error'] && 0 === $s['failing_days'], 'status: on and working' );

// Site status says so.
$row = Scout_Hub_Health::review_totals();
ok( 'good' === $row['state'] && 'Google is checked every day.' === $row['note'], 'Site status: "Google is checked every day."' );

// A place with no reviews yet sends neither field.
answer( 200, array() );
$r = Scout_Reviews_Places::run();
ok( is_array( $r ) && 0 === $r['count'] && 0 === $r['rating'], 'no reviews yet: zero, not an error' );
answer( 200, array( 'rating' => 4.86, 'userRatingCount' => 52 ) );
Scout_Reviews_Places::run();

// ---------- failures ----------
$saved = Scout_Reviews_Settings::get()['profiles']['google'];
$stamp = get_option( Scout_Reviews_Settings::STAMP );
$cases = array(
	array( new WP_Error( 'http_request_failed', 'cURL error 28: timed out' ), 'could not be reached' ),
	array( array( 'response' => array( 'code' => 400 ), 'body' => '{"error":{"message":"API key not valid. Key ' . $KEY . '"}}' ), 'did not accept' ),
	array( array( 'response' => array( 'code' => 403 ), 'body' => '{}' ), 'turned the API key down' ),
	array( array( 'response' => array( 'code' => 404 ), 'body' => '{}' ), 'no place with that place ID' ),
	array( array( 'response' => array( 'code' => 429 ), 'body' => '{}' ), 'limit' ),
	array( array( 'response' => array( 'code' => 500 ), 'body' => '{}' ), '(500)' ),
	array( array( 'response' => array( 'code' => 200 ), 'body' => 'not json' ), 'could not be read' ),
);
foreach ( $cases as $c ) {
	$GLOBALS['answer'] = $c[0];
	$r                 = Scout_Reviews_Places::run();
	$err               = (string) Scout_Reviews_Places::get()['last_error'];
	ok( is_wp_error( $r ) && false !== strpos( $err, $c[1] ), 'failure kept in plain words: ' . $err );
	ok( false === strpos( $err, $KEY ) && false === strpos( $err, 'cURL' ), '  and the error never carries the key or raw details' );
}
ok( $saved === Scout_Reviews_Settings::get()['profiles']['google'] && $stamp === get_option( Scout_Reviews_Settings::STAMP ), 'failures change nothing on Review settings' );
$s = Scout_Reviews_Places::status();
ok( ! $s['working'] && 0 === $s['failing_days'], 'failing for less than a day: not working, no look yet' );
$row = Scout_Hub_Health::review_totals();
ok( 'good' === $row['state'] && 'Google check failing' !== $row['value'], 'Site status waits before raising it' );

// Three days of failures.
$o                                         = Scout_Reviews_Places::get();
$o['failing_since']                        = time() - 3 * DAY_IN_SECONDS - 60;
$GLOBALS['opts'][ Scout_Reviews_Places::OPTION ] = $o;
$row = Scout_Hub_Health::review_totals();
ok( 'warn' === $row['state'] && 'Google check failing' === $row['value'], 'three days failing: a look in Site status' );
ok( false !== strpos( $row['note'], 'could not be read' ) && false !== strpos( $row['link'], 'page=scout-reviews-settings' ), 'with the reason and a link to Review settings' );
ok( false === strpos( json_encode( $row ), $KEY ), 'Site status never shows the key' );

// A good answer clears it.
answer( 200, array( 'rating' => 4.9, 'userRatingCount' => 53 ) );
Scout_Reviews_Places::run();
$s = Scout_Reviews_Places::status();
ok( $s['working'] && 0 === $s['failing_days'] && 0 === Scout_Reviews_Places::get()['failing_since'], 'the next good check clears the failure' );

// ---------- the site's security keys change ----------
$GLOBALS['salt'] = 'salt-two';
ok( 'unreadable' === Scout_Reviews_Places::key_state(), 'after the security keys change, the key reads as can\'t be read' );
$n = count( $GLOBALS['requests'] );
$r = Scout_Reviews_Places::run();
ok( is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'Paste the key again' ) && count( $GLOBALS['requests'] ) === $n, 'no request with a broken key, and the error says to paste it again' );
Scout_Reviews_Places::save_key( $KEY );
ok( 'saved' === Scout_Reviews_Places::key_state() && '' === Scout_Reviews_Places::get()['last_error'], 'pasting it again fixes it' );

// ---------- a key in wp-config.php ----------
define( 'SCOUT_REVIEWS_PLACES_KEY', 'AIzaSyCONFIGconfig1234567890abcdefghi' );
ok( 'config' === Scout_Reviews_Places::key_state(), 'wp-config.php key wins' );
answer( 200, array( 'rating' => 5, 'userRatingCount' => 3 ) );
Scout_Reviews_Places::run();
$req = end( $GLOBALS['requests'] );
ok( 'AIzaSyCONFIGconfig1234567890abcdefghi' === $req['args']['headers']['X-Goog-Api-Key'], 'and is the one sent' );

// ---------- the Business Profile sign-in owns Google ----------
$GLOBALS['ready'] = true;
$n                = count( $GLOBALS['requests'] );
ok( ! Scout_Reviews_Places::is_on() && is_wp_error( Scout_Reviews_Places::run() ) && count( $GLOBALS['requests'] ) === $n, 'while Google is connected by sign-in, this check stands down' );
Scout_Reviews_Places::keep_scheduled();
ok( empty( $GLOBALS['cron'][ Scout_Reviews_Places::CRON ] ), 'and its daily check is unscheduled' );
$GLOBALS['ready'] = false;

// ---------- removing the key ----------
Scout_Reviews_Places::keep_scheduled();
Scout_Reviews_Places::forget_key();
ok( null === get_option( Scout_Reviews_Places::KEY_OPTION, null ), 'removing the key deletes it' );

echo $fail ? "\n$fail failed\n" : "\nAll passed\n";
exit( $fail ? 1 : 0 );
