<?php
// Scout Build: saved secrets (Email's app password, the theme key, the Hub keys) after the
// host rotates the WordPress security keys. They read as "can't be read", never "not set",
// Site status gets a fix row, Site history gets one line, and no secret or salt ever shows.
// Runs without WordPress: php tools/tests/secret-health.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'ENT_QUOTES_X', 1 );
$GLOBALS['salt']      = 'first-salt-' . str_repeat( 'k', 40 );
$GLOBALS['opts']      = array();
$GLOBALS['log']       = array();
$GLOBALS['logged_in'] = false;
$GLOBALS['operator']  = false;
$GLOBALS['theme_uri'] = 'https://github.com/Scout-Media-Raleigh/SaltTest';
function wp_salt( $scheme = 'auth' ) { return $GLOBALS['salt']; }
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function __( $s, $d = '' ) { return $s; }
function esc_html__( $s, $d = '' ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s, $p = null ) { return (string) $s; }
function apply_filters( $hook, $value ) { return $value; }
function add_action() {}
function add_filter() {}
function did_action( $h ) { return 1; }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function admin_url( $p = '' ) { return '/wp-admin/' . $p; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function get_bloginfo( $k ) { return 'Example Site'; }
function wp_specialchars_decode( $s, $q = 0 ) { return $s; }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $s ) ), '-' ); }
class WP_Theme {
	public function get( $k ) { return array( 'UpdateURI' => $GLOBALS['theme_uri'], 'Scout Release' => 'salt-test', 'Name' => 'Salt Test', 'Version' => '1.0.0' )[ $k ] ?? ''; }
	public function get_stylesheet() { return 'salt-test'; }
}
function wp_get_theme( $s = null ) { return new WP_Theme(); }
final class Scout_Hub_Log { public static function add( array $e ): int { $GLOBALS['log'][] = $e; return count( $GLOBALS['log'] ); } }
final class Scout_Hub_Admin { public static function url( $t = 'overview' ) { return '/hub/' . $t; } public static function branded() { return true; } }
final class Scout_Hub_Access { public static function is_operator() { return $GLOBALS['operator']; } }
require __DIR__ . '/../../scout-build/includes/class-scout-hub-secret.php';
require __DIR__ . '/../../scout-build/includes/class-scout-hub-mail.php';
require __DIR__ . '/../../scout-build/includes/class-scout-hub-themes.php';
require __DIR__ . '/../../scout-build/includes/class-scout-hub-log-api.php';
require __DIR__ . '/../../scout-build/includes/class-scout-hub-update-report.php';
$S = 'Scout_Hub_Secret';

$enc = static function ( string $class, string $plain ): string {
	$m = new ReflectionMethod( $class, 'encrypt' );
	$m->setAccessible( true );
	return $m->invoke( null, $plain );
};
$rotate = static function ( string $salt ) use ( $S ) {
	$GLOBALS['salt'] = $salt;
	$S::reset();
};
$mail_pw  = 'abcdefghijklmnop';
$theme_pw = 'github_pat_11SECRETSECRETSECRET_0123456789abcdef';

// Never set: today's rows, no fix, no history.
$row = Scout_Hub_Mail::check();
ok( 'warn' === $row['state'] && 'Host mail' === $row['value'], 'a site that never set up Email keeps its look row (Host mail)' );
ok( 'none' === Scout_Hub_Themes::key_state() && null === Scout_Hub_Themes::key_check(), 'a site that never saved a theme key gets no theme row' );
ok( '' === Scout_Hub_Log_API::keys_source(), 'no Hub keys reads as none' );
ok( array() === $GLOBALS['log'], 'nothing is written to Site history for a site that never set them' );

// The fingerprint.
$fp = $S::fingerprint();
ok( (bool) preg_match( '/^[0-9a-f]{8}$/', $fp ) && substr( hash( 'sha256', $GLOBALS['salt'] ), 0, 8 ) === $fp, 'the fingerprint is the first 8 hex of sha256 of the salt' );
ok( false === strpos( $GLOBALS['salt'], $fp ), 'the fingerprint is not a piece of the salt' );

