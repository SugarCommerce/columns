<?php
/**
 * Runtime name derivation tests.
 *
 * @package SugarCommerce\Columns
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Tests;

use SugarCommerce\Columns\Utils\Runtime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Handles, AJAX actions and nonce keys come from the namespace this copy is
 * compiled under, so two plugins bundling the library never share them.
 */
final class RuntimeTest extends TestCase {

	/**
	 * Each namespace gives its build's prefix.
	 *
	 * @param string $under    The namespace the class is compiled under.
	 * @param string $expected The prefix it gives.
	 */
	#[DataProvider( 'namespaces' )]
	public function test_a_build_is_named_for_itself( string $under, string $expected ): void {
		$this->assertSame( $expected, Runtime::prefix_for( $under ) );
	}

	/**
	 * Namespaces and the prefix each gives.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function namespaces(): array {
		return [
			'unprefixed'         => [ 'SugarCommerce\\Columns\\Utils', 'sugarcommerce-columns' ],
			'nothing at all'     => [ '', 'sugarcommerce-columns' ],
			'a plugin prefix'    => [ 'EDDFF\\SugarCommerce\\Columns\\Utils', 'eddff-sugarcommerce-columns' ],
			'a vendor prefix'    => [ 'SugarCart\\Vendor\\SugarCommerce\\Columns\\Utils', 'sugarcart-sugarcommerce-columns' ],
			'a family plugin'    => [ 'SugarCommerce\\Playground\\Vendor\\SugarCommerce\\Columns\\Utils', 'sugarcommerce-playground-vendor-sugarcommerce-columns' ],
			'a plugin namespace' => [ 'ArrayPress\\EDD\\FraudFilter\\Lib\\Columns\\Utils', 'arraypress-edd-fraudfilter-sugarcommerce-columns' ],
			'a one-segment Lib'  => [ 'Playground\\Lib\\Columns\\Utils', 'playground-sugarcommerce-columns' ],
		];
	}

	/**
	 * Two plugins under one vendor, each with its packages in its own Lib, are
	 * two builds: the vendor segment they share does not name either.
	 */
	public function test_plugins_sharing_a_vendor_are_told_apart(): void {
		$this->assertNotSame(
			Runtime::prefix_for( 'ArrayPress\\EDD\\FraudFilter\\Lib\\Columns\\Utils' ),
			Runtime::prefix_for( 'ArrayPress\\EDD\\SalePrices\\Lib\\Columns\\Utils' )
		);
	}
}
