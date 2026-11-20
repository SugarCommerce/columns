<?php
/**
 * The page globals the scripts set.
 *
 * @package SugarCommerce\Columns
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * One page global, the registry keyed by handle, and nothing two copies
 * would both set.
 *
 * Two plugins each bundling a copy load the script twice, under two
 * handles. A fixed name -- the `window.SugarCommerceColumnsRowActions` flag
 * that let the first copy answer every copy's links -- couples them: one
 * plugin's older copy runs the other's actions.
 */
final class PageGlobalsTest extends TestCase {

	/**
	 * The one name the scripts may set on window: a registry, whose keys are
	 * each build's handle.
	 *
	 * @var string
	 */
	private const string REGISTRY = 'SugarCommerceColumns';

	/**
	 * Every script, as written and as built.
	 *
	 * @return array<string, string> Path relative to assets/ => source.
	 */
	private static function scripts(): array {
		$root    = dirname( __DIR__ ) . '/assets';
		$scripts = [];

		foreach ( [ 'js', 'build' ] as $dir ) {
			$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );

			foreach ( $files as $file ) {
				if ( 'js' === $file->getExtension() ) {
					$scripts[ substr( $file->getPathname(), strlen( $root ) + 1 ) ] = (string) file_get_contents( $file->getPathname() );
				}
			}
		}

		ksort( $scripts );

		return $scripts;
	}

	/**
	 * Nothing but the registry is set on window.
	 *
	 * @return void
	 */
	public function test_the_scripts_set_no_global_but_the_registry(): void {
		$wrong = [];

		foreach ( self::scripts() as $path => $source ) {
			preg_match_all( '/window\.([A-Za-z_$][\w$]*)\s*=(?!=)/', $source, $matches );

			foreach ( array_unique( $matches[1] ) as $name ) {
				if ( self::REGISTRY !== $name ) {
					$wrong[] = $path . ' sets window.' . $name;
				}
			}
		}

		$this->assertNotEmpty( self::scripts() );
		$this->assertSame( [], $wrong );
	}

	/**
	 * The built script finds its own entry from its element, and answers
	 * only the links that name it; the old shared flag is gone.
	 *
	 * @return void
	 */
	public function test_the_built_script_is_its_own_builds(): void {
		$built = self::scripts()['build/row-actions.js'] ?? '';

		$this->assertStringContainsString( 'document.currentScript', $built );
		$this->assertStringContainsString( '"data-config"', $built );
		$this->assertStringNotContainsString( 'SugarCommerceColumnsRowActions', $built );
	}

	/**
	 * No `wp_localize_script()`: its object name is a page global every copy
	 * would set.
	 *
	 * @return void
	 */
	public function test_nothing_is_localized_under_a_fixed_name(): void {
		$src   = dirname( __DIR__ ) . '/src';
		$found = [];
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ) );

		foreach ( $files as $file ) {
			if ( 'php' === $file->getExtension() && str_contains( (string) file_get_contents( $file->getPathname() ), 'wp_localize_script(' ) ) {
				$found[] = substr( $file->getPathname(), strlen( $src ) + 1 );
			}
		}

		$this->assertSame( [], $found );
	}
}
