<?php

declare( strict_types = 1 );

$tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! is_string( $tests_dir ) || '' === $tests_dir ) {
	$tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__, 2 ) . '/asc-ai-plugin/asc-ai-plugin.php';
	}
);

require $tests_dir . '/includes/bootstrap.php';
