<?php
/**
 * Post Row Actions Class
 *
 * Handles custom row action registration for WordPress posts and custom post types.
 * Integrates with WordPress post row action filters.
 *
 * @package     SugarCommerce\Columns
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace SugarCommerce\Columns\RowActions;

use SugarCommerce\Columns\Abstracts\RowActions;
use SugarCommerce\Columns\Utils\Runtime;
use WP_Post;

/**
 * Class Post
 *
 * Manages custom row actions for posts in the WordPress admin.
 *
 * @package SugarCommerce\Columns
 */
class Post extends RowActions {

	/**
	 * Object type for posts.
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = 'post';

	/**
	 * Load the necessary hooks for custom row actions.
	 *
	 * Registers WordPress hooks for adding custom post row actions.
	 *
	 * @return void
	 */
	public function load_hooks(): void {
		// Core has two post row action filters and neither is named for the
		// post type: `post_row_actions` for a flat type and `page_row_actions`
		// for a hierarchical one. `{post_type}_row_actions` -- what this
		// hooked -- fired only for `post` and `page` themselves, and the
		// `post` one fired for every flat custom type too. So a custom post
		// type got no actions of its own and every post's.
		add_filter( 'post_row_actions', [ $this, 'register_actions_wrapper' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'register_actions_wrapper' ], 10, 2 );
		add_action( 'wp_ajax_' . Runtime::ajax_action( $this->object_type . '_' . $this->object_subtype ), [ $this, 'handle_ajax' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Wrapper for registering actions with WordPress filter.
	 *
	 * @param array   $actions Array of existing actions.
	 * @param WP_Post $post    The post object.
	 *
	 * @return array Updated array of actions.
	 */
	public function register_actions_wrapper( array $actions, WP_Post $post ): array {
		if ( $post->post_type !== $this->object_subtype ) {
			return $actions;
		}

		return $this->register_actions( $actions, $post, $post->ID );
	}
}
