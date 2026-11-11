<?php
// Site Sync array meta (Scout Core 1.12.0): a `fields` entry whose value is a list or object
// is compared as canonical JSON and written as a real array, so a theme can seed Scout AI
// Search's `_scout_aeo_faqs` rows. Before this, the list was stored and compared as "Array"
// (a client staging site, 2026-11-10). Runs inside a disposable WordPress with
// Scout Core active (Scout AI Search too, for the schema check):
//   wp eval-file tools/tests/sync-array-meta.php
// It creates and deletes its own pages; never point it at a real site.
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }

wp_set_current_user( 1 );
$manifest = tempnam( sys_get_temp_dir(), 'sync' );
add_filter( 'scout_sync_manifest_path', static function () use ( $manifest ) { return $manifest; } );
foreach ( array( Scout_Sync::BASES, Scout_Sync::JOURNAL, Scout_Sync::LOCK, Scout_Sync::SEEN ) as $o ) {
	delete_option( $o );
}
function write_manifest( $file, array $data ) {
	file_put_contents( $file, wp_json_encode( $data ) );
	delete_transient( Scout_Sync::SUMMARY );
}
function sync_with( $file, array $data ) {
	write_manifest( $file, $data );
	return Scout_Sync::apply( '', 'safe', array(), 'auto' );
}
function rows() {
	$o = array();
	foreach ( Scout_Sync::plan()['rows'] as $r ) {
		$o[ $r['id'] ] = $r;
	}
	return $o;
}
function state( $id ) {
	return rows()[ $id ]['state'] ?? 'absent';
}

$menu  = wp_insert_post( array( 'post_type' => 'page', 'post_name' => 'menu-test', 'post_title' => 'Menu', 'post_status' => 'publish', 'post_content' => '<p>Our menu.</p>' ) );
$about = wp_insert_post( array( 'post_type' => 'page', 'post_name' => 'about-arr', 'post_title' => 'About', 'post_status' => 'publish' ) );
update_post_meta( $about, 'scout_hero_heading', 'Plain heading' );
$id = 'page/menu-test#meta:_scout_aeo_faqs';

$faqs_v1 = array(
	array( 'q' => 'Do you take reservations?', 'a' => 'Yes, by phone or online.' ),
	array( 'q' => 'Is there parking?', 'a' => 'Free parking behind the building. Valet "on weekends" \\ only.' ),
);
$data = array(
	'sync_version' => 1,
	'pages'        => array( array( 'slug' => 'about-arr', 'seo_title' => 'About | Test' ) ),
	'fields'       => array(
		array( 'post_type' => 'page', 'slug' => 'menu-test', 'meta' => '_scout_aeo_faqs', 'value' => $faqs_v1 ),
		array( 'slug' => 'about-arr', 'field' => 'hero_heading', 'value' => 'Plain heading' ),
		array( 'slug' => 'about-arr', 'seo' => 'desc', 'value' => array( 'not', 'text' ) ),
	),
);

// Plan: the list is canonical JSON, not "Array".
write_manifest( $manifest, $data );
$r = rows();
ok( 'update' === ( $r[ $id ]['state'] ?? '' ), 'an empty FAQ field is a safe update' );
ok( isset( $r[ $id ]['theme'] ) && 'Array' !== $r[ $id ]['theme'] && json_decode( $r[ $id ]['theme'], true ) === $faqs_v1, 'the theme copy is the list as JSON, not "Array"' );
ok( 'current' === ( $r['page/about-arr#field:hero_heading']['state'] ?? '' ), 'a scalar field that matches still reads current' );
ok( ! isset( $r['page/about-arr#seo:desc'] ) && (bool) array_filter( Scout_Sync::plan()['errors'], static function ( $e ) { return false !== strpos( $e, 'takes text only' ); } ), 'a list on a text-only field (SEO) is refused and reported' );

// Write: a real array lands in post meta.
$res  = sync_with( $manifest, $data );
$meta = get_post_meta( $menu, '_scout_aeo_faqs', true );
ok( ! is_wp_error( $res ) && is_array( $meta ), 'the FAQ field is stored as an array' );
ok( is_array( $meta ) && $meta === $faqs_v1, 'the rows match the theme, quotes and backslashes intact' );
ok( 'current' === state( $id ), 'and read current afterwards' );
ok( 0 === Scout_Sync::apply( '', 'safe', array(), 'auto' )['written'], 'a second run writes nothing' );

// Key order inside a row never counts as an edit (a box that saves {a, q} still matches).
update_post_meta( $menu, '_scout_aeo_faqs', wp_slash( array_map( static function ( $row ) { return array( 'a' => $row['a'], 'q' => $row['q'] ); }, $faqs_v1 ) ) );
ok( 'current' === state( $id ), 'the same rows saved with keys in another order still read current' );

// The theme moves on, WordPress untouched: a safe update.
$faqs_v2              = $faqs_v1;
$faqs_v2[]            = array( 'q' => 'Do you have a kids menu?', 'a' => 'Yes.' );
$data['fields'][0]['value'] = $faqs_v2;
write_manifest( $manifest, $data );
ok( 'update' === state( $id ), 'a new question in the theme is a safe update' );
Scout_Sync::apply( '', 'safe', array(), 'auto' );
ok( get_post_meta( $menu, '_scout_aeo_faqs', true ) === $faqs_v2, 'the new question synced' );

