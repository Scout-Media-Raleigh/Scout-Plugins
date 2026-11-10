<?php
// Review totals and reviews from the theme's scout-setup.json ("reviews": "totals"
// and "items"), applied by Scout Build's Fill from theme through Scout Reviews.
// Totals: the newest check wins, a future or broken date is ignored, and the
// saved stamp moves to the checked date, not to now. Reviews: added once, never
// edited or removed. The section runs again when "checked" changes.
// Runs without WordPress:
//   php tools/tests/reviews-from-theme.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['opts']  = array();
$GLOBALS['posts'] = array(); // id => { title, content, status, type, meta }.
$GLOBALS['ready'] = false;   // Google connected and syncing.
$GLOBALS['theme'] = sys_get_temp_dir() . '/scout-reviews-theme-' . getmypid();
@mkdir( $GLOBALS['theme'] );

function __( $s ) { return $s; }
function apply_filters( $t, $v ) { return $v; }
function add_action() { return true; }
function do_action() {}
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_url_raw( $u, $p = null ) { return preg_match( '#^https?://#', (string) $u ) ? (string) $u : ''; }
function wp_slash( $v ) { return $v; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function current_user_can() { return true; }
function wp_doing_ajax() { return false; }
function get_stylesheet_directory() { return $GLOBALS['theme']; }
function get_template_directory() { return $GLOBALS['theme']; }
function trailingslashit( $p ) { return rtrim( $p, '/' ) . '/'; }
function wp_insert_post( $a ) {
	$id = count( $GLOBALS['posts'] ) + 100;
	$GLOBALS['posts'][ $id ] = array( 'title' => $a['post_title'], 'content' => $a['post_content'], 'status' => $a['post_status'], 'type' => $a['post_type'], 'meta' => array() );
	return $id;
}
function update_post_meta( $id, $k, $v ) { $GLOBALS['posts'][ $id ]['meta'][ $k ] = $v; return true; }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['posts'][ $id ]['meta'][ $k ] ?? ''; }
function get_post_field( $f, $id ) { return 'post_title' === $f ? ( $GLOBALS['posts'][ $id ]['title'] ?? '' ) : ''; }
function get_posts( $q ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $id => $p ) {
		if ( $p['type'] !== $q['post_type'] || ! in_array( $p['status'], (array) $q['post_status'], true ) ) {
			continue;
		}
		foreach ( (array) ( $q['meta_query'] ?? array() ) as $m ) {
			if ( ( $p['meta'][ $m['key'] ] ?? null ) !== $m['value'] ) {
				continue 2;
			}
		}
		$out[] = $id;
	}
	return $out;
}

final class Scout_Reviews_Query { public static $flushed = 0; public static function flush() { self::$flushed++; } }
final class Scout_Reviews_Google { public static function is_ready() { return $GLOBALS['ready']; } }
final class Scout_Reviews_Requests { const OPTION = 'scout_review_page'; public static function defaults() { return array( 'headline' => 'How was it?' ); } }

$root = dirname( __DIR__, 2 );
require $root . '/scout-reviews/includes/class-scout-reviews-sources.php';
require $root . '/scout-reviews/includes/class-scout-reviews-post-type.php';
require $root . '/scout-reviews/includes/class-scout-reviews-settings.php';
require $root . '/scout-reviews/includes/class-scout-reviews-theme.php';
require $root . '/scout-build/includes/class-scout-hub-setup.php';

const DAY = 86400;
function days_ago( $n ) { return gmdate( 'Y-m-d', time() - $n * DAY ); }
function midnight( $date ) { return ( new DateTimeImmutable( $date . ' 00:00:00', new DateTimeZone( 'UTC' ) ) )->getTimestamp(); }
function profile( $slug ) { return Scout_Reviews_Settings::get()['profiles'][ $slug ] ?? array(); }
function stamp() { return (int) get_option( Scout_Reviews_Settings::STAMP, 0 ); }
function job( array $section ) {
	$m = new ReflectionMethod( 'Scout_Hub_Setup', 'reviews' );
	$m->setAccessible( true );
	return $m->invoke( null, $section );
}
function theme_file( array $json ) { file_put_contents( $GLOBALS['theme'] . '/scout-setup.json', json_encode( $json ) ); }

