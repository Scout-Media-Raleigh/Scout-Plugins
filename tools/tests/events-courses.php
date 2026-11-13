<?php
$fail = 0;
function ok( $c, $m ) { global $fail; echo ( $c ? 'PASS ' : 'FAIL ' ) . $m . "\n"; if ( ! $c ) $fail++; }
define( 'ABSPATH', '/' ); define( 'SCOUT_EVENTS_VERSION', 't' ); define( 'SCOUT_EVENTS_URL', '/' );
$GLOBALS['meta'] = array(); $GLOBALS['posts'] = array(); $GLOBALS['opts'] = array(); $GLOBALS['filters'] = array();
function __( $s ) { return $s; } function _x( $s ) { return $s; } function esc_html__( $s ) { return $s; } function _n( $a, $b, $n ) { return 1 == $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s ); } function esc_attr( $s ) { return htmlspecialchars( (string) $s ); } function esc_url( $s ) { return (string) $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); } function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', (string) $s ) ); }
function esc_url_raw( $s ) { return preg_match( '#^https?://#', $s ) ? $s : ''; } function absint( $n ) { return abs( (int) $n ); }
function wp_parse_args( $a, $d ) { return array_merge( $d, $a ); } function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function apply_filters( $h, $v ) { return isset( $GLOBALS['filters'][ $h ] ) ? $GLOBALS['filters'][ $h ]( ...array_slice( func_get_args(), 1 ) ) : $v; }
function add_action() {} function add_filter() {} function add_shortcode() {}
function wp_timezone() { return new DateTimeZone( 'America/New_York' ); }
function wp_date( $f, $t ) { return ( new DateTime( '@' . $t ) )->setTimezone( wp_timezone() )->format( $f ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( $n, $d ); }
function get_post_meta( $id, $k, $s = false ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ $id ][ $k ] = $v; }
function get_post_type( $id ) { return $GLOBALS['posts'][ $id ]['type'] ?? false; } function get_post_status( $id ) { return $GLOBALS['posts'][ $id ]['status'] ?? false; }
function get_permalink( $id ) { return 'https://example.test/' . ( $GLOBALS['posts'][ $id ]['slug'] ?? $id ) . '/'; }
function get_the_title( $id ) { return $GLOBALS['posts'][ $id ]['title'] ?? ''; } function get_post_field( $f, $id ) { return 'post_name' === $f ? ( $GLOBALS['posts'][ $id ]['slug'] ?? '' ) : ( $GLOBALS['posts'][ $id ]['content'] ?? '' ); }
function has_excerpt() { return false; } function wp_trim_words( $s ) { return $s; } function wp_strip_all_tags( $s ) { return strip_tags( $s ); } function has_post_thumbnail() { return false; }
function home_url( $p = '' ) { return 'https://example.test' . $p; } function get_bloginfo() { return 'Example School'; } function is_admin() { return false; } function wp_doing_ajax() { return false; }
function get_posts() { return array(); }
function wp_list_pluck( $a, $k ) { return array_map( fn( $r ) => $r[ $k ], $a ); }
require dirname( __DIR__, 2 ) . '/scout-events/includes/class-scout-events.php';
require dirname( __DIR__, 2 ) . '/scout-events/includes/class-scout-events-schema.php';
require dirname( __DIR__, 2 ) . '/scout-events/includes/class-scout-events-courses.php';
$GLOBALS['filters']['scout_events_now'] = fn() => strtotime( '2026-11-13 12:00' );
$E = 'Scout_Events';
$GLOBALS['posts'][1] = array( 'type' => 'scout_event', 'status' => 'publish', 'title' => 'Open night', 'slug' => 'open-night' );
$full = array( 'start_date' => '2027-01-16', 'start_time' => '19:00', 'venue' => 'Hall', 'street' => '1 Main St', 'city' => 'Raleigh', 'region' => 'NC', 'postal' => '27601', 'availability' => 'onsale', 'price_from' => '20' );
$E::save_event( 1, $full );
ok( '' === $E::google_skip( 1 ) && Scout_Events_Schema::event( 1 ), 'a public in-person event with a full address gets Event schema' );
$E::save_event( 1, array( 'members_only' => '1' ) + $full );
ok( 'members' === $E::google_skip( 1 ) && ! Scout_Events_Schema::event( 1 ), 'members-only tick: no Event schema' );
$E::save_event( 1, array( 'members_only' => 'members' ) + $full );
ok( 1 === $E::event( 1 )['members_only'], 'a members cell from events.csv turns it on' );
$E::save_event( 1, array( 'street' => '' ) + $full );
ok( 'address' === $E::google_skip( 1 ) && ! Scout_Events_Schema::event( 1 ), 'no street address: no Event schema' );
$E::save_event( 1, array( 'address_private' => 'yes' ) + $full );
ok( 'private' === $E::google_skip( 1 ) && ! Scout_Events_Schema::event( 1 ), 'private address: no Event schema' );
$E::save_event( 1, array( 'mode' => 'online', 'venue' => '', 'street' => '', 'online_url' => 'https://meet.test/x' ) + $full );
ok( '' === $E::google_skip( 1 ), 'an online event needs no street address' );
ok( 0 === $E::sanitize_event( array() )['members_only'], 'members_only is off by default' );

