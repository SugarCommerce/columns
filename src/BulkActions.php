<?php
/**
 * Bulk Actions
 *
 * @package     SugarCommerce\Columns
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns;

use SugarCommerce\Columns\BulkActions\Comment;
use SugarCommerce\Columns\BulkActions\Media;
use SugarCommerce\Columns\BulkActions\Post;
use SugarCommerce\Columns\BulkActions\Taxonomy;
use SugarCommerce\Columns\BulkActions\User;
use SugarCommerce\Columns\Support\Registrar;

/**
 * Add actions to a list table's Bulk actions menu.
 *
 * The callback is handed the selected ids as integers. Return
 * `[ 'message' => …, 'success' => false ]` to say something, or anything
 * else for the default "N items processed." notice.
 */
final class BulkActions {

	/**
	 * Bulk actions on a post type's list, or several.
	 *
	 * @param string|string[] $post_types Post type or types.
	 * @param array           $actions    Action key => configuration.
	 *
	 * @return Post[]
	 */
	public static function posts( string|array $post_types, array $actions ): array {
		return Registrar::each( Post::class, $post_types, $actions );
	}

	/**
	 * Bulk actions on the users list.
	 *
	 * @param array $actions Action key => configuration.
	 *
	 * @return User|null Null when the configuration was refused.
	 */
	public static function users( array $actions ): ?User {
		return Registrar::one( User::class, 'user', $actions );
	}

	/**
	 * Bulk actions on a taxonomy's terms list, or several.
	 *
	 * @param string|string[] $taxonomies Taxonomy or taxonomies.
	 * @param array           $actions    Action key => configuration.
	 *
	 * @return Taxonomy[]
	 */
	public static function terms( string|array $taxonomies, array $actions ): array {
		return Registrar::each( Taxonomy::class, $taxonomies, $actions );
	}

	/**
	 * Bulk actions on the comments list.
	 *
	 * @param array $actions Action key => configuration.
	 *
	 * @return Comment|null Null when the configuration was refused.
	 */
	public static function comments( array $actions ): ?Comment {
		return Registrar::one( Comment::class, 'comment', $actions );
	}

	/**
	 * Bulk actions on the media library's list view.
	 *
	 * @param array $actions Action key => configuration.
	 *
	 * @return Media|null Null when the configuration was refused.
	 */
	public static function media( array $actions ): ?Media {
		return Registrar::one( Media::class, 'attachment', $actions );
	}
}
