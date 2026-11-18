<?php
/**
 * Filters
 *
 * @package     SugarCommerce\Columns
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns;

use SugarCommerce\Columns\Filters\Post;
use SugarCommerce\Columns\Filters\User;
use SugarCommerce\Columns\Support\Registrar;

/**
 * Add dropdown filters above a list table, applied to its query.
 *
 * A filter's options are given outright or read from a taxonomy when the
 * table is drawn. What a choice does is a `query_callback`, a tax query for
 * a taxonomy filter, or otherwise a meta query on the filter's own key.
 */
final class Filters {

	/**
	 * Filters above a post type's list, or several.
	 *
	 * @param string|string[] $post_types Post type or types.
	 * @param array           $filters    Filter key => configuration.
	 *
	 * @return Post[]
	 */
	public static function posts( string|array $post_types, array $filters ): array {
		return Registrar::each( Post::class, $post_types, $filters );
	}

	/**
	 * Filters above the users list, with the Filter button core does not
	 * put there.
	 *
	 * @param array $filters Filter key => configuration.
	 *
	 * @return User|null Null when the configuration was refused.
	 */
	public static function users( array $filters ): ?User {
		return Registrar::one( User::class, 'user', $filters );
	}
}