// A course with two runs, one linked to an event, tiers, a private address.
$E::save_event( 1, $full );
$GLOBALS['posts'][5] = array( 'type' => 'scout_course', 'status' => 'publish', 'title' => 'Level one training', 'slug' => 'level-one', 'content' => 'Four weekends of practice.' );
$GLOBALS['posts'][9] = array( 'type' => 'page', 'status' => 'publish', 'title' => 'Trainings', 'slug' => 'trainings' );
$C = 'Scout_Events_Courses';
$C::save( 5, array(
	'level' => 'Beginner', 'prereq' => 'None needed.', 'mode' => 'onsite', 'hours' => '52',
	'venue' => 'Studio', 'street' => '9 Oak St', 'city' => 'Raleigh', 'region' => 'NC', 'postal' => '27603',
	'instances' => array(
		array( 'label' => 'Spring 2027', 'start_date' => '2027-03-05', 'start_time' => '18:30', 'end_date' => '2027-06-06', 'end_time' => '17:00' ),
		array( 'label' => 'Past', 'start_date' => '2026-01-05', 'end_date' => '2026-02-01' ),
		array( 'label' => '', 'start_date' => '', 'events' => array( 1 ) ),
		array( 'label' => '', 'start_date' => '' ),
	),
	'tiers' => array(
		array( 'name' => 'Early price', 'price' => '$850', 'until' => '2027-01-01', 'availability' => 'onsale' ),
		array( 'name' => 'Gone', 'price' => '500', 'until' => '2026-01-01' ),
		array( 'name' => 'Full price', 'price' => '1500' ),
		array( 'name' => '', 'price' => '' ),
	),
	'register_url' => 'https://booking.example/x', 'teacher_names' => "A. Teacher\n", 'page' => 9,
) );
$c = $C::course( 5 );
ok( 3 === count( $c['instances'] ) && 3 === count( $c['tiers'] ), 'empty rows are dropped on save' );
$n = $C::schema( 5, 9 );
ok( 'Course' === $n['@type'] && 'https://example.test/trainings/#course-level-one' === $n['@id'] && array( '@type' => 'Organization', 'name' => 'Example School', 'url' => 'https://example.test/' ) === $n['provider'], 'Course node with provider and stable id' );
ok( 'Beginner' === $n['educationalLevel'] && 'None needed.' === $n['coursePrerequisites'], 'level and prerequisites' );
ok( 2 === count( $n['offers'] ) && 850.0 === $n['offers'][0]['price'] && 'Paid' === $n['offers'][0]['category'] && '2027-01-01' === $n['offers'][0]['priceValidUntil'] && 'https://booking.example/x' === $n['offers'][0]['url'], 'current tiers become Offers; the ended one is left out' );
ok( 2 === count( $n['hasCourseInstance'] ), 'past run left out, two upcoming runs' );
$i0 = $n['hasCourseInstance'][0];
ok( 'Onsite' === $i0['courseMode'] && '2027-01-16T19:00:00-05:00' === $i0['startDate'] && 'https://example.test/open-night/#event' === $i0['subEvent'][0]['@id'], 'a run from a linked event takes its dates and lists it as a session' );
$i1 = $n['hasCourseInstance'][1];
ok( '2027-03-05T18:30:00-05:00' === $i1['startDate'] && '2027-06-06T17:00:00-04:00' === $i1['endDate'] && 'PT52H' === $i1['courseWorkload'] && '9 Oak St' === $i1['location']['address']['streetAddress'] && 'A. Teacher' === $i1['instructor']['name'], 'own dates, workload, public place and instructor' );
$C::save( 5, array( 'address_private' => 1 ) + $C::course( 5 ) );
$n = $C::schema( 5, 9 );
ok( ! isset( $n['hasCourseInstance'][0]['location'] ) && ! isset( $n['hasCourseInstance'][1]['location'] ) && false === strpos( json_encode( $n ), 'Oak' ), 'private course address: no location and the street never prints' );
$html = $C::panel( 5 );
ok( false === strpos( $html, 'Oak' ) && false !== strpos( $html, 'Raleigh' ) && false !== strpos( $html, 'booking.example' ) && false === strpos( $html, 'Gone' ), 'panel: no private street, register button, ended price hidden' );
$C::save( 5, array( 'venue' => '', 'street' => '', 'city' => '', 'address_private' => 0 ) + $C::course( 5 ) );
$n = $C::schema( 5, 9 );
ok( '1 Main St' === $n['hasCourseInstance'][0]['location']['address']['streetAddress'] && ! isset( $n['hasCourseInstance'][1]['location'] ), 'no venue of its own: a run uses its linked event place when public' );
$E::save_event( 1, array( 'address_private' => 'yes' ) + $full );
$n = $C::schema( 5, 9 );
ok( ! isset( $n['hasCourseInstance'][0]['location'] ) && false === strpos( json_encode( $n ), 'Main St' ), 'a linked event with a private address gives no location' );
$C::save( 5, array( 'schema' => '0' ) + $C::course( 5 ) );
ok( array() === $C::schema( 5, 9 ), 'per-course Google switch off: no node' );
ok( 1 === $C::sanitize( array() )['schema'], 'per-course Google switch defaults on' );
exit( $fail ? 1 : 0 );