// ---------- dates ----------
ok( Scout_Reviews_Theme::day( '2026-11-09' ) === midnight( '2026-11-09' ), 'a real date reads as midnight that day' );
ok( 0 === Scout_Reviews_Theme::day( '2026-02-30' ), 'February 30 is not a date' );
ok( 0 === Scout_Reviews_Theme::day( 'last week' ), 'words are not a date' );
ok( 0 === Scout_Reviews_Theme::day( '' ), 'blank is not a date' );
ok( Scout_Reviews_Theme::is_future( days_ago( -1 ) ) && ! Scout_Reviews_Theme::is_future( days_ago( 0 ) ), 'tomorrow is in the future, today is not' );

// ---------- totals: first fill ----------
// Yelp was typed in by hand earlier, with its review link; nothing has been checked since.
$GLOBALS['opts'][ Scout_Reviews_Settings::OPTION ] = array(
	'profiles' => array(
		'yelp'   => array( 'rating' => 4.0, 'count' => 4, 'url' => 'https://yelp.test/old', 'write_url' => 'https://yelp.test/write' ),
		'google' => array( 'rating' => 0, 'count' => 0, 'url' => '', 'write_url' => 'https://g.page/r/x/review' ),
	),
);
$checked = days_ago( 3 );
$changed = Scout_Reviews_Theme::apply_totals(
	array(
		'checked'  => $checked,
		'profiles' => array(
			'google'   => array( 'rating' => 4.9, 'count' => 52, 'url' => 'https://maps.test/place' ),
			'yelp'     => array( 'rating' => 4.5, 'count' => 6 ),
			'facebook' => array( 'rating' => 5, 'count' => 10 ),      // No stars on Facebook: the count only.
			'madeup'   => array( 'rating' => 5, 'count' => 99 ),      // Not a Scout Reviews platform.
			'direct'   => array( 'count' => 3 ),                      // Direct testimonials have no profile.
			'bbb'      => array( 'rating' => 9, 'count' => 0 ),       // Nothing usable.
		),
	)
);
sort( $changed );
ok( array( 'facebook', 'google', 'yelp' ) === $changed, 'newer totals apply to real platforms only: ' . implode( ', ', $changed ) );
ok( 4.9 === profile( 'google' )['rating'] && 52 === profile( 'google' )['count'], 'Google rating and count filled' );
ok( 'https://maps.test/place' === profile( 'google' )['url'], 'Google profile link filled' );
ok( 'https://g.page/r/x/review' === profile( 'google' )['write_url'], 'the Google review link stays' );
ok( 4.5 === profile( 'yelp' )['rating'] && 6 === profile( 'yelp' )['count'], 'Yelp totals replaced by the newer check' );
ok( 'https://yelp.test/old' === profile( 'yelp' )['url'] && 'https://yelp.test/write' === profile( 'yelp' )['write_url'], 'Yelp links stay when the theme leaves them out' );
ok( 0.0 === (float) profile( 'facebook' )['rating'] && 10 === profile( 'facebook' )['count'], 'Facebook takes the count, never stars' );
ok( ! profile( 'madeup' ) && ! profile( 'direct' ) && ! profile( 'bbb' ), 'unknown, direct and empty rows are skipped' );
ok( stamp() === midnight( $checked ), 'the stamp is the checked date, not now' );
ok( midnight( $checked ) === Scout_Reviews_Settings::checked_at( 'google' ), 'each platform remembers its own check' );
ok( 4 === count( array_filter( array( 'rating', 'count', 'url', 'write_url' ), fn( $k ) => array_key_exists( $k, Scout_Reviews_Settings::profile( 'google' ) ) ) ), 'Settings::profile() still reads the four fields' );

// ---------- totals: older, same day, future, broken ----------
$before = $GLOBALS['opts'][ Scout_Reviews_Settings::OPTION ];
ok( array() === Scout_Reviews_Theme::apply_totals( array( 'checked' => days_ago( 10 ), 'profiles' => array( 'google' => array( 'rating' => 3.1, 'count' => 1 ) ) ) ), 'an older check is ignored' );
ok( array() === Scout_Reviews_Theme::apply_totals( array( 'checked' => $checked, 'profiles' => array( 'google' => array( 'rating' => 3.1, 'count' => 1 ) ) ) ), 'the same check again is ignored' );
ok( array() === Scout_Reviews_Theme::apply_totals( array( 'checked' => days_ago( -2 ), 'profiles' => array( 'google' => array( 'rating' => 3.1, 'count' => 1 ) ) ) ), 'a future date is ignored' );
ok( array() === Scout_Reviews_Theme::apply_totals( array( 'checked' => '2026-13-01', 'profiles' => array( 'google' => array( 'rating' => 3.1, 'count' => 1 ) ) ) ), 'an invalid date is ignored' );
ok( array() === Scout_Reviews_Theme::apply_totals( array( 'profiles' => array( 'google' => array( 'rating' => 3.1, 'count' => 1 ) ) ) ), 'no date is ignored' );
ok( $before === $GLOBALS['opts'][ Scout_Reviews_Settings::OPTION ] && stamp() === midnight( $checked ), 'nothing changed after the ignored checks' );

