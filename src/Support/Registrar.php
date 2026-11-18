<?php
/**
 * Registrar
 *
 * @package     SugarCommerce\Columns
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\Support;

use Exception;

/**
 * Builds the table classes for the four front doors.
 *
 * Columns, filters, bulk actions and row actions are registered the same
 * way: one instance per post type or taxonomy named, or one for a table that
 * has no subtypes. A key that is not a string throws in the table class; it
 * is logged here and that table is skipped, so one bad array does not take
 * the admin screen down with it.
 */
final class Registrar {

	/**
	 * One instance per subtype.
	 *
	 * @template T of object
	 *
	 * @param class-string<T> $table    The table class.
	 * @param string|string[] $subtypes Post types or taxonomies.
	 * @param array           $config   What to register.
	 * @param array|null      $remove   Core keys to take off, for the classes that take them.
	 *
	 * @return T[]
	 */
	public static function each( string $table, string|array $subtypes, array $config, ?array $remove = null ): array {
		$instances = [];

		foreach ( (array) $subtypes as $subtype ) {
			$instance = self::one( $table, (string) $subtype, $config, $remove );

			if ( null !== $instance ) {
				$instances[] = $instance;
			}
		}

		return $instances;
	}

	/**
	 * One instance.
	 *
	 * @template T of object
	 *
	 * @param class-string<T> $table   The table class.
	 * @param string          $subtype The subtype, or the table's fixed one.
	 * @param array           $config  What to register.
	 * @param array|null      $remove  Core keys to take off, for the classes that take them.
	 *
	 * @return T|null Null when the configuration was refused.
	 */
	public static function one( string $table, string $subtype, array $config, ?array $remove = null ): ?object {
		try {
			return null === $remove ? new $table( $config, $subtype ) : new $table( $config, $subtype, $remove );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- a developer's mistake, reported where a developer looks.
			error_log( 'SugarCommerce Columns: ' . $e->getMessage() );

			return null;
		}
	}
}