// Undo puts back the earlier rows.
$u = Scout_Sync::undo();
ok( ! is_wp_error( $u ) && 1 === $u['restored'], 'undo restores the FAQ field' );
$back = get_post_meta( $menu, '_scout_aeo_faqs', true );
ok( is_array( $back ) && Scout_Sync::canon( $back ) === Scout_Sync::canon( $faqs_v1 ), 'undo put back the two rows as an array' );
ok( 'conflict' === state( $id ), 'after undo the rows wait for a person, as every undone row does' );
$plan = Scout_Sync::plan();
Scout_Sync::apply( $plan['hash'], 'take', array( $id ) );

// Edited in WordPress: flagged, never overwritten, and "keep my edit" holds when the theme moves.
$edited       = $faqs_v2;
$edited[0]['a'] = 'Yes. Call (555) 010-0000.';
update_post_meta( $menu, '_scout_aeo_faqs', wp_slash( $edited ) );
ok( 'edited' === state( $id ), 'a WordPress edit to the rows reads edited' );
Scout_Sync::apply( '', 'safe', array(), 'auto' );
ok( get_post_meta( $menu, '_scout_aeo_faqs', true ) === $edited, 'a safe run leaves the edit alone' );
$plan = Scout_Sync::plan();
$k    = Scout_Sync::apply( $plan['hash'], 'keep', array( $id ) );
ok( ! is_wp_error( $k ) && 'current' === state( $id ), 'keeping the WordPress rows clears the flag' );
$faqs_v3                    = $faqs_v2;
$faqs_v3[2]['a']            = 'Yes, for under 12s.';
$data['fields'][0]['value'] = $faqs_v3;
write_manifest( $manifest, $data );
ok( 'conflict' === state( $id ), 'kept rows are a conflict when the theme moves on, never auto-replaced' );
Scout_Sync::apply( '', 'safe', array(), 'auto' );
ok( get_post_meta( $menu, '_scout_aeo_faqs', true ) === $edited, 'the kept rows are still there' );

// Use the theme copy on purpose.
$plan = Scout_Sync::plan();
$t    = Scout_Sync::apply( $plan['hash'], 'take', array( $id ) );
ok( ! is_wp_error( $t ) && get_post_meta( $menu, '_scout_aeo_faqs', true ) === $faqs_v3, 'take writes the theme rows' );
$u = Scout_Sync::undo();
ok( ! is_wp_error( $u ) && get_post_meta( $menu, '_scout_aeo_faqs', true ) === $edited, 'undo after take puts back the kept edit' );

// A plain value saved where the theme has a list: a conflict, and undo puts the text back.
update_post_meta( $menu, '_scout_aeo_faqs', 'old text' );
ok( 'conflict' === state( $id ), 'a plain saved value against a list is a conflict' );
$plan = Scout_Sync::plan();
Scout_Sync::apply( $plan['hash'], 'take', array( $id ) );
Scout_Sync::undo();
ok( 'old text' === get_post_meta( $menu, '_scout_aeo_faqs', true ), 'undo restores the plain text, not a decoded copy' );

// Undo of a field that was never saved removes it again.
delete_post_meta( $menu, '_scout_aeo_faqs' );
delete_option( Scout_Sync::BASES );
Scout_Sync::apply( '', 'safe', array(), 'auto' );
ok( get_post_meta( $menu, '_scout_aeo_faqs', true ) === $faqs_v3, 'a fresh field fills' );
Scout_Sync::undo();
ok( ! metadata_exists( 'post', $menu, '_scout_aeo_faqs' ), 'undo removes a field the sync added' );

// An empty list in the theme matches a field never saved.
$data['fields'][0]['value'] = array();
write_manifest( $manifest, $data );
ok( 'current' === state( $id ), 'an empty list and no field read the same' );

// One key inside an array meta value can carry a list too.
update_post_meta( $about, '_scout_extra', array( 'title' => 'Keep me' ) );
$data['fields'][0]['value'] = $faqs_v3;
$data['fields'][]           = array( 'slug' => 'about-arr', 'meta' => '_scout_extra', 'key' => 'tags', 'value' => array( 'steak', 'wine' ) );
sync_with( $manifest, $data );
$extra = get_post_meta( $about, '_scout_extra', true );
ok( is_array( $extra ) && 'Keep me' === ( $extra['title'] ?? '' ) && array( 'steak', 'wine' ) === ( $extra['tags'] ?? null ), 'a list under one key is planted, the rest left alone' );
ok( 'current' === state( 'page/about-arr#meta:_scout_extra.tags' ), 'and reads current' );

// Scalar paths are untouched by any of this.
ok( 'Plain heading' === get_post_meta( $about, 'scout_hero_heading', true ) && 'About | Test' === get_post_meta( $about, SCOUT_SEO_META_TITLE, true ), 'scalar fields sync as before' );

// Scout AI Search prints FAQPage in Scout Core's graph from the synced rows.
if ( class_exists( 'Scout_AEO_FAQ' ) && class_exists( 'Scout_Schema' ) ) {
	$GLOBALS['wp_query']     = new WP_Query( array( 'page_id' => $menu ) );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$GLOBALS['wp_query']->the_post();
	ob_start();
	Scout_Schema::render();
	$html = (string) ob_get_clean();
	wp_reset_postdata();
	ok( (bool) preg_match( '/"@type"\s*:\s*(\[[^\]]*)?"FAQPage"/', $html ), 'the menu page prints FAQPage in Scout Core\'s graph' );
	ok( false !== strpos( $html, 'Do you have a kids menu?' ), 'with the synced questions' );
} else {
	echo "SKIP schema check: Scout AI Search is not active\n";
}

wp_delete_post( $menu, true );
wp_delete_post( $about, true );
foreach ( array( Scout_Sync::BASES, Scout_Sync::JOURNAL, Scout_Sync::LOCK ) as $o ) {
	delete_option( $o );
}
unlink( $manifest );
echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n";
if ( $fail ) {
	exit( 1 );
}