// ---------- a hand-typed save on Review settings is newer ----------
// The form rebuilds every row, stamps each one and the whole set with now (an hour ago here).
$form = $GLOBALS['opts'][ Scout_Reviews_Settings::OPTION ];
foreach ( $form['profiles'] as $slug => $row ) {
	$form['profiles'][ $slug ]['checked'] = time() - 3600;
}
$form['profiles']['google']['count'] = 53;
$GLOBALS['opts'][ Scout_Reviews_Settings::OPTION ] = $form;
$GLOBALS['opts'][ Scout_Reviews_Settings::STAMP ]  = time() - 3600;
ok( array() === Scout_Reviews_Theme::apply_totals( array( 'checked' => days_ago( 1 ), 'profiles' => array( 'google' => array( 'rating' => 4.8, 'count' => 50 ) ) ) ), 'a hand-typed save that is newer stays' );
ok( 53 === profile( 'google' )['count'], 'the typed count is untouched' );

// A later theme check (today) beats that save from an hour ago only if it is newer: midnight today is older.
ok( array() === Scout_Reviews_Theme::apply_totals( array( 'checked' => days_ago( 0 ), 'profiles' => array( 'google' => array( 'count' => 50 ) ) ) ), 'a check dated today loses to a save made later today' );

// A week later Scout checks again: the theme wins, and the stamp never goes backwards.
foreach ( $GLOBALS['opts'][ Scout_Reviews_Settings::OPTION ]['profiles'] as $slug => $row ) {
	$GLOBALS['opts'][ Scout_Reviews_Settings::OPTION ]['profiles'][ $slug ]['checked'] = time() - 8 * DAY;
}
$GLOBALS['opts'][ Scout_Reviews_Settings::STAMP ] = time() - 8 * DAY;
$later = days_ago( 1 );
ok( array( 'google' ) === Scout_Reviews_Theme::apply_totals( array( 'checked' => $later, 'profiles' => array( 'google' => array( 'rating' => 4.9, 'count' => 55 ) ) ) ), 'a newer theme check beats an older save' );
ok( 55 === profile( 'google' )['count'] && stamp() === midnight( $later ), 'count updated and stamp moved to the checked date' );
// Then a daily Google check refreshes only the Google row and the whole-set stamp.
$GLOBALS['opts'][ Scout_Reviews_Settings::OPTION ]['profiles']['google']['checked'] = time();
$GLOBALS['opts'][ Scout_Reviews_Settings::STAMP ] = time();
Scout_Reviews_Theme::apply_totals( array( 'checked' => days_ago( 0 ), 'profiles' => array( 'yelp' => array( 'count' => 7 ) ) ) );
ok( 7 === profile( 'yelp' )['count'], 'Yelp, last checked a week ago, takes today\'s check' );
ok( stamp() >= midnight( days_ago( 0 ) ) && stamp() > time() - 60, 'the stamp never moves backwards' );

// Rows saved before per-row check times fall back to the whole-set stamp.
unset( $GLOBALS['opts'][ Scout_Reviews_Settings::OPTION ]['profiles']['clutch'] );
$GLOBALS['opts'][ Scout_Reviews_Settings::OPTION ]['profiles']['clutch'] = array( 'rating' => 5, 'count' => 2, 'url' => '', 'write_url' => '' );
ok( stamp() === Scout_Reviews_Settings::checked_at( 'clutch' ), 'an old row without its own check time uses the saved stamp' );

// ---------- Google syncing: its numbers win ----------
$GLOBALS['ready'] = true;
$GLOBALS['opts'][ Scout_Reviews_Settings::OPTION ]['profiles']['google']['checked'] = time() - 8 * DAY;
ok( array() === Scout_Reviews_Theme::apply_totals( array( 'checked' => days_ago( 0 ), 'profiles' => array( 'google' => array( 'count' => 1 ) ) ) ), 'while Google syncs, the theme leaves the Google row alone' );
ok( 55 === profile( 'google' )['count'], 'Google count untouched while syncing' );
$GLOBALS['ready'] = false;

