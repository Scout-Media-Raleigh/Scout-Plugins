<?php
// Scout AI Search and Scout Analytics read Scout Core's staging hosts. No WordPress needed:
//   php tools/tests/staging-hosts.php
// It loads the real classes; first without Scout Core (each plugin's own fallback),
// then with Scout Core's staging guard loaded.
define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__, 2 ) . '/scout-aeo/includes/class-scout-aeo.php';
require dirname( __DIR__, 2 ) . '/scout-analytics/includes/class-scout-analytics.php';

$fail = 0;
function ok( $cond, $msg ) {
	global $fail;
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n";
	if ( ! $cond ) {
		$fail++;
	}
}

foreach ( array( 'Scout_AEO', 'Scout_Analytics' ) as $c ) {
	ok( ! class_exists( 'Scout_Core_Staging' ), "$c: Scout Core not loaded yet" );
	ok( $c::staging_host( 'clientstg.wpenginepowered.com' ) && $c::staging_host( 'client.wpengine.com' ), "$c without Core: WP Engine staging is staging" );
	ok( ! $c::staging_host( 'client.kinsta.cloud' ), "$c without Core: only its own two hosts" );
	ok( $c::staging_host( 'localhost' ) && $c::staging_host( 'site.local' ) && $c::staging_host( 'site.test' ) && $c::staging_host( '192.168.1.5' ), "$c without Core: local and private addresses" );
	ok( ! $c::staging_host( 'example.com' ), "$c without Core: a live domain is live" );
}

require dirname( __DIR__, 2 ) . '/scout-core/includes/staging-guard.php';
foreach ( array( 'Scout_AEO', 'Scout_Analytics' ) as $c ) {
	foreach ( Scout_Core_Staging::HOSTS as $suffix ) {
		ok( $c::staging_host( 'client' . $suffix ), "$c with Core: client$suffix is staging" );
	}
	ok( $c::staging_host( 'CLIENT.KINSTA.CLOUD' ), "$c with Core: case does not matter" );
	ok( $c::staging_host( 'staging.example.com' ) && $c::staging_host( '127.0.0.1' ), "$c with Core: its own staging and local rules still apply" );
	ok( ! $c::staging_host( 'example.com' ) && ! $c::staging_host( 'kinsta.cloud.example.com' ), "$c with Core: a live domain is live, even one that contains a staging suffix" );
}

echo $fail ? "\n$fail failed\n" : "\nAll passed\n";
exit( $fail ? 1 : 0 );
