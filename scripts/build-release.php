<?php
/**
 * Packages a release ZIP from a git ref.
 *
 * Usage:
 *   php scripts/build-release.php <ref> [--output=<directory>]
 *
 * The archive is built with `git archive`, so it always reflects the committed
 * ref rather than the working tree. Only the paths a WordPress install needs are
 * included; the handover exports in docs/ ship separately.
 *
 * @package BCI_Woo_Plugin
 */

declare( strict_types = 1 );

/**
 * Paths included in the plugin archive, relative to the repository root.
 */
const RELEASE_PATHS = array(
	'assets',
	'includes',
	'readme.txt',
	'woocommerce-gateway-bci.php',
	'docs/merchant-setup-guide.md',
	'docs/images/setup',
);

/**
 * Runs a command and returns its output, failing loudly on a non-zero exit.
 *
 * @param array<int, string> $arguments Command and arguments.
 * @return string
 */
function run( array $arguments ): string {
	$command = implode( ' ', array_map( 'escapeshellarg', $arguments ) );
	exec( $command . ' 2>&1', $output, $status );

	if ( 0 !== $status ) {
		throw new RuntimeException(
			sprintf( "Command failed (exit %d): %s\n%s", $status, $command, implode( "\n", $output ) )
		);
	}

	return implode( "\n", $output );
}

$arguments  = $argv;
$script     = array_shift( $arguments );
$repository = dirname( __DIR__ );
$reference  = null;
$output_dir = $repository . '/dist';

foreach ( $arguments as $argument ) {
	if ( preg_match( '/^--output=(.+)$/', $argument, $matches ) ) {
		$output_dir = $matches[1];
		continue;
	}

	if ( null === $reference ) {
		$reference = $argument;
		continue;
	}

	fwrite( STDERR, sprintf( "Unexpected argument: %s\n", $argument ) );
	exit( 1 );
}

if ( null === $reference ) {
	fwrite( STDERR, "Usage: php scripts/build-release.php <ref> [--output=<directory>]\n" );
	exit( 1 );
}

try {
	run( array( 'git', '-C', $repository, 'rev-parse', '--verify', $reference . '^{commit}' ) );

	$version = ltrim( $reference, 'v' );

	if ( ! is_dir( $output_dir ) && ! mkdir( $output_dir, 0755, true ) ) {
		throw new RuntimeException( sprintf( 'Unable to create %s.', $output_dir ) );
	}

	$archive_path = realpath( $output_dir ) . '/woocommerce-gateway-bci-v' . $version . '.zip';

	if ( is_file( $archive_path ) ) {
		unlink( $archive_path );
	}

	run(
		array_merge(
			array(
				'git',
				'-C',
				$repository,
				'archive',
				'--format=zip',
				'--prefix=woocommerce-gateway-bci/',
				'--output=' . $archive_path,
				$reference,
				'--',
			),
			RELEASE_PATHS
		)
	);

	printf( "Ref     %s\n", $reference );
	printf( "Path    %s\n", $archive_path );
	printf( "Size    %s bytes\n", number_format( (int) filesize( $archive_path ) ) );
	printf( "SHA256  %s\n", strtoupper( (string) hash_file( 'sha256', $archive_path ) ) );
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . "\n" );
	exit( 1 );
}