// ---------- reviews ----------
$items = array(
	array( 'source' => 'google', 'author' => 'Pat R.', 'rating' => 5, 'date' => '2026-10-02', 'text' => 'Great cut.', 'url' => 'https://maps.test/r1' ),
	array( 'source' => 'yelp', 'author' => 'Sam K.', 'rating' => 4, 'date' => '2026-09-15', 'text' => 'Good visit.', 'url' => '' ),
	array( 'source' => 'google', 'author' => '', 'rating' => 5, 'date' => '2026-10-03', 'text' => 'No name.' ),   // Skipped.
	array( 'source' => 'google', 'author' => 'Stars Only', 'rating' => 5, 'date' => '2026-10-04', 'text' => '' ), // Skipped.
	array( 'source' => 'myspace', 'author' => 'Lee', 'rating' => 5, 'date' => '2026-10-05', 'text' => 'Hi.' ),   // Skipped.
	array( 'source' => 'direct', 'author' => 'Jo B.', 'rating' => 9, 'date' => 'soon', 'text' => 'Thank you.' ),
);
ok( 3 === Scout_Reviews_Theme::add_items( $items ), 'three usable reviews added' );
$first = $GLOBALS['posts'][100];
ok( 'Pat R.' === $first['title'] && 'Great cut.' === $first['content'] && 'publish' === $first['status'], 'a review is a published scout_review post with the reviewer and text' );
ok( 'google' === $first['meta']['_scout_review_source'] && 5 === $first['meta']['_scout_review_rating'] && '2026-10-02' === $first['meta']['_scout_review_date'] && 'https://maps.test/r1' === $first['meta']['_scout_review_url'], 'source, stars, date and link saved' );
ok( 1 === $first['meta'][ Scout_Reviews_Theme::META_FROM_THEME ] && ! isset( $first['meta']['_scout_review_external_id'] ), 'marked as from the theme, never as a Google sync review' );
ok( 5 === $GLOBALS['posts'][102]['meta']['_scout_review_rating'] && '' === $GLOBALS['posts'][102]['meta']['_scout_review_date'], 'stars capped at 5 and a broken date left blank' );
ok( 0 === Scout_Reviews_Theme::add_items( $items ), 'the same reviews are not added twice' );

// The client edited one and trashed another: neither is touched or brought back.
$GLOBALS['posts'][100]['content'] = 'Great cut (edited).';
$GLOBALS['posts'][100]['status']  = 'draft';
$GLOBALS['posts'][101]['status']  = 'trash';
$snapshot = $GLOBALS['posts'];
ok( 0 === Scout_Reviews_Theme::add_items( $items ), 'a hidden or trashed review is not added again' );
ok( $snapshot === $GLOBALS['posts'], 'existing reviews are untouched' );

$more   = $items;
$more[] = array( 'source' => 'google', 'author' => 'Pat R.', 'rating' => 4, 'date' => '2026-11-01', 'text' => 'Second visit.' );
ok( 1 === Scout_Reviews_Theme::add_items( $more ), 'a second review by the same person on another day is added' );

$GLOBALS['ready'] = true;
ok( 0 === Scout_Reviews_Theme::add_items( array( array( 'source' => 'google', 'author' => 'New', 'date' => '2026-11-02', 'text' => 'Hi.' ) ) ), 'while Google syncs, Google reviews come from the sync' );
$GLOBALS['ready'] = false;

