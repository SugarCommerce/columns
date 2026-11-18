<?php
/**
 * Columns
 *
 * @package     SugarCommerce\Columns
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns;

use SugarCommerce\Columns\Support\Registrar;
use SugarCommerce\Columns\Tables\Comment;
use SugarCommerce\Columns\Tables\Media;
use SugarCommerce\Columns\Tables\Post;
use SugarCommerce\Columns\Tables\Taxonomy;
use SugarCommerce\Columns\Tables\User;

/**
 * Add columns to WordPress's own list tables.
 *
 * Each column is an array -- label, meta_key or display_callback, position,
 * sortable, width, image -- and the four hooks a column needs (add it,
 * render it, make it sortable, sort the query) are wired from it. The third
 * argument takes core columns off the table.
 *
 * Register on `admin_init`: early enough that the list table has not asked
 * for its columns, late enough that post types and taxonomies exist.
 */
final class Columns {

	/**
	 * Columns on a post type's list, or several.
	 *
	 * @param string|string[] $post_types Post type or types.
	 * @param array           $columns    Column key => configuration.
	 * @param string[]        $remove     Core column keys to take off.
	 *
	 * @return Post[]
	 */
	public static function posts( string|array $post_types, array $columns, array $remove = [] ): array {
		return Registrar::each( Post::class, $post_types, $columns, $remove );
	}

	/**
	 * Columns on the users list.
	 *
	 * @param array    $columns Column key => configuration.
	 * @param string[] $remove  Core column keys to take off.
	 *
	 * @return User|null Null when the configuration was refused.
	 */
	public static function users( array $columns, array $remove = [] ): ?User {
		return Registrar::one( User::class, 'user', $columns, $remove );
	}

	/**
	 * Columns on a taxonomy's terms list, or several.
	 *
	 * @param string|string[] $taxonomies Taxonomy or taxonomies.
	 * @param array           $columns    Column key => configuration.
	 * @param string[]        $remove     Core column keys to take off.
	 *
	 * @return Taxonomy[]
	 */
	public static function terms( string|array $taxonomies, array $columns, array $remove = [] ): array {
		return Registrar::each( Taxonomy::class, $taxonomies, $columns, $remove );
	}

	/**
	 * Columns on the comments list.
	 *
	 * @param array    $columns Column key => configuration.
	 * @param string[] $remove  Core column keys to take off.
	 *
	 * @return Comment|null Null when the configuration was refused.
	 */
	public static function comments( array $columns, array $remove = [] ): ?Comment {
		return Registrar::one( Comment::class, 'comment', $columns, $remove );
	}

	/**
	 * Columns on the media library's list view.
	 *
	 * @param array    $columns Column key => configuration.
	 * @param string[] $remove  Core column keys to take off.
	 *
	 * @return Media|null Null when the configuration was refused.
	 */
	public static function media( array $columns, array $remove = [] ): ?Media {
		return Registrar::one( Media::class, 'attachment', $columns, $remove );
	}
}