// Saving stores the fingerprint next to each secret.
update_option( 'scout_mail', array( 'email' => 'sender@example.com', 'password' => $enc( 'Scout_Hub_Mail', $mail_pw ) ) );
$S::saved( 'mail' );
update_option( 'scout_hub_theme_token', $enc( 'Scout_Hub_Themes', $theme_pw ) );
$S::saved( 'theme' );
Scout_Hub_Log_API::make_keys();
$prints = get_option( $S::PRINTS );
ok( array( 'mail' => $fp, 'theme' => $fp, 'hub' => $fp ) === $prints, 'saving each secret stores the salt fingerprint for it' );
ok( Scout_Hub_Mail::ready() && ! Scout_Hub_Mail::unreadable(), 'with the same keys, Email reads and is ready' );
$row = Scout_Hub_Mail::check();
ok( 'Through Google' === $row['value'], 'with the same keys, the Site email row is as before' );
ok( 'saved' === Scout_Hub_Themes::key_state() && $theme_pw === Scout_Hub_Themes::token(), 'with the same keys, the theme key reads back' );
ok( 'site' === Scout_Hub_Log_API::keys_source(), 'with the same keys, the Hub keys read back' );
ok( 'saved' === Scout_Hub_Update_Report::key_word( Scout_Hub_Themes::key_state() ), 'the update report says the key is saved' );

// The host rotates the salts.
$rotate( 'second-salt-' . str_repeat( 'z', 40 ) );
ok( Scout_Hub_Mail::unreadable() && ! Scout_Hub_Mail::ready(), 'after the salts change, Email reads as can\'t be read, not ready' );
$row = Scout_Hub_Mail::check();
ok( 'bad' === $row['state'] && 'Site email' === $row['label'], 'the Site email row turns bad (a fix), so it reaches Today' );
ok( 0 === strpos( $row['note'], 'Your Google connection can\'t be read anymore.' ) && false !== strpos( $row['note'], 'security keys change' ), 'the row says plainly what happened: ' . $row['note'] );
ok( 0 === strpos( $row['link'], '/wp-admin/admin.php?page=scout-mail' ) && 'Connect again' === $row['link_text'], 'the row links to Email' );
ok( 'Connect again' === Scout_Hub_Mail::nav_hint( '', Scout_Hub_Mail::PAGE ), 'the Email menu hint says Connect again' );
ok( 'unreadable' === Scout_Hub_Themes::key_state() && '' === Scout_Hub_Themes::token(), 'the theme key reads as can\'t be read, never "not set"' );
$GLOBALS['logged_in'] = true;
ok( null === Scout_Hub_Themes::key_check(), 'no theme row for a signed-in client who cannot paste the key' );
$GLOBALS['logged_in'] = true;
$GLOBALS['operator']  = true;
$trow = Scout_Hub_Themes::key_check();
ok( is_array( $trow ) && 'bad' === $trow['state'] && 'Theme updates stopped: the saved key can\'t be read. Paste it again.' === $trow['note'], 'operators get a bad theme row: ' . ( $trow['note'] ?? '' ) );
ok( '/hub/updates#sh-theme' === ( $trow['link'] ?? '' ), 'the theme row links to the theme on Updates' );
$GLOBALS['logged_in'] = false;
ok( is_array( Scout_Hub_Themes::key_check() ), 'the Hub\'s nightly read (no one signed in) sees the theme row too' );
ok( 'unreadable' === Scout_Hub_Log_API::keys_source(), 'the Hub keys read as can\'t be read' );
ok( 'can\'t be read (paste it again)' === Scout_Hub_Update_Report::key_word( Scout_Hub_Themes::key_state() ), 'the update report says can\'t be read (paste it again)' );

// One history line, the first time.
Scout_Hub_Mail::check();
Scout_Hub_Themes::key_state();
$S::reset();
Scout_Hub_Mail::check();
ok( 1 === count( $GLOBALS['log'] ), 'one Site history line, however many times it is read: ' . count( $GLOBALS['log'] ) );
$line = $GLOBALS['log'][0] ?? array();
ok( 'Security keys changed: reconnect Email and paste the theme key' === ( $line['title'] ?? '' ), 'the line says what to do' );
ok( 'setup' === ( $line['kind'] ?? '' ) && false === ( $line['show_client'] ?? null ), 'the line is a setup line, for operators only' );
ok( $S::fingerprint() === get_option( $S::NOTICED ), 'the change is marked as noticed' );

// A mismatch is decided by the fingerprint alone: decrypt is never tried.
$tried = false;
$r     = $S::read( 'mail', 'e:' . base64_encode( str_repeat( 'x', 32 ) ), static function ( $v ) use ( &$tried ) { $tried = true; return 'plain'; } );
ok( 'unreadable' === $r['state'] && ! $tried, 'a fingerprint mismatch reads as can\'t be read without decrypting' );

// Reconnecting clears it.
update_option( 'scout_mail', array( 'email' => 'sender@example.com', 'password' => $enc( 'Scout_Hub_Mail', $mail_pw ) ) );
$S::saved( 'mail' );
ok( Scout_Hub_Mail::ready() && 'Through Google' === Scout_Hub_Mail::check()['value'], 'saving a new app password brings Email back' );
$S::forgot( 'theme' );
delete_option( 'scout_hub_theme_token' );
$S::reset();
ok( 'none' === Scout_Hub_Themes::key_state(), 'removing the key forgets its fingerprint' );