// ---------- the Scout Build job ----------
$GLOBALS['opts'] = array();
$GLOBALS['posts'] = array();
$section = array(
	'headline'          => 'How did we do?',
	// Look keys an older theme may still carry. Build 0.15.0 leaves them alone: the look is DESIGN.md's.
	'background'        => '#FBF9F6',
	'text_color'        => '#23212A',
	'accent'            => '#7A5C2C',
	'heading_font'      => 'Lora',
	'body_font'         => 'Mulish',
	'label_font'        => 'Mulish',
	'google_review_url' => 'https://g.page/r/abc/review',
	'totals'            => array( 'checked' => days_ago( 2 ), 'profiles' => array( 'google' => array( 'rating' => 4.7, 'count' => 20 ) ) ),
	'items'             => array( array( 'source' => 'google', 'author' => 'Ann', 'rating' => 5, 'date' => '2026-10-10', 'text' => 'Lovely.' ) ),
);
ok( true === job( $section ), 'the reviews job runs' );
ok( 'How did we do?' === ( $GLOBALS['opts']['scout_review_page']['headline'] ?? '' ), 'the review page words still fill' );
$look = array_intersect_key( (array) ( $GLOBALS['opts']['scout_review_page'] ?? array() ), array_flip( array( 'background', 'text_color', 'accent', 'heading_font', 'body_font', 'label_font' ) ) );
ok( array() === $look, 'the look keys are no longer filled from scout-setup.json (DESIGN.md sets them)' );
ok( array( 'reviews.background', 'reviews.text_color', 'reviews.accent', 'reviews.heading_font', 'reviews.body_font', 'reviews.label_font', 'cards.mode', 'cards.font' ) === Scout_Hub_Setup::look_keys( array( 'reviews' => $section, 'cards' => array( 'mode' => 'dark', 'font' => 'Inter', 'accent' => '', 'name' => 'x' ) ) ), 'look_keys() names the look keys a setup file still carries, skipping blanks' );
ok( '' === Scout_Hub_Setup::look_notice( array( 'reviews' => array( 'headline' => 'x' ) ) ), 'no look keys: no notice' );
ok( false !== strpos( Scout_Hub_Setup::look_notice( array( 'cards' => array( 'accent' => '#000000' ) ) ), 'cards.accent' ), 'the notice names the keys' );

// A value someone saved before stays: Build stops writing, it never clears.
$GLOBALS['opts']['scout_review_page']['accent'] = '#123456';
job( array( 'accent' => '#7A5C2C', 'headline' => 'Other' ) );
ok( '#123456' === $GLOBALS['opts']['scout_review_page']['accent'], 'an accent already saved on the site is kept' );
unset( $GLOBALS['opts']['scout_review_page']['accent'] );
ok( 'https://g.page/r/abc/review' === profile( 'google' )['write_url'], 'the Google review link still fills' );
ok( 20 === profile( 'google' )['count'] && 4.7 === profile( 'google' )['rating'], 'the job applies the totals' );
ok( 1 === count( $GLOBALS['posts'] ), 'the job adds the reviews' );

// ---------- reruns ----------
theme_file( array( 'reviews' => $section ) );
Scout_Hub_Setup::run();
$hash = $GLOBALS['opts']['scout_hub_setup_done']['reviews'] ?? '';
ok( '' !== $hash, 'Fill from theme records the reviews section' );
Scout_Hub_Setup::run();
ok( $hash === $GLOBALS['opts']['scout_hub_setup_done']['reviews'], 'unchanged file: no rerun' );

$section['totals']['checked']                       = days_ago( 1 );
$section['totals']['profiles']['google']['count'] = 22;
theme_file( array( 'reviews' => $section ) );
Scout_Hub_Setup::run();
ok( $hash !== $GLOBALS['opts']['scout_hub_setup_done']['reviews'], 'a new checked date reruns the section' );
ok( 22 === profile( 'google' )['count'] && stamp() === midnight( days_ago( 1 ) ), 'and the new totals apply' );

// A future date waits; the section runs again once the hash text changes.
$section['totals']['checked']                       = days_ago( -1 );
$section['totals']['profiles']['google']['count'] = 30;
theme_file( array( 'reviews' => $section ) );
Scout_Hub_Setup::run();
$waiting = $GLOBALS['opts']['scout_hub_setup_done']['reviews'];
ok( 22 === profile( 'google' )['count'], 'a future checked date does not apply' );
$m = new ReflectionMethod( 'Scout_Hub_Setup', 'reviews_hash_extra' );
$m->setAccessible( true );
ok( false !== strpos( $m->invoke( null, $section ), 'totals-waiting' ), 'a future date marks the section as waiting' );
$section['totals']['checked'] = days_ago( 0 );
ok( false === strpos( $m->invoke( null, $section ), 'totals-waiting' ), 'once the day comes the mark drops, so the hash changes and it runs' );
ok( '|defaults-blank' === $m->invoke( null, array( 'headline' => 'x' ) ), 'a section without totals hashes as before (no rerun on update)' );

@unlink( $GLOBALS['theme'] . '/scout-setup.json' );
@rmdir( $GLOBALS['theme'] );

echo $fail ? "\n$fail failed\n" : "\nAll passed\n";
exit( $fail ? 1 : 0 );
