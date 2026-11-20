<?php
/**
 * Runtime Key Derivation
 *
 * @package     SugarCommerce\Columns
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Utils;

use SugarCommerce\Plugin\Build;

/**
 * Every runtime string this library registers, derived from its own namespace.
 *
 * Strauss rewrites class namespaces and leaves string literals alone. Two
 * plugins each bundling a prefixed copy of this library therefore get
 * distinct classes but would otherwise register identical script handles and
 * identical transient keys.
 *
 * That is not merely untidy. `wp_enqueue_style()` ignores a handle that is
 * already registered, so the plugin that enqueued second gets the other
 * plugin's stylesheet — from a directory its own build may not even contain.
 * And a shared transient means one plugin's activation sends the user to the
 * other plugin's wizard.
 *
 * The derivation exploits the one thing Strauss does rewrite: this file's
 * namespace. In a prefixed build `__NAMESPACE__` begins with the consumer's
 * prefix ("MyPlugin\SugarCommerce\Columns\Utils"), unique per plugin
 * by construction, so every key comes out distinct with no configuration.
 */
final class Runtime {

	/**
	 * This library's own identifier, used when running unprefixed.
	 */
	private const string LIBRARY = 'sugarcommerce-columns';

	/**
	 * How many segments this class's own namespace has --
	 * SugarCommerce\Columns\Utils -- before any prefix. Counted rather than
	 * written out: Strauss rewrites a namespace it finds in a string, and
	 * would prefix the very thing being compared against.
	 */
	private const int OWN_SEGMENTS = 3;

	/**
	 * The per-build prefix.
	 *
	 * "sugarcommerce-columns" for a plain Composer install — development, or a single
	 * consumer that does not use Strauss — and "{prefix}-sugarcommerce-columns" for a
	 * prefixed build.
	 *
	 * @return string
	 */
	public static function prefix(): string {
		return self::prefix_for( __NAMESPACE__ );
	}

	/**
	 * The prefix a given namespace would produce.
	 *
	 * Split out so the rule can be tested. prefix() reads __NAMESPACE__,
	 * which cannot be changed at runtime, so a test can only reach the
	 * prefixed case by asking the rule directly — this takes the namespace
	 * as an argument, and prefix() hands it __NAMESPACE__.
	 *
	 * The alternative, compiling a copy of this file under another namespace
	 * at runtime, was tried and abandoned: the interpreter that does it
	 * refuses the `declare( strict_types=1 )` at the top of this file on PHP
	 * 8.3 and 8.4, so the test passed on 8.5 alone and reported the local PHP
	 * version rather than the code. It is also a construct that has no place
	 * in a file that ships inside somebody's plugin.
	 *
	 * @param string $under The namespace this class is compiled under, prefixed or not.
	 *
	 * @return string
	 */
	public static function prefix_for( string $under ): string {
		$build = self::build_for( $under );

		return '' === $build ? self::LIBRARY : $build . '-' . self::LIBRARY;
	}

	/**
	 * The name of the build a namespace belongs to, as a hyphenated slug.
	 *
	 * Empty when the namespace is this package's own, unprefixed. Otherwise
	 * what the build put around it, in one of two shapes:
	 *
	 * - A prefix in front of the whole namespace,
	 *   "MyShop\SugarCommerce\…\Utils": the prefix's first segment, as it
	 *   always was ("SugarCart\Vendor" is "sugarcart"), or all of it when it
	 *   starts "SugarCommerce" too -- a family plugin's
	 *   "SugarCommerce\Playground\Vendor" would otherwise be taken for the
	 *   library itself.
	 * - A plugin's own namespace with the vendor segment dropped,
	 *   "ArrayPress\EDD\FraudFilter\Lib\…\Utils": every segment before "Lib".
	 *   The first alone is the vendor every such plugin shares, and two of
	 *   them would answer each other's routes and load each other's scripts.
	 *
	 * @param string $under The namespace this class is compiled under, prefixed or not.
	 *
	 * @return string
	 */
	public static function build_for( string $under ): string {
		return Build::name( $under, self::OWN_SEGMENTS );
	}

	/**
	 * A script or style handle for this build.
	 *
	 * @param string $suffix Optional handle suffix.
	 *
	 * @return string
	 */
	public static function handle( string $suffix = '' ): string {
		return '' === $suffix ? self::prefix() : self::prefix() . '-' . $suffix;
	}

	/**
	 * An option or transient key for this build.
	 *
	 * @param string $suffix Optional key suffix.
	 *
	 * @return string
	 */
	public static function key( string $suffix = '' ): string {
		$base = str_replace( '-', '_', self::prefix() );

		return '' === $suffix ? $base : $base . '_' . $suffix;
	}

	/**
	 * The REST namespace this copy's routes live under: `{prefix}/v1`.
	 *
	 * The collision this avoids is the quiet kind. Two prefixed copies
	 * registering the same route merge into one, and the first registered
	 * answers both plugins' requests -- looking the action up in its own
	 * registry, where the other plugin's is not.
	 *
	 * @return string
	 */
	public static function rest_namespace(): string {
		return self::prefix() . '/v1';
	}
}
