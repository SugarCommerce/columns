<?php
/**
 * Row Actions
 *
 * @package     SugarCommerce\Columns
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns;

use SugarCommerce\Columns\RowActions\Comment;
use SugarCommerce\Columns\RowActions\Media;
use SugarCommerce\Columns\RowActions\Post;
use SugarCommerce\Columns\RowActions\Taxonomy;
use SugarCommerce\Columns\RowActions\User;
use SugarCommerce\Columns\Support\Registrar;

/**
 * Add links to the actions under a list table row's title.
 *
 * An action is a link (`url`, given `?id=`, or `url_callback`) or, with
 * `ajax`, a call to its `callback` that does not leave the page. The third
 * argument takes core actions off the row.
 */
final class RowActions {

	/**
	 * Row actions on a post type's list, or several.
	 *
	 * @param string|string[] $post_types Post type or types.
	 * @param array           $actions    Action key => configuration.
	 * @param string[]        $remove     Core action keys to take off.
	 *
	 * @return Post[]
	 */
	public static function posts( string|array $post_types, array $actions, array $remove = [] ): array {
		return Registrar::each( Post::class, $post_types, $actions, $remove );
	}

	/**
	 * Row actions on the users list.
	 *
	 * @param array    $actions Action key => configuration.
	 * @param string[] $remove  Core action keys to take off.
	 *
	 * @return User|null Null when the configuration was refused.
	 */
	public static function users( array $actions, array $remove = [] ): ?User {
		return Registrar::one( User::class, 'user', $actions, $remove );
	}

	/**
	 * Row actions on a taxonomy's terms list, or several.
	 *
	 * @param string|string[] $taxonomies Taxonomy or taxonomies.
	 * @param array           $actions    Action key => configuration.
	 * @param string[]        $remove     Core action keys to take off.
	 *
	 * @return Taxonomy[]
	 */
	public static function terms( string|array $taxonomies, array $actions, array $remove = [] ): array {
		return Registrar::each( Taxonomy::class, $taxonomies, $actions, $remove );
	}

	/**
	 * Row actions on the comments list.
	 *
	 * @param array    $actions Action key => configuration.
	 * @param string[] $remove  Core action keys to take off.
	 *
	 * @return Comment|null Null when the configuration was refused.
	 */
	public static function comments( array $actions, array $remove = [] ): ?Comment {
		return Registrar::one( Comment::class, 'comment', $actions, $remove );
	}

	/**
	 * Row actions on the media library's list view.
	 *
	 * @param array    $actions Action key => configuration.
	 * @param string[] $remove  Core action keys to take off.
	 *
	 * @return Media|null Null when the configuration was refused.
	 */
	public static function media( array $actions, array $remove = [] ): ?Media {
		return Registrar::one( Media::class, 'attachment', $actions, $remove );
	}

	/**
	 * One of two labels, by whether a meta flag is set.
	 *
	 * For a `label_callback` on an action that flips a flag: "Feature" on a
	 * row that is not featured, "Unfeature" on one that is.
	 *
	 * @param string $object_type    post, attachment, comment, user or term.
	 * @param int    $object_id      The object.
	 * @param string $meta_key       The flag.
	 * @param string $active_label   The label when the flag is set.
	 * @param string $inactive_label The label when it is not.
	 *
	 * @return string
	 */
	public static function toggle_label( string $object_type, int $object_id, string $meta_key, string $active_label, string $inactive_label ): string {
		return self::meta( $object_type, $object_id, $meta_key ) ? $active_label : $inactive_label;
	}

	/**
	 * Flip a meta flag and say what it is now.
	 *
	 * For an AJAX action's `callback`. An object type it does not know is
	 * left alone and answers true, as it always has: it reads as unset.
	 *
	 * @param string $object_type post, attachment, comment, user or term.
	 * @param int    $object_id   The object.
	 * @param string $meta_key    The flag.
	 *
	 * @return bool The new value.
	 */
	public static function toggle_meta( string $object_type, int $object_id, string $meta_key ): bool {
		$value = ! self::meta( $object_type, $object_id, $meta_key );

		match ( $object_type ) {
			'post', 'attachment' => update_post_meta( $object_id, $meta_key, $value ),
			'comment'            => update_comment_meta( $object_id, $meta_key, $value ),
			'user'               => update_user_meta( $object_id, $meta_key, $value ),
			'term'               => update_term_meta( $object_id, $meta_key, $value ),
			default              => null,
		};

		return $value;
	}

	/**
	 * An admin-ajax URL for an action on one object, with its nonce.
	 *
	 * The nonce action is `{$action}_{$id}`; check it the same way in the
	 * handler. `$args` adds to the query string, or replaces any of the
	 * three.
	 *
	 * @param string               $action The admin-ajax action.
	 * @param int                  $id     The object.
	 * @param array<string, mixed> $args   More query arguments.
	 *
	 * @return string
	 */
	public static function ajax_url( string $action, int $id, array $args = [] ): string {
		$args = array_merge(
			[
				'action' => $action,
				'id'     => $id,
				'nonce'  => wp_create_nonce( "{$action}_{$id}" ),
			],
			$args
		);

		return add_query_arg( $args, admin_url( 'admin-ajax.php' ) );
	}

	/**
	 * A meta value, through the object type's own function.
	 *
	 * @param string $object_type post, attachment, comment, user or term.
	 * @param int    $object_id   The object.
	 * @param string $meta_key    The key.
	 *
	 * @return mixed False for a type it does not know.
	 */
	private static function meta( string $object_type, int $object_id, string $meta_key ): mixed {
		return match ( $object_type ) {
			'post', 'attachment' => get_post_meta( $object_id, $meta_key, true ),
			'comment'            => get_comment_meta( $object_id, $meta_key, true ),
			'user'               => get_user_meta( $object_id, $meta_key, true ),
			'term'               => get_term_meta( $object_id, $meta_key, true ),
			default              => false,
		};
	}
}