// A second rotation is a new change, so it gets its own line.
$rotate( 'third-salt-' . str_repeat( 'q', 40 ) );
Scout_Hub_Mail::check();
ok( 2 === count( $GLOBALS['log'] ), 'a later change of keys writes one more line' );

// Saved before fingerprints existed.
$GLOBALS['opts'][ $S::PRINTS ] = array();
update_option( 'scout_hub_theme_token', $enc( 'Scout_Hub_Themes', $theme_pw ) );
$S::reset();
ok( 'saved' === Scout_Hub_Themes::key_state() && $S::fingerprint() === ( get_option( $S::PRINTS )['theme'] ?? '' ), 'an older readable key is kept and gets its fingerprint on first read' );
$GLOBALS['opts'][ $S::PRINTS ] = array();
$rotate( 'fourth-salt-' . str_repeat( 'w', 40 ) );
$GLOBALS['opts'][ $S::NOTICED ] = '';
ok( 'unreadable' === Scout_Hub_Themes::key_state(), 'an older key with no fingerprint that will not decrypt reads as can\'t be read' );
ok( 3 === count( $GLOBALS['log'] ), 'and that is noted in Site history too' );
$S::reset();
ok( 'unreadable' === $S::read( 'theme', 'e:abc', static fn( $v ) => "\x01\xff\x10noise" )['state'], 'a wrong key that decrypts to noise reads as can\'t be read' );
ok( 'ok' === $S::read( 'theme', 'p:' . base64_encode( 'plain' ), static fn( $v ) => 'plain' )['state'], 'a value stored without openssl still reads' );

// Never the secret or the salt.
$everything = var_export( array( $GLOBALS['opts'][ $S::PRINTS ] ?? null, $GLOBALS['opts'][ $S::NOTICED ] ?? null, $GLOBALS['log'], Scout_Hub_Mail::check(), $trow ), true );
foreach ( array( 'first-salt', 'second-salt', 'third-salt', 'fourth-salt', $mail_pw, $theme_pw, 'SECRET' ) as $needle ) {
	ok( false === strpos( $everything, $needle ), 'nothing shown or logged contains ' . ( false !== strpos( $needle, 'salt' ) ? 'the salt (' . $needle . ')' : 'the secret' ) );
}
$report = Scout_Hub_Update_Report::text( array( 'theme' => array( 'name' => 'Salt Test', 'version' => '1.0.0', 'state' => 'updates stopped, the saved key can\'t be read', 'key' => Scout_Hub_Update_Report::key_word( 'unreadable' ) ) ) );
ok( false !== strpos( $report, "Theme key: can't be read (paste it again)" ) && false === strpos( $report, $theme_pw ), 'the update report names the state, never the key' );

// The test email tells the truth about how it went out.
$body = new ReflectionMethod( 'Scout_Hub_Mail', 'test_body' );
$body->setAccessible( true );
$google = $body->invoke( null, 'Example Site', true );
$host   = $body->invoke( null, 'Example Site', false );
ok( false !== strpos( $google, 'through Google, sent as sender@example.com' ), 'through Google, the test card says Google' );
ok( false !== strpos( $host, 'This came through your host&#039;s mail, not Google, because the Google connection isn&#039;t set up (or can&#039;t be read). Mail sent this way often lands in junk.' ), 'through the host, the test card says so' );
ok( false === strpos( $host, 'through Google' ), 'through the host, the card never claims Google' );
$n = Scout_Hub_Mail::sent_notice( 'joy@example.com', true );
ok( 'success' === $n['type'] && 0 === strpos( $n['message'], 'Sent to joy@example.com.' ), 'through Google, the notice is a success' );
$n = Scout_Hub_Mail::sent_notice( 'joy@example.com', false );
ok( 'warning' === $n['type'] && false !== strpos( $n['message'], 'through your host\'s mail, not Google' ), 'through the host, the notice is a warning that says so' );
ok( false !== strpos( Scout_Hub_Mail::sent_notice( 'a@b.co', false, true )['message'], 'Connect Google again' ), 'when the connection can\'t be read, the warning says to connect again' );

$all = $google . $host . var_export( $GLOBALS['log'], true ) . $report;
ok( false === strpos( $all, 'Claude' ) && false === strpos( $all, "\u{2014}" ), 'no Claude and no em dash in anything shown' );

echo $fail ? "\n$fail FAILED\n" : "\nAll secret health checks passed\n";
exit( $fail ? 1 : 0 );
