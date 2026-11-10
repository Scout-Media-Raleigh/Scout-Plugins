<?php
// Site Sync "noindex" (Scout Core 1.11.0): a page entry can tick or clear Hide from
// search in the Scout SEO box. Runs inside a disposable WordPress with Scout Core active:
//   wp eval-file tools/tests/sync-noindex.php
// It creates and deletes its own pages; never point it at a real site.
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }

wp_set_current_user( 1 );
$manifest = tempnam( sys_get_temp_dir(), 'sync' );
add_filter( 'scout_sync_manifest_path', static function () use ( $manifest ) { return $manifest; } );
foreach ( array( Scout_Sync::BASES, Scout_Sync::JOURNAL, Scout_Sync::LOCK, Scout_Sync::SEEN ) as $o ) {
	delete_option( $o );
}
function sync_with( $file, array $data ) {
	file_put_contents( $file, wp_json_encode( $data ) );
	delete_transient( Scout_Sync::SUMMARY );
	return Scout_Sync::apply( '', 'safe', array(), 'auto' );
}
function states() {
	$o = array();
	foreach ( Scout_Sync::plan()['rows'] as $r ) {
		$o[ $r['id'] ] = $r['state'];
	}
	return $o;
}

$guide = wp_insert_post( array( 'post_type' => 'page', 'post_name' => 'style-guide', 'post_title' => 'Style guide', 'post_status' => 'publish' ) );
$faq   = wp_insert_post( array( 'post_type' => 'page', 'post_name' => 'faq-test', 'post_title' => 'FAQ', 'post_status' => 'publish' ) );
$about = wp_insert_post( array( 'post_type' => 'page', 'post_name' => 'about-test', 'post_title' => 'About', 'post_status' => 'publish' ) );

$data = array(
	'sync_version' => 1,
	'pages'        => array(
		array( 'slug' => 'style-guide', 'noindex' => true ),
		array( 'slug' => 'about-test', 'seo_title' => 'About | Test' ),
	),
	'fields'       => array(
		array( 'slug' => 'faq-test', 'seo' => 'noindex', 'value' => 'yes' ),
	),
);
file_put_contents( $manifest, wp_json_encode( $data ) );
$s = states();
ok( 'update' === ( $s['page/style-guide#seo:noindex'] ?? '' ), 'a page entry with "noindex": true is a safe update on an empty box' );
ok( ! isset( $s['page/about-test#seo:noindex'] ), 'a page entry without noindex leaves the box out of the sync' );

$r = sync_with( $manifest, $data );
ok( ! is_wp_error( $r ), 'the sync ran' );
ok( '1' === get_post_meta( $guide, SCOUT_SEO_META_NOINDEX, true ), 'Hide from search is ticked on the style guide' );
ok( '1' === get_post_meta( $faq, SCOUT_SEO_META_NOINDEX, true ), 'the fields form ({"seo": "noindex", "value": "yes"}) ticks it too' );
ok( '' === get_post_meta( $about, SCOUT_SEO_META_NOINDEX, true ), 'a page the manifest does not mark stays indexed' );
ok( 'current' === ( states()['page/style-guide#seo:noindex'] ?? '' ), 'the row reads current afterwards' );

// The repo changes its mind: false clears the tick it set.
$data['pages'][0]['noindex'] = false;
$r = sync_with( $manifest, $data );
ok( ! is_wp_error( $r ) && '' === get_post_meta( $guide, SCOUT_SEO_META_NOINDEX, true ), '"noindex": false clears a tick the sync set' );

// A tick someone set by hand is never cleared without review.
update_post_meta( $about, SCOUT_SEO_META_NOINDEX, '1' );
$data['pages'][1]['noindex'] = false;
file_put_contents( $manifest, wp_json_encode( $data ) );
ok( 'conflict' === ( states()['page/about-test#seo:noindex'] ?? '' ), 'a hand tick the sync never saw is a conflict, not a safe update' );
sync_with( $manifest, $data );
ok( '1' === get_post_meta( $about, SCOUT_SEO_META_NOINDEX, true ), 'the automatic run leaves the hand tick alone' );

foreach ( array( $guide, $faq, $about ) as $id ) {
	wp_delete_post( $id, true );
}
foreach ( array( Scout_Sync::BASES, Scout_Sync::JOURNAL, Scout_Sync::LOCK, Scout_Sync::SEEN ) as $o ) {
	delete_option( $o );
}
unlink( $manifest );
echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n";
if ( $fail ) {
	exit( 1 );
}
